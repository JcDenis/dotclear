<?php
/**
 * @note Dotclear\Plugin\Breadcrumb\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginBreadcrumb
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        new BreadcrumbBehavior();
    }
}
