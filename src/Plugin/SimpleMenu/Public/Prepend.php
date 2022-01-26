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

use Dotclear\Plugin\SimpleMenu\Lib\TraitPrependSimpleMenu;
use Dotclear\Plugin\SimpleMenu\Public\PublicWidgets;

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic, TraitPrependSimpleMenu;

    public static function loadModule(Core $core): void
    {
        self::$widgets = new PublicWidgets($core);
        $core->behaviors->add('initWidgets', [__CLASS__, 'initWidgets']);
        $core->tpl->addValue('SimpleMenu', [self::$widgets, 'simpleMenu']);
    }
}
