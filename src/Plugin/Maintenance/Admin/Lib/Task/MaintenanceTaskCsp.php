<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskCSP
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

class MaintenanceTaskCSP extends MaintenanceTask
{
    protected $group = 'purge';

    protected function init()
    {
        $this->task    = __('Delete the Content-Security-Policy report file');
        $this->success = __('Content-Security-Policy report file has been deleted.');
        $this->error   = __('Failed to delete the Content-Security-Policy report file.');

        $this->description = __('Remove the Content-Security-Policy report file.');
    }

    public function execute()
    {
        $csp_file = path::real(dotclear()->config()->var_dir) . '/csp/csp_report.json';
        if (file_exists($csp_file)) {
            unlink($csp_file);
        }

        return true;
    }
}