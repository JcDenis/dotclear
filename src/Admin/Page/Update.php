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

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;
use Dotclear\Core\Update as CoreUpdate;

use Dotclear\Admin\Page;

use Dotclear\Html\Html;
use Dotclear\Utils\Files;
use Dotclear\Utils\Form;
use Dotclear\Network\Http;
use Dotclear\File\Zip\Unzip;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Update extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->checkSuper();

        if (!defined('DOTCLEAR_BACKUP_DIR')) {
            define('DOTCLEAR_BACKUP_DIR', DOTCLEAR_ROOT_DIR);
        } else {
            // Check backup path existence
            if (!is_dir(DOTCLEAR_BACKUP_DIR)) {
                $this->open(
                    __('Dotclear update'),
                    '',
                    $this->breadcrumb(
                        [
                            __('System')          => '',
                            __('Dotclear update') => ''
                        ]
                    )
                );
                echo
                '<h3>' . __('Precheck update error') . '</h3>' .
                '<p>' . __('Backup directory does not exist') . '</p>';
                $this->close();
                exit;
            }
        }

        if (!is_readable(DOTCLEAR_DIGESTS_DIR)) {
            $this->open(
                __('Dotclear update'),
                '',
                $this->breadcrumb(
                    [
                        __('System')          => '',
                        __('Dotclear update') => ''
                    ]
                )
            );
            echo
            '<h3>' . __('Precheck update error') . '</h3>' .
            '<p>' . __('Access denied') . '</p>';
            $this->close();
            exit;
        }

        $updater      = new CoreUpdate(DOTCLEAR_UPDATE_URL, 'dotclear', DOTCLEAR_UPDATE_VERSION, DOTCLEAR_CACHE_DIR . '/versions');
        $new_v        = $updater->check(DOTCLEAR_VERSION, !empty($_GET['nocache']));
        $zip_file     = $new_v ? DOTCLEAR_BACKUP_DIR . '/' . basename($updater->getFileURL()) : '';
        $version_info = $new_v ? $updater->getInfoURL() : '';

        # Hide "update me" message
        if (!empty($_GET['hide_msg'])) {
            $updater->setNotify(false);
            Http::redirect('index.php');
        }

        $p_url = $this->core->adminurl->get('admin.update');

        $step = $_GET['step'] ?? '';
        $step = in_array($step, ['check', 'download', 'backup', 'unzip']) ? $step : '';

        $default_tab = !empty($_GET['tab']) ? Html::escapeHTML($_GET['tab']) : 'update';
        if (!empty($_POST['backup_file'])) {
            $default_tab = 'files';
        }

        $archives = [];
        foreach (Files::scanDir(DOTCLEAR_BACKUP_DIR) as $v) {
            if (preg_match('/backup-([0-9A-Za-z\.-]+).zip/', $v)) {
                $archives[] = $v;
            }
        }
        if (!empty($archives)) {
            usort($archives, 'version_compare');
        } else {
            $default_tab = 'update';
        }

        # Revert or delete backup file
        if (!empty($_POST['backup_file']) && in_array($_POST['backup_file'], $archives)) {
            $b_file = $_POST['backup_file'];

            try {
                if (!empty($_POST['b_del'])) {
                    if (!@unlink(DOTCLEAR_BACKUP_DIR . '/' . $b_file)) {
                        throw new AdminException(sprintf(__('Unable to delete file %s'), Html::escapeHTML($b_file)));
                    }
                    Http::redirect($p_url . '&tab=files');
                }

                if (!empty($_POST['b_revert'])) {
                    $zip = new Unzip(DOTCLEAR_BACKUP_DIR . '/' . $b_file);
                    $zip->unzipAll(DOTCLEAR_BACKUP_DIR . '/');
                    @unlink(DOTCLEAR_BACKUP_DIR . '/' . $b_file);
                    Http::redirect($p_url . '&tab=files');
                }
            } catch (Exception $e) {
                $core->error->add($e->getMessage());
            }
        }

        # Upgrade process
        if ($new_v && $step) {
            try {
                $updater->setForcedFiles('src/digests');

                switch ($step) {
                    case 'check':
                        $updater->checkIntegrity(DOTCLEAR_ROOT_DIR . '/src/digests', DOTCLEAR_ROOT_DIR);
                        Http::redirect($p_url . '&step=download');

                        break;
                    case 'download':
                        $updater->download($zip_file);
                        if (!$updater->checkDownload($zip_file)) {
                            throw new AdminException(
                                sprintf(__('Downloaded Dotclear archive seems to be corrupted. ' .
                                    'Try <a %s>download it</a> again.'), 'href="' . $p_url . '&step=download"') .
                                ' ' .
                                __('If this problem persists try to ' .
                                    '<a href="https://dotclear.org/download">update manually</a>.')
                            );
                        }
                        Http::redirect($p_url . '&step=backup');

                        break;
                    case 'backup':
                        $updater->backup(
                            $zip_file,
                            'dotclear/src/digests',
                            DOTCLEAR_ROOT_DIR,
                            DOTCLEAR_ROOT_DIR . '/src/digests',
                            DOTCLEAR_BACKUP_DIR . '/backup-' . DOTCLEAR_VERSION . '.zip'
                        );
                        Http::redirect($p_url . '&step=unzip');

                        break;
                    case 'unzip':
                        $updater->performUpgrade(
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
                        '<strong>backup-' . DOTCLEAR_VERSION . '.zip</strong>'
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

                $core->error->add($msg);

                $core->callBehavior('adminDCUpdateException', $e);
            }
        }

        /* DISPLAY Main page
        -------------------------------------------------------- */
        $this->open(
            __('Dotclear update'),
            (!$step ?
                self::jsPageTabs($default_tab) .
                self::jsLoad('js/_update.js')
                : ''),
            $this->breadcrumb(
                [
                    __('System')          => '',
                    __('Dotclear update') => ''
                ]
            )
        );

        if (!$core->error->flag()) {
            if (!empty($_GET['nocache'])) {
                self::success(__('Manual checking of update done successfully.'));
            }
        }

        if (!$step) {
            echo '<div class="multi-part" id="update" title="' . __('Dotclear update') . '">';

            // Warning about PHP version if necessary
            if (version_compare(phpversion(), DOTCLEAR_NEXT_REQUIRED_PHP, '<')) {
                echo '<p class="info more-info">' .
                sprintf(
                    __('The next versions of Dotclear will not support PHP version < %s, your\'s is currently %s'),
                    DOTCLEAR_NEXT_REQUIRED_PHP,
                    phpversion()
                ) .
                '</p>';
            }
            if (empty($new_v)) {
                echo '<p><strong>' . __('No newer Dotclear version available.') . '</strong></p>' .
                '<form action="' . $p_url . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update Dotclear') . '" /></p>' .
                    '</form>';
            } else {
                echo
                '<p class="static-msg">' . sprintf(__('Dotclear %s is available.'), $new_v) .
                    ($version_info ? ' <a href="' . $version_info . '" class="outgoing" title="' . __('Information about this version') . '">(' .
                    __('Information about this version') . ')&nbsp;<img src="?df=images/outgoing-link.svg" alt=""/></a>' : '') .
                    '</p>';
                if (version_compare(phpversion(), $updater->getPHPVersion()) < 0) {
                    echo
                    '<p class="warning-msg">' . sprintf(__('PHP version is %s (%s or earlier needed).'), phpversion(), $updater->getPHPVersion()) . '</p>';
                } else {
                    echo
                    '<p>' . __('To upgrade your Dotclear installation simply click on the following button. ' .
                        'A backup file of your current installation will be created in your root directory.') . '</p>' .
                    '<form action="' . $p_url . '" method="get">' .
                    '<p><input type="hidden" name="step" value="check" />' .
                    '<input type="submit" value="' . __('Update Dotclear') . '" /></p>' .
                        '</form>';
                }
            }
            echo '</div>';

            if (!empty($archives)) {
                echo '<div class="multi-part" id="files" title="' . __('Manage backup files') . '">';

                echo
                '<h3>' . __('Update backup files') . '</h3>' .
                '<p>' . __('The following files are backups of previously updates. ' .
                    'You can revert your previous installation or delete theses files.') . '</p>';

                echo '<form action="' . $p_url . '" method="post">';
                foreach ($archives as $v) {
                    echo
                    '<p><label class="classic">' . Form::radio(['backup_file'], Html::escapeHTML($v)) . ' ' .
                    Html::escapeHTML($v) . '</label></p>';
                }

                echo
                '<p><strong>' . __('Please note that reverting your Dotclear version may have some ' .
                    'unwanted side-effects. Consider reverting only if you experience strong issues with this new version.') . '</strong> ' .
                sprintf(__('You should not revert to version prior to last one (%s).'), end($archives)) .
                '</p>' .
                '<p><input type="submit" class="delete" name="b_del" value="' . __('Delete selected file') . '" /> ' .
                '<input type="submit" name="b_revert" value="' . __('Revert to selected file') . '" />' .
                $core->formNonce() . '</p>' .
                    '</form>';

                echo '</div>';
            }
        } elseif ($step == 'unzip' && !$core->error->flag()) {
            echo
            '<p class="message">' .
            __("Congratulations, you're one click away from the end of the update.") .
            ' <strong><a href="?handler=admin.home&logout=1" class="button submit">' . __('Finish the update.') . '</a></strong>' .
                '</p>';
        }

        $this->helpBlock('core_update');
        $this->close();
    }
}
