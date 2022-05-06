<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin;

// Dotclear\Plugin\Maintenance\Admin\Prepend
use Dotclear\Modules\ModulePrepend;

/**
 * Admin prepend for plugin Maintenance.
 *
 * @ingroup  Plugin Maintenance
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        // Menu
        $this->addStandardMenu('Plugins');

        // Behaviors
        new MaintenanceRest();
        new MaintenanceBehavior();
    }
}
