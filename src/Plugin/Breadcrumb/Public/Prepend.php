<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

/**
 * Public prepend for plugin Breacrumb.
 *
 * \Dotclear\Plugin\Breadcrumb\Public\Prepend
 *
 * @ingroup  Plugin Breadcrumb
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        new BreadcrumbTemplate();
    }
}
