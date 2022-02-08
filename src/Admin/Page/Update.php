<?php
/**
 * @class Dotclear\Admin\Page\Update
 * @brief Dotclear admin update page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use function Dotclear\core;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Update as CoreUpdate;

use Dotclear\Admin\Page;

use Dotclear\Html\Html;
use Dotclear\Html\Form;
use Dotclear\File\Files;
use Dotclear\File\Zip\Unzip;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Update extends Page
{
    private $updater;
    private $content     = '';
    private $step        = '';
    private $new_version = '';
    private $archives    = [];

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

        if (!defined('DOTCLEAR_BACKUP_DIR')) {
            define('DOTCLEAR_BACKUP_DIR', DOTCLEAR_ROOT_DIR);
        } else {
            if (!is_dir(DOTCLEAR_BACKUP_DIR)) {
                $this->content = sprintf('<h3>%s</h3><p>%s</p>',  __('Precheck update error'), __('Backup directory does not exist'));
                return true;
            }
        }

        if (!is_readable(DOTCLEAR_DIGESTS_DIR)) {
            $this->content =  sprintf('<h3>%s</h3><p>%s</p>',  __('Precheck update error'), __('Access denied'));
            return true;
        }

        $this->updater     = new CoreUpdate(DOTCLEAR_CORE_UPDATE_URL, 'dotclear', DOTCLEAR_CORE_UPDATE_CHANNEL, DOTCLEAR_CACHE_DIR . '/versions');
        $this->new_version = $this->updater->check(DOTCLEAR_CORE_VERSION, !empty($_GET['nocache']));
        $zip_file          = $this->new_version ? DOTCLEAR_BACKUP_DIR . '/' . basename($this->updater->getFileURL()) : '';

        # Hide "update me" message
        if (!empty($_GET['hide_msg'])) {
            $this->updater->setNotify(false);
            core()->adminurl->redirect('admin.home');
        }

        $this->step = $_GET['step'] ?? '';
        $this->step = in_array($this->step, ['check', 'download', 'backup', 'unzip']) ? $this->step : '';

        $default_tab = !empty($_GET['tab']) ? Html::escapeHTML($_GET['tab']) : 'update';
        if (!empty($_POST['backup_file'])) {
            $default_tab = 'files';
        }

        foreach (Files::scanDir(DOTCLEAR_BACKUP_DIR) as $v) {
            if (preg_match('/backup-([0-9A-Za-z\.-]+).zip/', $v)) {
                $this->archives[] = $v;
            }
        }
        if (!empty($this->archives)) {
            usort($this->archives, 'version_compare');
        } else {
            $default_tab = 'update';
        }

        if (!$this->step) {
            $this->setPageHead(
                self::jsPageTabs($default_tab) .
                self::jsLoad('js/_update.js')
            );
        }

        # Revert or delete backup file
        if (!empty($_POST['backup_file']) && in_array($_POST['backup_file'], $this->archives)) {
            $b_file = $_POST['backup_file'];

            try {
                if (!empty($_POST['b_del'])) {
                    if (!@unlink(DOTCLEAR_BACKUP_DIR . '/' . $b_file)) {
                        throw new AdminException(sprintf(__('Unable to delete file %s'), Html::escapeHTML($b_file)));
                    }
                    core()->adminurl->redirect('admin.update', ['tab' => 'files']);
                }

                if (!empty($_POST['b_revert'])) {
                    $zip = new Unzip(DOTCLEAR_BACKUP_DIR . '/' . $b_file);
                    $zip->unzipAll(DOTCLEAR_BACKUP_DIR . '/');
                    @unlink(DOTCLEAR_BACKUP_DIR . '/' . $b_file);
                    core()->adminurl->redirect('admin.update', ['tab' => 'files']);
                }
            } catch (Exception $e) {
                core()->error($e->getMessage());
            }
        }

        # Upgrade process
        if ($this->new_version && $this->step) {
            try {
                $this->updater->setForcedFiles('src/digests');

                switch ($this->step) {
                    case 'check':
                        $this->updater->checkIntegrity(DOTCLEAR_ROOT_DIR . '/src/digests', DOTCLEAR_ROOT_DIR);
                        core()->adminurl->redirect('admin.update', ['step' => 'download']);

                        break;
                    case 'download':
                        $this->updater->download($zip_file);
                        if (!$this->updater->checkDownload($zip_file)) {
                            throw new AdminException(
                                sprintf(__('Downloaded Dotclear archive seems to be corrupted. ' .
                                    'Try <a %s>download it</a> again.'), 'href="' . core()->adminurl->get('admin.update', ['step' => 'download']) .'"') .
                                ' ' .
                                __('If this problem persists try to ' .
                                    '<a href="https://dotclear.org/download">update manually</a>.')
                            );
                        }
                        core()->adminurl->redirect('admin.update', ['step' => 'backup']);

                        break;
                    case 'backup':
                        $this->updater->backup(
                            $zip_file,
                            'dotclear/src/digests',
                            DOTCLEAR_ROOT_DIR,
                            DOTCLEAR_ROOT_DIR . '/src/digests',
                            DOTCLEAR_BACKUP_DIR . '/backup-' . DOTCLEAR_CORE_VERSION . '.zip'
                        );
                        core()->adminurl->redirect('admin.update', ['step' => 'unzip']);

                        break;
                    case 'unzip':
                        $this->updater->performUpgrade(
                            $zip_file,
                            'dotclear/src/digests',
                            'dotclear',
                            DOTCLEAR_ROOT_DIR,
                            DOTCLEAR_ROOT_DIR . '/src/digests'
                        );

                        break;
                }
            } catch (Exception $e) {
                $msg = $e->getMessage();

                if ($e->getCode() == dcUpdate::ERR_FILES_CHANGED) {
                    $msg = __('The following files of your Dotclear installation ' .
                        'have been modified so we won\'t try to update your installation. ' .
                        'Please try to <a href="https://dotclear.org/download">update manually</a>.');
                } elseif ($e->getCode() == dcUpdate::ERR_FILES_UNREADABLE) {
                    $msg = sprintf(
                        __('The following files of your Dotclear installation are not readable. ' .
                        'Please fix this or try to make a backup file named %s manually.'),
                        '<strong>backup-' . DOTCLEAR_CORE_VERSION . '.zip</strong>'
                    );
                } elseif ($e->getCode() == dcUpdate::ERR_FILES_UNWRITALBE) {
                    $msg = __('The following files of your Dotclear installation cannot be written. ' .
                        'Please fix this or try to <a href="https://dotclear.org/download">update manually</a>.');
                }

                if (isset($e->bad_files)) {
                    $msg .= '<ul><li><strong>' .
                    implode('</strong></li><li><strong>', $e->bad_files) .
                        '</strong></li></ul>';
                }

                core()->error($msg);

                core()->behaviors->call('adminDCUpdateException', $e);
            }
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($this->content)) {
            echo $this->content;

            return;
        }

        if (!core()->error()->flag()) {
            if (!empty($_GET['nocache'])) {
                core()->notices->success(__('Manual checking of update done successfully.'));
            }
        }

        if (!$this->step) {
            echo '<div class="multi-part" id="update" title="' . __('Dotclear update') . '">';

            // Warning about PHP version if necessary
            if (version_compare(phpversion(), DOTCLEAR_PHP_NEXT_REQUIRED, '<')) {
                echo '<p class="info more-info">' .
                sprintf(
                    __('The next versions of Dotclear will not support PHP version < %s, your\'s is currently %s'),
                    DOTCLEAR_PHP_NEXT_REQUIRED,
                    phpversion()
                ) .
                '</p>';
            }
            if (empty($this->new_version)) {
                echo '<p><strong>' . __('No newer Dotclear version available.') . '</strong></p>' .
                '<form action="' . core()->adminurl->get('admin.update', [], '&') . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update Dotclear') . '" /></p>' .
                    '</form>';
            } else {
                $version_info = $this->updater->getInfoURL();

                echo
                '<p class="static-msg">' . sprintf(__('Dotclear %s is available.'), $this->new_version) .
                    ($version_info ? ' <a href="' . $version_info . '" class="outgoing" title="' . __('Information about this version') . '">(' .
                    __('Information about this version') . ')&nbsp;<img src="?df=images/outgoing-link.svg" alt=""/></a>' : '') .
                    '</p>';
                if (version_compare(phpversion(), $this->updater->getPHPVersion()) < 0) {
                    echo
                    '<p class="warning-msg">' . sprintf(__('PHP version is %s (%s or earlier needed).'), phpversion(), $this->updater->getPHPVersion()) . '</p>';
                } else {
                    echo
                    '<p>' . __('To upgrade your Dotclear installation simply click on the following button. ' .
                        'A backup file of your current installation will be created in your root directory.') . '</p>' .
                    '<form action="' . core()->adminurl->get('admin.update', [], '&')  . '" method="get">' .
                    '<p><input type="hidden" name="step" value="check" />' .
                    '<input type="submit" value="' . __('Update Dotclear') . '" /></p>' .
                        '</form>';
                }
            }
            echo '</div>';

            if (!empty($this->archives)) {
                echo '<div class="multi-part" id="files" title="' . __('Manage backup files') . '">';

                echo
                '<h3>' . __('Update backup files') . '</h3>' .
                '<p>' . __('The following files are backups of previously updates. ' .
                    'You can revert your previous installation or delete theses files.') . '</p>';

                echo '<form action="' . core()->adminurl->get('admin.update', [], '&')  . '" method="post">';
                foreach ($this->archives as $v) {
                    echo
                    '<p><label class="classic">' . Form::radio(['backup_file'], Html::escapeHTML($v)) . ' ' .
                    Html::escapeHTML($v) . '</label></p>';
                }

                echo
                '<p><strong>' . __('Please note that reverting your Dotclear version may have some ' .
                    'unwanted side-effects. Consider reverting only if you experience strong issues with this new version.') . '</strong> ' .
                sprintf(__('You should not revert to version prior to last one (%s).'), end($this->archives)) .
                '</p>' .
                '<p><input type="submit" class="delete" name="b_del" value="' . __('Delete selected file') . '" /> ' .
                '<input type="submit" name="b_revert" value="' . __('Revert to selected file') . '" />' .
                core()->formNonce() . '</p>' .
                    '</form>';

                echo '</div>';
            }
        } elseif ($this->step == 'unzip' && !core()->error()->flag()) {
            echo
            '<p class="message">' .
            __("Congratulations, you're one click away from the end of the update.") .
            ' <strong><a href="' . core()->adminurl->get('admin.home', ['logout' => 1], '&') . '" class="button submit">' . __('Finish the update.') . '</a></strong>' .
                '</p>';
        }
    }
}
