<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

// Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskCache
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * Cache maintenance Task.
 *
 * @ingroup  Plugin Maintenance Task
 */
class MaintenanceTaskCache extends MaintenanceTask
{
    protected $group = 'purge';

    protected function init(): void
    {
        $this->task    = __('Empty templates cache directory');
        $this->success = __('Templates cache directory emptied.');
        $this->error   = __('Failed to empty templates cache directory.');

        $this->description = __("It may be useful to empty this cache when modifying a theme's .html or .css files (or when updating a theme or plugin). Notice : with some hosters, the templates cache cannot be emptied with this plugin. You may then have to delete the directory <strong>/cbtpl/</strong> directly on the server with your FTP software.");
    }

    public function execute(): int|bool
    {
        dotclear()->emptyTemplatesCache();

        return true;
    }
}
