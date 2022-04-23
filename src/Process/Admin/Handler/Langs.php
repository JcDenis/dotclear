<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Langs
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Zip\Unzip;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Feed\Reader;
use Dotclear\Helper\Network\NetHttp\NetHttp;
use Dotclear\Helper\L10n;
use Exception;

/**
 * Admin langs page.
 *
 * @ingroup  Admin Lang Localisation Handler
 */
class Langs extends AbstractPage
{
    private $lang_is_writable = false;
    private $lang_iso_codes   = [];

    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        $this->lang_is_writable = is_dir(dotclear()->config()->get('l10n_dir')) && is_writable(dotclear()->config()->get('l10n_dir'));
        $this->lang_iso_codes   = L10n::getISOCodes();

        // Delete a language pack
        if ($this->lang_is_writable && !empty($_POST['delete']) && !empty($_POST['locale_id'])) {
            try {
                $locale_id = $_POST['locale_id'];
                if (!isset($this->lang_iso_codes[$locale_id]) || !is_dir(dotclear()->config()->get('l10n_dir') . '/' . $locale_id)) {
                    throw new AdminException(__('No such installed language'));
                }

                if ('en' == $locale_id) {
                    throw new AdminException(__("You can't remove English language."));
                }

                if (!Files::deltree(dotclear()->config()->get('l10n_dir') . '/' . $locale_id)) {
                    throw new AdminException(__('Permissions to delete language denied.'));
                }

                dotclear()->notice()->addSuccessNotice(__('Language has been successfully deleted.'));
                dotclear()->adminurl()->redirect('admin.langs');
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        // Download a language pack
        if ($this->lang_is_writable && !empty($_POST['pkg_url'])) {
            try {
                if (empty($_POST['your_pwd']) || !dotclear()->user()->checkPassword($_POST['your_pwd'])) {
                    throw new AdminException(__('Password verification failed'));
                }

                $url  = Html::escapeHTML($_POST['pkg_url']);
                $dest = dotclear()->config()->get('l10n_dir') . '/' . basename($url);
                if (!preg_match('#^https://[^.]+\.dotclear\.(net|org)/.*\.zip$#', $url)) {
                    throw new AdminException(__('Invalid language file URL.'));
                }

                $client = NetHttp::initClient($url, $path);
                $client->setUserAgent('Dotclear - https://dotclear.org/');
                $client->useGzip(false);
                $client->setPersistReferers(false);
                $client->setOutput($dest);
                $client->get($path);

                try {
                    $ret_code = $this->langInstall($dest);
                } catch (Exception $e) {
                    @unlink($dest);

                    throw $e;
                }

                @unlink($dest);
                if (2 == $ret_code) {
                    dotclear()->notice()->addSuccessNotice(__('Language has been successfully upgraded'));
                } else {
                    dotclear()->notice()->addSuccessNotice(__('Language has been successfully installed.'));
                }
                dotclear()->adminurl()->redirect('admin.langs');
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        // Upload a language pack
        if ($this->lang_is_writable && !empty($_POST['upload_pkg'])) {
            try {
                if (empty($_POST['your_pwd']) || !dotclear()->user()->checkPassword($_POST['your_pwd'])) {
                    throw new AdminException(__('Password verification failed'));
                }

                Files::uploadStatus($_FILES['pkg_file']);
                $dest = dotclear()->config()->get('l10n_dir') . '/' . $_FILES['pkg_file']['name'];
                if (!move_uploaded_file($_FILES['pkg_file']['tmp_name'], $dest)) {
                    throw new AdminException(__('Unable to move uploaded file.'));
                }

                try {
                    $ret_code = $this->langInstall($dest);
                } catch (Exception $e) {
                    @unlink($dest);

                    throw $e;
                }

                @unlink($dest);
                if (2 == $ret_code) {
                    dotclear()->notice()->addSuccessNotice(__('Language has been successfully upgraded'));
                } else {
                    dotclear()->notice()->addSuccessNotice(__('Language has been successfully installed.'));
                }
                dotclear()->adminurl()->redirect('admin.langs');
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Languages management'))
            ->setPageHelp('core_langs')
            ->setPageHead(dotclear()->resource()->load('_langs.js'))
            ->setPageBreadcrumb([
                __('System')               => '',
                __('Languages management') => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['removed'])) {
            dotclear()->notice()->success(__('Language has been successfully deleted.'));
        }

        if (!empty($_GET['added'])) {
            dotclear()->notice()->success((2 == $_GET['added'] ? __('Language has been successfully upgraded') : __('Language has been successfully installed.')));
        }

        // Get languages list on Dotclear.net
        $dc_langs    = false;
        $feed_reader = new Reader();
        $feed_reader->setCacheDir(dotclear()->config()->get('cache_dir'));
        $feed_reader->setTimeout(5);
        $feed_reader->setUserAgent('Dotclear - https://dotclear.org/');

        try {
            $dc_langs = $feed_reader->parse(sprintf(dotclear()->config()->get('l10n_update_url'), dotclear()->config()->get('core_version')));
            if (false !== $dc_langs) {
                $dc_langs = $dc_langs->items;
            }
        } catch (\Exception) {
        }

        echo '<p>' . __('Here you can install, upgrade or remove languages for your Dotclear ' .
            'installation.') . '</p>' .
        '<p>' . sprintf(
            __('You can change your user language in your <a href="%1$s">preferences</a> or ' .
            'change your blog\'s main language in your <a href="%2$s">blog settings</a>.'),
            dotclear()->adminurl()->get('admin.user.pref'),
            dotclear()->adminurl()->get('admin.blog.pref')
        ) . '</p>';

        echo '<h3>' . __('Installed languages') . '</h3>';

        $locales_content = [];
        $tmp             = Files::scandir(dotclear()->config()->get('l10n_dir'));
        foreach ($tmp as $v) {
            $c = ('.' == $v || '..' == $v || 'en' == $v || !is_dir(dotclear()->config()->get('l10n_dir') . '/' . $v) || !isset($this->lang_iso_codes[$v]));

            if (!$c) {
                $locales_content[$v] = dotclear()->config()->get('l10n_dir') . '/' . $v;
            }
        }

        if (empty($locales_content)) {
            echo '<p><strong>' . __('No additional language is installed.') . '</strong></p>';
        } else {
            echo '<div class="table-outer clear">' .
            '<table class="plugins"><tr>' .
            '<th>' . __('Language') . '</th>' .
            '<th class="nowrap">' . __('Action') . '</th>' .
                '</tr>';

            foreach ($locales_content as $k => $v) {
                $is_deletable = $this->lang_is_writable && is_writable($v);

                echo '<tr class="line wide">' .
                '<td class="maximal nowrap">(' . $k . ') ' .
                '<strong>' . Html::escapeHTML($this->lang_iso_codes[$k]) . '</strong></td>' .
                    '<td class="nowrap action">';

                if ($is_deletable) {
                    echo '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
                    '<div>' .
                    dotclear()->adminurl()->getHiddenFormFields('admin.langs', ['locale_id' => Html::escapeHTML($k)], true) .
                    '<input type="submit" class="delete" name="delete" value="' . __('Delete') . '" /> ' .
                        '</div>' .
                        '</form>';
                }

                echo '</td></tr>';
            }
            echo '</table></div>';
        }

        echo '<h3>' . __('Install or upgrade languages') . '</h3>';

        if (!$this->lang_is_writable) {
            echo '<p>' . sprintf(__('You can install or remove a language by adding or ' .
                'removing the relevant directory in your %s folder.'), '<strong>locales</strong>') . '</p>';
        }

        if (!empty($dc_langs) && $this->lang_is_writable) {
            $dc_langs_combo = [];
            foreach ($dc_langs as $k => $v) {
                if ($v->link && isset($this->lang_iso_codes[$v->title])) {
                    $dc_langs_combo[Html::escapeHTML('(' . $v->title . ') ' . $this->lang_iso_codes[$v->title])] = Html::escapeHTML($v->link);
                }
            }

            echo '<form method="post" action="' . dotclear()->adminurl()->root() . '" enctype="multipart/form-data" class="fieldset">' .
            '<h4>' . __('Available languages') . '</h4>' .
            '<p>' . sprintf(__('You can download and install a additional language directly from Dotclear.net. ' .
                'Proposed languages are based on your version: %s.'), '<strong>' . dotclear()->config()->get('core_version') . '</strong>') . '</p>' .
            '<p class="field"><label for="pkg_url" class="classic">' . __('Language:') . '</label> ' .
            Form::combo(['pkg_url'], $dc_langs_combo) . '</p>' .
            '<p class="field"><label for="your_pwd1" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
            Form::password(
                ['your_pwd', 'your_pwd1'],
                20,
                255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password', ]
            ) . '</p>' .
            '<p><input type="submit" value="' . __('Install language') . '" />' .
            dotclear()->adminurl()->getHiddenFormFields('admin.langs', [], true) .
                '</p>' .
                '</form>';
        }

        if ($this->lang_is_writable) {
            // 'Upload language pack' form
            echo '<form method="post" action="' . dotclear()->adminurl()->root() . '" enctype="multipart/form-data" class="fieldset">' .
            '<h4>' . __('Upload a zip file') . '</h4>' .
            '<p>' . __('You can install languages by uploading zip files.') . '</p>' .
            '<p class="field"><label for="pkg_file" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Language zip file:') . '</label> ' .
            '<input type="file" id="pkg_file" name="pkg_file" required /></p>' .
            '<p class="field"><label for="your_pwd2" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
            Form::password(
                ['your_pwd', 'your_pwd2'],
                20,
                255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password', ]
            ) . '</p>' .
            '<p><input type="submit" name="upload_pkg" value="' . __('Upload language') . '" />' .
            dotclear()->adminurl()->getHiddenFormFields('admin.langs', [], true) .
                '</p>' .
                '</form>';
        }
    }

    private function langInstall($file)
    {
        $zip = new Unzip($file);
        $zip->getList(false, '#(^|/)(__MACOSX|\.svn|\.hg.*|\.git.*|\.DS_Store|\.directory|Thumbs\.db)(/|$)#');

        if (!preg_match('/^[a-z]{2,3}(-[a-z]{2})?$/', $zip->getRootDir())) {
            throw new AdminException(__('Invalid language zip file.'));
        }

        if ($zip->isEmpty() || !$zip->hasFile($zip->getRootDir() . '/main.po')) {
            throw new AdminException(__('The zip file does not appear to be a valid Dotclear language pack.'));
        }

        $target      = dirname($file);
        $destination = $target . '/' . $zip->getRootDir();
        $res         = 1;

        if (is_dir($destination)) {
            if (!Files::deltree($destination)) {
                throw new AdminException(__('An error occurred during language upgrade.'));
            }
            $res = 2;
        }

        $zip->unzipAll($target);

        return $res;
    }
}
