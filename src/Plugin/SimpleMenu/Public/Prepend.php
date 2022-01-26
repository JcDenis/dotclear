<?php
/**
 * @class Dotclear\Plugin\SimpleMenu\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

use Dotclear\Plugin\SimpleMenu\Lib\SimpleMenuWidgets;

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(Core $core): void
    {
        # Widgets
        new SimpleMenuWidgets($core);
    }
}
