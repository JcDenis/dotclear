<?php
/**
 * @class Dotclear\Admin\Page\Langs
 * @brief Dotclear admin langs page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;

use Dotclear\Utils\L10n;
use Dotclear\Utils\Form;
use Dotclear\File\Files;
use Dotclear\Html\Html;
use Dotclear\File\Zip\Unzip;
use Dotclear\Network\Feed\Reader;
use Dotclear\Network\NetHttp\NetHttp;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Langs extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->checkSuper();

        $is_writable = is_dir(DOTCLEAR_L10N_DIR) && is_writable(DOTCLEAR_L10N_DIR);
        $iso_codes   = L10n::getISOCodes();

        # Get languages list on Dotclear.net
        $dc_langs    = false;
        $feed_reader = new Reader;
        $feed_reader->setCacheDir(DOTCLEAR_CACHE_DIR);
        $feed_reader->setTimeout(5);
        $feed_reader->setUserAgent('Dotclear - https://dotclear.org/');

        try {
            $dc_langs = $feed_reader->parse(sprintf(DOTCLEAR_L10N_UPDATE_URL, DOTCLEAR_VERSION));   // @phpstan-ignore-line
            if ($dc_langs !== false) {
                $dc_langs = $dc_langs->items;
            }
        } catch (Exception $e) {
        }

        # Language installation function
        ;

        # Delete a language pack
        if ($is_writable && !empty($_POST['delete']) && !empty($_POST['locale_id'])) {
            try {
                $locale_id = $_POST['locale_id'];
                if (!isset($iso_codes[$locale_id]) || !is_dir(DOTCLEAR_L10N_DIR . '/' . $locale_id)) {
                    throw new AdminException(__('No such installed language'));
                }

                if ($locale_id == 'en') {
                    throw new AdminException(__("You can't remove English language."));
                }

                if (!Files::deltree(DOTCLEAR_L10N_DIR . '/' . $locale_id)) {
                    throw new AdminException(__('Permissions to delete language denied.'));
                }

                self::addSuccessNotice(__('Language has been successfully deleted.'));
                $this->core->adminurl->redirect('admin.langs');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        # Download a language pack
        if ($is_writable && !empty($_POST['pkg_url'])) {
            try {
                if (empty($_POST['your_pwd']) || !$this->core->auth->checkPassword($_POST['your_pwd'])) {
                    throw new AdminException(__('Password verification failed'));
                }

                $url  = html::escapeHTML($_POST['pkg_url']);
                $dest = DOTCLEAR_L10N_DIR . '/' . basename($url);
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
                    $ret_code = self::langInstall($dest);
                } catch (Exception $e) {
                    @unlink($dest);

                    throw $e;
                }

                @unlink($dest);
                if ($ret_code == 2) {
                    self::addSuccessNotice(__('Language has been successfully upgraded'));
                } else {
                    self::addSuccessNotice(__('Language has been successfully installed.'));
                }
                $this->core->adminurl->redirect('admin.langs');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        # Upload a language pack
        if ($is_writable && !empty($_POST['upload_pkg'])) {
            try {
                if (empty($_POST['your_pwd']) || !$this->core->auth->checkPassword($_POST['your_pwd'])) {
                    throw new AdminException(__('Password verification failed'));
                }

                Files::uploadStatus($_FILES['pkg_file']);
                $dest = DOTCLEAR_L10N_DIR . '/' . $_FILES['pkg_file']['name'];
                if (!move_uploaded_file($_FILES['pkg_file']['tmp_name'], $dest)) {
                    throw new AdminException(__('Unable to move uploaded file.'));
                }

                try {
                    $ret_code = self::langInstall($dest);
                } catch (Exception $e) {
                    @unlink($dest);

                    throw $e;
                }

                @unlink($dest);
                if ($ret_code == 2) {
                    self::addSuccessNotice(__('Language has been successfully upgraded'));
                } else {
                    self::addSuccessNotice(__('Language has been successfully installed.'));
                }
                $this->core->adminurl->redirect('admin.langs');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        /* DISPLAY Main page
        -------------------------------------------------------- */
        $this->open(__('Languages management'),
            self::jsLoad('js/_langs.js'),
            $this->breadcrumb(
                [
                    __('System')               => '',
                    __('Languages management') => ''
                ])
        );

        if (!empty($_GET['removed'])) {
            self::success(__('Language has been successfully deleted.'));
        }

        if (!empty($_GET['added'])) {
            self::success(($_GET['added'] == 2 ? __('Language has been successfully upgraded') : __('Language has been successfully installed.')));
        }

        echo
        '<p>' . __('Here you can install, upgrade or remove languages for your Dotclear ' .
            'installation.') . '</p>' .
        '<p>' . sprintf(__('You can change your user language in your <a href="%1$s">preferences</a> or ' .
            'change your blog\'s main language in your <a href="%2$s">blog settings</a>.'),
            $this->core->adminurl->get('admin.user.preferences'), $this->core->adminurl->get('admin.blog.pref')) . '</p>';

        echo
        '<h3>' . __('Installed languages') . '</h3>';

        $locales_content = scandir(DOTCLEAR_L10N_DIR);
        $tmp             = [];
        foreach ($locales_content as $v) {
            $c = ($v == '.' || $v == '..' || $v == 'en' || !is_dir(DOTCLEAR_L10N_DIR . '/' . $v) || !isset($iso_codes[$v]));

            if (!$c) {
                $tmp[$v] = DOTCLEAR_L10N_DIR . '/' . $v;
            }
        }
        $locales_content = $tmp;

        if (empty($locales_content)) {
            echo '<p><strong>' . __('No additional language is installed.') . '</strong></p>';
        } else {
            echo
            '<div class="table-outer clear">' .
            '<table class="plugins"><tr>' .
            '<th>' . __('Language') . '</th>' .
            '<th class="nowrap">' . __('Action') . '</th>' .
                '</tr>';

            foreach ($locales_content as $k => $v) {
                $is_deletable = $is_writable && is_writable($v);

                echo
                '<tr class="line wide">' .
                '<td class="maximal nowrap">(' . $k . ') ' .
                '<strong>' . html::escapeHTML($iso_codes[$k]) . '</strong></td>' .
                    '<td class="nowrap action">';

                if ($is_deletable) {
                    echo
                    '<form action="' . $this->core->adminurl->get('admin.langs') . '" method="post">' .
                    '<div>' .
                    $this->core->formNonce() .
                    Form::hidden(['locale_id'], html::escapeHTML($k)) .
                    '<input type="submit" class="delete" name="delete" value="' . __('Delete') . '" /> ' .
                        '</div>' .
                        '</form>';
                }

                echo '</td></tr>';
            }
            echo '</table></div>';
        }

        echo '<h3>' . __('Install or upgrade languages') . '</h3>';

        if (!$is_writable) {
            echo '<p>' . sprintf(__('You can install or remove a language by adding or ' .
                'removing the relevant directory in your %s folder.'), '<strong>locales</strong>') . '</p>';
        }

        if (!empty($dc_langs) && $is_writable) {
            $dc_langs_combo = [];
            foreach ($dc_langs as $k => $v) {
                if ($v->link && isset($iso_codes[$v->title])) {
                    $dc_langs_combo[html::escapeHTML('(' . $v->title . ') ' . $iso_codes[$v->title])] = html::escapeHTML($v->link);
                }
            }

            echo
            '<form method="post" action="' . $this->core->adminurl->get('admin.langs') . '" enctype="multipart/form-data" class="fieldset">' .
            '<h4>' . __('Available languages') . '</h4>' .
            '<p>' . sprintf(__('You can download and install a additional language directly from Dotclear.net. ' .
                'Proposed languages are based on your version: %s.'), '<strong>' . DOTCLEAR_VERSION . '</strong>') . '</p>' .
            '<p class="field"><label for="pkg_url" class="classic">' . __('Language:') . '</label> ' .
            Form::combo(['pkg_url'], $dc_langs_combo) . '</p>' .
            '<p class="field"><label for="your_pwd1" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
            Form::password(['your_pwd', 'your_pwd1'], 20, 255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password']
            ) . '</p>' .
            '<p><input type="submit" value="' . __('Install language') . '" />' .
            $this->core->formNonce() .
                '</p>' .
                '</form>';
        }

        if ($is_writable) {
            # 'Upload language pack' form
            echo
            '<form method="post" action="' . $this->core->adminurl->get('admin.langs') . '" enctype="multipart/form-data" class="fieldset">' .
            '<h4>' . __('Upload a zip file') . '</h4>' .
            '<p>' . __('You can install languages by uploading zip files.') . '</p>' .
            '<p class="field"><label for="pkg_file" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Language zip file:') . '</label> ' .
            '<input type="file" id="pkg_file" name="pkg_file" required /></p>' .
            '<p class="field"><label for="your_pwd2" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
            Form::password(['your_pwd', 'your_pwd2'], 20, 255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password']
            ) . '</p>' .
            '<p><input type="submit" name="upload_pkg" value="' . __('Upload language') . '" />' .
            $this->core->formNonce() .
                '</p>' .
                '</form>';
        }
        $this->helpBlock('core_langs');
        $this->close();
    }

    private static function langInstall($file)
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
