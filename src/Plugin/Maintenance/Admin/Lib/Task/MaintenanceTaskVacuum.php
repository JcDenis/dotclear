<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskVacuum
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

use Dotclear\Database\AbstractSchema;

class MaintenanceTaskVacuum extends MaintenanceTask
{
    protected $group = 'optimize';

    protected function init()
    {
        $this->name    = __('Optimise database');
        $this->task    = __('optimize tables');
        $this->success = __('Optimization successful.');
        $this->error   = __('Failed to optimize tables.');

        $this->description = __("After numerous delete or update operations on Dotclear's database, it gets fragmented. Optimizing will allow to defragment it. It has no incidence on your data's integrity. It is recommended to optimize before any blog export.");
    }

    public function execute()
    {
        $schema = AbstractSchema::init(dotclear()->con());

        foreach ($schema->getTables() as $table) {
            if (str_starts_with($table, dotclear()->prefix)) {
                dotclear()->con()->vacuum($table);
            }
        }

        return true;
    }
}
