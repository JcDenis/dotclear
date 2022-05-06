<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Public;

// Dotclear\Plugin\Breadcrumb\Public\Prepend
use Dotclear\Modules\ModulePrepend;

/**
 * Public prepend for plugin Breacrumb.
 *
 * @ingroup  Plugin Breadcrumb
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        new BreadcrumbTemplate();
    }
}
