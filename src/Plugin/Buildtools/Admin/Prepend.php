<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Buildtools\Admin;

// Dotclear\Plugin\Buildtools\Admin\Prepend
use Dotclear\App;
use Dotclear\Helper\File\Path;
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\Maintenance\Admin\Lib\Maintenance;

/**
 * Admin prepend for plugin Buildtools.
 *
 * @ingroup  Plugin Buildtools
 */
class Prepend extends ModulePrepend
{
    public function checkModule(): bool
    {
        return !Path::inComposer();
    }

    public function loadModule(): void
    {
        App::core()->behavior()->add('dcMaintenanceInit', function (Maintenance $maintenance): void {
            $maintenance->addTask('Dotclear\\Plugin\\Buildtools\\Admin\\MaintenanceTaskBuildtools');
        });
    }
}
