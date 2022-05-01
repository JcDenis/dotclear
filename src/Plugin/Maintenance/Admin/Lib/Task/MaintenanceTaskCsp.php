<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

// Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskCSP
use Dotclear\App;
use Dotclear\Helper\File\Path;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * CSP report maintenance Task.
 *
 * @ingroup  Plugin Maintenance Task
 */
class MaintenanceTaskCSP extends MaintenanceTask
{
    protected $group = 'purge';

    protected function init(): void
    {
        $this->task    = __('Delete the Content-Security-Policy report file');
        $this->success = __('Content-Security-Policy report file has been deleted.');
        $this->error   = __('Failed to delete the Content-Security-Policy report file.');

        $this->description = __('Remove the Content-Security-Policy report file.');
    }

    public function execute(): int|bool
    {
        if (false !== ($csp_file = Path::real(App::core()->config()->get('var_dir') . '/csp/csp_report.json'))) {
            unlink($csp_file);
        }

        return true;
    }
}
