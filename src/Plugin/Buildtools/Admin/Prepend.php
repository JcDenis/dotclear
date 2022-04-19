<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Buildtools\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Maintenance\Admin\Lib\Maintenance;

/**
 * Admin prepend for plugin Buildtools.
 *
 * \Dotclear\Plugin\Buildtools\Admin\Prepend
 *
 * @ingroup  Plugin Buildtools
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        dotclear()->behavior()->add('dcMaintenanceInit', function (Maintenance $maintenance): void {
            $maintenance->addTask('Dotclear\\Plugin\\Buildtools\\Admin\\MaintenanceTaskBuildtools');
        });
    }
}
