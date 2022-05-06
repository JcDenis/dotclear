<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

// Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskRepo
use Dotclear\App;
use DOtclear\Helper\File\Files;
use DOtclear\Helper\File\Path;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * Cache maintenance Task.
 *
 * @ingroup  Plugin Maintenance Task
 */
class MaintenanceTaskRepo extends MaintenanceTask
{
    protected $group = 'purge';

    protected function init(): void
    {
        $this->task    = __('Empty repositories cache directory');
        $this->success = __('Repositories cache directory emptied.');
        $this->error   = __('Failed to empty repositories cache directory.');

        $this->description = __('It may be useful to empty this cache when ... Notice : with some hosters, the repositories cache cannot be emptied with this plugin. You may then have to delete the directory <strong>/cbrepo/</strong> directly on the server with your FTP software.');
    }

    public function execute(): int|bool
    {
        if (is_dir(Path::implode(App::core()->config()->get('cache_dir'), 'cbrepo'))) {
            Files::deltree(Path::implode(App::core()->config()->get('cache_dir'), 'cbrepo'));
        }

        return true;
    }
}
