<?php
/**
 * @class Dotclear\Plugin\Maintenance\Lib\Task\MaintenanceTaskLogs
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Lib\Task;

use Dotclear\Plugin\Maintenance\Lib\MaintenanceTask;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class MaintenanceTaskLogs extends MaintenanceTask
{
    public static $keep_maintenance_logs = true;

    protected $group = 'purge';

    protected function init()
    {
        $this->task    = __('Delete all logs');
        $this->success = __('Logs deleted.');
        $this->error   = __('Failed to delete logs.');

        $this->description = __('Logs record all activity and connection to your blog history. Unless you need to keep this history, consider deleting these logs from time to time.');
    }

    public function execute()
    {
        if (static::$keep_maintenance_logs) {
            dotclear()->con->execute(
                'DELETE FROM ' . dotclear()->prefix . 'log ' .
                "WHERE log_table <> 'maintenance' "
            );
        } else {
            dotclear()->log()->delete(null, true);
        }

        return true;
    }
}
