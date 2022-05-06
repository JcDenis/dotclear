<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Public;

// Dotclear\Plugin\SimpleMenu\Public\Prepend
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\SimpleMenu\Common\SimpleMenuWidgets;

/**
 * Public prepend for plugin SimpleMenu.
 *
 * @ingroup  Plugin SimpleMenu
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        // Widgets
        new SimpleMenuWidgets();
    }
}
