<?php
/**
 * @note Dotclear\Plugin\SimpleMenu\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @ingroup  PluginSimpleMenu
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\SimpleMenu\Common\SimpleMenuWidgets;

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        // Widgets
        new SimpleMenuWidgets();
    }
}
