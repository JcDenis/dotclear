<?php
/**
 * @note Dotclear\Plugin\Breadcrumb\Public\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginBreadcrumb
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        new BreadcrumbTemplate();
    }
}
