<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Buildtools\Admin;

// Dotclear\Plugin\Buildtools\Admin\MaintenanceTaskBuildtools
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * Buildtools task for plugin Maintenance.
 *
 * @ingroup  Plugin Buildtools Maintenance Task
 */
class MaintenanceTaskBuildtools extends MaintenanceTask
{
    protected $tab   = 'dev';
    protected $group = 'l10n';

    protected function init(): void
    {
        $this->task        = __('Generate fake l10n');
        $this->success     = __('fake l10n file generated.');
        $this->error       = __('Failed to generate fake l10n file.');
        $this->description = __('Generate a php file that contents strings to translate that are not be done with core tools.');
    }

    public function execute(): int|bool
    {
        /*
        $widget = dotclear()->plugins()?->getModules('widgets');
        include $widget['root'] . '/_default_widgets.php';
        */

        $faker = new L10nFaker();
        $faker->generate_file();

        return true;
    }
}
