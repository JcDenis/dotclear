<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

// Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskVacuum
use Dotclear\App;
use Dotclear\Database\AbstractSchema;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * Database vacuum maintenance Task.
 *
 * @ingroup  Plugin Maintenance Task
 */
class MaintenanceTaskVacuum extends MaintenanceTask
{
    protected $group = 'optimize';

    protected function init(): void
    {
        $this->name    = __('Optimise database');
        $this->task    = __('optimize tables');
        $this->success = __('Optimization successful.');
        $this->error   = __('Failed to optimize tables.');

        $this->description = __("After numerous delete or update operations on Dotclear's database, it gets fragmented. Optimizing will allow to defragment it. It has no incidence on your data's integrity. It is recommended to optimize before any blog export.");
    }

    public function execute(): int|bool
    {
        $schema = AbstractSchema::init(App::core()->con());

        foreach ($schema->getTables() as $table) {
            if (str_starts_with($table, App::core()->prefix)) {
                App::core()->con()->vacuum($table);
            }
        }

        return true;
    }
}
