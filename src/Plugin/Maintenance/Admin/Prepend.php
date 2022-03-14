<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Maintenance\Admin\MaintenanceBehavior;
use Dotclear\Plugin\Maintenance\Admin\MaintenanceRest;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        # Menu
        $this->addStandardMenu('Plugins');

        # Behaviors
        new MaintenanceRest();
        new MaintenanceBehavior();
    }
}
