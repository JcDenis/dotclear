<?php
/**
 * @class Dotclear\Admin\Page\Page\Update
 * @brief Dotclear admin update page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page\Page;

use Dotclear\Admin\Page\Page;
use Dotclear\Admin\Page\Service\Updater;
use Dotclear\Exception\AdminException;
use Dotclear\File\Files;
use Dotclear\File\Zip\Unzip;
use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Update extends Page
{
    use \Dotclear\Utils\ErrorTrait;

    private $upd_updater;
    private $upd_step        = '';
    private $upd_new_version = '';
    private $upd_archives    = [];

    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageTitle(__('Dotclear update'))
            ->setPageHelp('core_update')
            ->setPageBreadcrumb([
                __('System')          => '',
                __('Dotclear update') => ''
            ])
        ;

        if (!is_dir(dotclear()->config()->backup_dir)) {
            $this->error()->add(__('Backup directory does not exist'));
            return true;
        }

        if (!is_readable(dotclear()->config()->digests_dir)) {
            $this->error()->add(__('Access denied'));
            return true;
        }

        $this->upd_updater     = new Updater(dotclear()->config()->core_update_url, 'dotclear', dotclear()->config()->core_update_channel, dotclear()->config()->cache_dir . '/versions');
        $this->upd_new_version = $this->upd_updater->check(dotclear()->config()->core_version, !empty($_GET['nocache']));
        $zip_file              = $this->upd_new_version ? dotclear()->config()->backup_dir . '/' . basename($this->upd_updater->getFileURL()) : '';

        # Hide "update me" message
        if (!empty($_GET['hide_msg'])) {
            $this->upd_updater->setNotify(false);
            dotclear()->adminurl()->redirect('admin.home');
        }

        $this->upd_step = $_GET['step'] ?? '';
        $this->upd_step = in_array($this->upd_step, ['check', 'download', 'backup', 'unzip']) ? $this->upd_step : '';

        $default_tab = !empty($_GET['tab']) ? Html::escapeHTML($_GET['tab']) : 'update';
        if (!empty($_POST['backup_file'])) {
            $default_tab = 'files';
        }

        foreach (Files::scanDir(dotclear()->config()->backup_dir) as $v) {
            if (preg_match('/backup-([0-9A-Za-z\.-]+).zip/', $v)) {
                $this->upd_archives[] = $v;
            }
        }
        if (!empty($this->upd_archives)) {
            usort($this->upd_archives, 'version_compare');
        } else {
            $default_tab = 'update';
        }

        if (!$this->upd_step) {
            $this->setPageHead(
                self::jsPageTabs($default_tab) .
                dotclear()->filer()->load('_update.js')
            );
        }

        # Revert or delete backup file
        if (!empty($_POST['backup_file']) && in_array($_POST['backup_file'], $this->upd_archives)) {
            $b_file = $_POST['backup_file'];

            try {
                if (!empty($_POST['b_del'])) {
                    if (!@unlink(dotclear()->config()->backup_dir . '/' . $b_file)) {
                        throw new AdminException(sprintf(__('Unable to delete file %s'), Html::escapeHTML($b_file)));
                    }
                    dotclear()->adminurl()->redirect('admin.update', ['tab' => 'files']);
                }

                if (!empty($_POST['b_revert'])) {
                    $zip = new Unzip(dotclear()->config()->backup_dir . '/' . $b_file);
                    $zip->unzipAll(dotclear()->config()->backup_dir . '/');
                    @unlink(dotclear()->config()->backup_dir . '/' . $b_file);
                    dotclear()->adminurl()->redirect('admin.update', ['tab' => 'files']);
                }
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Upgrade process
        if ($this->upd_new_version && $this->upd_step) {
            try {
                $this->upd_updater->setForcedFiles(dotclear()->config()->digests_dir);

                switch ($this->upd_step) {
                    case 'check':
                        $this->upd_updater->checkIntegrity(dotclear()->config()->digests_dir, dotclear()->config()->root_dir);
                        dotclear()->adminurl()->redirect('admin.update', ['step' => 'download']);

                        break;
                    case 'download':
                        $this->upd_updater->download($zip_file);
                        if (!$this->upd_updater->checkDownload($zip_file)) {
                            throw new AdminException(
                                sprintf(__('Downloaded Dotclear archive seems to be corrupted. ' .
                                    'Try <a %s>download it</a> again.'), 'href="' . dotclear()->adminurl()->get('admin.update', ['step' => 'download']) .'"') .
                                ' ' .
                                __('If this problem persists try to ' .
                                    '<a href="https://dotclear.org/download">update manually</a>.')
                            );
                        }
                        dotclear()->adminurl()->redirect('admin.update', ['step' => 'backup']);

                        break;
                    case 'backup':
                        $this->upd_updater->backup(
                            $zip_file,
                            'dotclear/digests',
                            dotclear()->config()->root_dir,
                            dotclear()->config()->digests_dir,
                            dotclear()->config()->backup_dir . '/backup-' . dotclear()->config()->core_version . '.zip'
                        );
                        dotclear()->adminurl()->redirect('admin.update', ['step' => 'unzip']);

                        break;
                    case 'unzip':
                        $this->upd_updater->performUpgrade(
                            $zip_file,
                            'dotclear/digests',
                            'dotclear',
                            dotclear()->config()->root_dir,
                            dotclear()->config()->digests_dir
                        );

                        break;
                }
            } catch (\Exception $e) {
                $msg = $e->getMessage();

                if ($e->getCode() == Updater::ERR_FILES_CHANGED) {
                    $msg = __('The following files of your Dotclear installation ' .
                        'have been modified so we won\'t try to update your installation. ' .
                        'Please try to <a href="https://dotclear.org/download">update manually</a>.');
                } elseif ($e->getCode() == Updater::ERR_FILES_UNREADABLE) {
                    $msg = sprintf(
                        __('The following files of your Dotclear installation are not readable. ' .
                        'Please fix this or try to make a backup file named %s manually.'),
                        '<strong>backup-' . dotclear()->config()->core_version . '.zip</strong>'
                    );
                } elseif ($e->getCode() == Updater::ERR_FILES_UNWRITALBE) {
                    $msg = __('The following files of your Dotclear installation cannot be written. ' .
                        'Please fix this or try to <a href="https://dotclear.org/download">update manually</a>.');
                }

                if (isset($e->bad_files)) {
                    $msg .= '<ul><li><strong>' .
                    implode('</strong></li><li><strong>', $e->bad_files) .
                        '</strong></li></ul>';
                }

                dotclear()->error()->add($msg);

                dotclear()->behavior()->call('adminDCUpdateException', $e);
            }
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if ($this->error()->flag()) {
            echo '<h3>' . __('Precheck update error') . '</h3>' . $this->error()->toHTML();

            return;
        }

        if (!dotclear()->error()->flag()) {
            if (!empty($_GET['nocache'])) {
                dotclear()->notice()->success(__('Manual checking of update done successfully.'));
            }
        }

        if (!$this->upd_step) {
            echo '<div class="multi-part" id="update" title="' . __('Dotclear update') . '">';

            // Warning about PHP version if necessary
            if (version_compare(phpversion(), dotclear()->config()->php_next_required, '<')) {
                echo '<p class="info more-info">' .
                sprintf(
                    __('The next versions of Dotclear will not support PHP version < %s, your\'s is currently %s'),
                    dotclear()->config()->php_next_required,
                    phpversion()
                ) .
                '</p>';
            }
            if (empty($this->upd_new_version)) {
                echo '<p><strong>' . __('No newer Dotclear version available.') . '</strong></p>' .
                '<form action="' . dotclear()->adminurl()->get('admin.update', [], '&') . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update Dotclear') . '" /></p>' .
                    '</form>';
            } else {
                $version_info = $this->upd_updater->getInfoURL();

                echo
                '<p class="static-msg">' . sprintf(__('Dotclear %s is available.'), $this->upd_new_version) .
                    ($version_info ? ' <a href="' . $version_info . '" class="outgoing" title="' . __('Information about this version') . '">(' .
                    __('Information about this version') . ')&nbsp;<img src="?df=images/outgoing-link.svg" alt=""/></a>' : '') .
                    '</p>';
                if (version_compare(phpversion(), $this->upd_updater->getPHPVersion()) < 0) {
                    echo
                    '<p class="warning-msg">' . sprintf(__('PHP version is %s (%s or earlier needed).'), phpversion(), $this->upd_updater->getPHPVersion()) . '</p>';
                } else {
                    echo
                    '<p>' . __('To upgrade your Dotclear installation simply click on the following button. ' .
                        'A backup file of your current installation will be created in your root directory.') . '</p>' .
                    '<form action="' . dotclear()->adminurl()->get('admin.update', [], '&')  . '" method="get">' .
                    '<p><input type="hidden" name="step" value="check" />' .
                    '<input type="submit" value="' . __('Update Dotclear') . '" /></p>' .
                        '</form>';
                }
            }
            echo '</div>';

            if (!empty($this->upd_archives)) {
                echo '<div class="multi-part" id="files" title="' . __('Manage backup files') . '">';

                echo
                '<h3>' . __('Update backup files') . '</h3>' .
                '<p>' . __('The following files are backups of previously updates. ' .
                    'You can revert your previous installation or delete theses files.') . '</p>';

                echo '<form action="' . dotclear()->adminurl()->get('admin.update', [], '&')  . '" method="post">';
                foreach ($this->upd_archives as $v) {
                    echo
                    '<p><label class="classic">' . Form::radio(['backup_file'], Html::escapeHTML($v)) . ' ' .
                    Html::escapeHTML($v) . '</label></p>';
                }

                echo
                '<p><strong>' . __('Please note that reverting your Dotclear version may have some ' .
                    'unwanted side-effects. Consider reverting only if you experience strong issues with this new version.') . '</strong> ' .
                sprintf(__('You should not revert to version prior to last one (%s).'), end($this->upd_archives)) .
                '</p>' .
                '<p><input type="submit" class="delete" name="b_del" value="' . __('Delete selected file') . '" /> ' .
                '<input type="submit" name="b_revert" value="' . __('Revert to selected file') . '" />' .
                dotclear()->nonce()->form() . '</p>' .
                    '</form>';

                echo '</div>';
            }
        } elseif ($this->upd_step == 'unzip' && !dotclear()->error()->flag()) {
            echo
            '<p class="message">' .
            __("Congratulations, you're one click away from the end of the update.") .
            ' <strong><a href="' . dotclear()->adminurl()->get('admin.home', ['logout' => 1], '&') . '" class="button submit">' . __('Finish the update.') . '</a></strong>' .
                '</p>';
        }
    }
}
