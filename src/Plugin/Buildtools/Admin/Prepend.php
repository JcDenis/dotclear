<?php
/**
 * @class Dotclear\Plugin\Buildtools\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBuildtools
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Buildtools\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Plugin\Maintenance\Lib\Maintenance;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        dotclear()->behaviors->add('dcMaintenanceInit', function(Maintenance $maintenance): void {
            $maintenance->addTask('Dotclear\\Plugin\\Buildtools\\Admin\\MaintenanceTaskBuildtools');
        });
    }
}
