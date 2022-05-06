<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Admin;

// Dotclear\Plugin\Breadcrumb\Admin\Prepend
use Dotclear\Modules\ModulePrepend;

/**
 * Admin prepend for plugin Breadcrumb.
 *
 * @ingroup  Plugin Breadcrumb
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        new BreadcrumbBehavior();
    }
}
