<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

// Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskLogs
use Dotclear\App;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * Logs maintenance Task.
 *
 * @ingroup  Plugin Maintenance Task
 */
class MaintenanceTaskLogs extends MaintenanceTask
{
    public static $keep_maintenance_logs = true;

    protected $group = 'purge';

    protected function init(): void
    {
        $this->task    = __('Delete all logs');
        $this->success = __('Logs deleted.');
        $this->error   = __('Failed to delete logs.');

        $this->description = __('Logs record all activity and connection to your blog history. Unless you need to keep this history, consider deleting these logs from time to time.');
    }

    public function execute(): int|bool
    {
        if (static::$keep_maintenance_logs) {
            $sql = new DeleteStatement();
            $sql->from(App::core()->getPrefix() . 'log');
            $sql->where("log_table <> 'maintenance'");

            $sql->delete();
        } else {
            App::core()->log()->emptyLogTable();
        }

        return true;
    }
}
