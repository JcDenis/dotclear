<?php
/**
 * @class Dotclear\Plugin\ImportExport\Lib\Task\ExportFull
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Lib\Task;

use Dotclear\Exception\ModuleException;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Plugin\ImportExport\Lib\Task\ExportFlat;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class ExportFull extends MaintenanceTask
{
    protected $tab   = 'backup';
    protected $group = 'zipfull';

    protected $export_name;
    protected $export_type;

    protected function init()
    {
        $this->name = __('Database export');
        $this->task = __('Download database of all blogs');

        $this->export_name = 'dotclear-backup.txt';
        $this->export_type = 'export_all';
    }

    public function execute()
    {
        global $core;

        // Create zip file
        if (!empty($_POST['file_name'])) {
            if (empty($_POST['your_pwd']) || !dotclear()->user()->checkPassword($_POST['your_pwd'])) {
                $this->error = __('Password verification failed');

                return false;
            }

            // This process make an http redirect
            $ie = new ExportFlat();
            $ie->setURL($this->id);
            $ie->process($this->export_type);
        }
        // Go to step and show form
        else {
            return 1;
        }
    }

    public function step()
    {
        // Download zip file
        if (isset($_SESSION['export_file']) && file_exists($_SESSION['export_file'])) {
            // Log task execution here as we sent file and stop script
            $this->log();

            // This process send file by http and stop script
            $ie = new ExportFlat();
            $ie->setURL($this->id);
            $ie->process('ok');
        } else {
            return
            '<p><label for="file_name">' . __('File name:') . '</label>' .
            Form::field('file_name', 50, 255, date('Y-m-d-H-i-') . $this->export_name) .
            '</p>' .
            '<p><label for="file_zip" class="classic">' .
            Form::checkbox('file_zip', 1) . ' ' .
            __('Compress file') . '</label>' .
            '</p>' .
            '<p><label for="your_pwd" class="required">' .
            '<abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label>' .
            Form::password(
                'your_pwd',
                20,
                255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password',
                ]
            ) . '</p>';
        }
    }
}
