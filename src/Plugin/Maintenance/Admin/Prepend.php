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
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

/**
 * Admin prepend for plugin Maintenance.
 *
 * @ingroup  Plugin Maintenance
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        // Menu
        $this->addStandardMenu('Plugins');

        // Behaviors
        new MaintenanceRest();
        new MaintenanceBehavior();
    }
}
