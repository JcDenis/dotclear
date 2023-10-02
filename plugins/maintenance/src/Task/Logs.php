<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\maintenance\Task;

use Dotclear\App;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Plugin\maintenance\MaintenanceTask;

/**
 * @brief   The logs maintenance task.
 * @ingroup maintenance
 */
class Logs extends MaintenanceTask
{
    /**
     * Task ID (class name).
     *
     * @var     null|string     $id
     */
    protected $id = 'dcMaintenanceLogs';

    /**
     * Keep maintenance logs?
     *
     * @var     bool    $keep_maintenance_logs
     */
    public static $keep_maintenance_logs = true;

    /**
     * Task group container.
     *
     * @var     string  $group
     */
    protected $group = 'purge';

    /**
     * Initialize task object.
     */
    protected function init(): void
    {
        $this->task    = __('Delete all logs');
        $this->success = __('Logs deleted.');
        $this->error   = __('Failed to delete logs.');

        $this->description = __('Logs record all activity and connection to your blog history. Unless you need to keep this history, consider deleting these logs from time to time.');
    }

    public function execute()
    {
        if (static::$keep_maintenance_logs) {
            $sql = new DeleteStatement();
            $sql
                ->from(App::con()->prefix() . App::log()::LOG_TABLE_NAME)
                ->where('log_table <> ' . $sql->quote('maintenance'))
                ->delete();
        } else {
            App::log()->delAllLogs();
        }

        return true;
    }
}
