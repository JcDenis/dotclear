<?php
/**
 * @note Dotclear\Plugin\Maintenance\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

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
