<?php
/**
 * @class Dotclear\Plugin\Widgets\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

use Dotclear\Plugin\Widgets\Lib\WidgetsStack;
use Dotclear\Plugin\Widgets\Public\PublicWidgets;

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(Core $core): void
    {
        # Load widgets
        WidgetsStack::initWidgets($core);
        PublicWidgets::setCore($core);

        $core->tpl->addValue('Widgets', [__NAMESPACE__ . '\\PublicWidgets', 'tplWidgets']);
        $core->tpl->addBlock('Widget', [__NAMESPACE__ . '\\PublicWidgets', 'tplWidget']);
        $core->tpl->addBlock('IfWidgets', [__NAMESPACE__ . '\\PublicWidgets', 'tplIfWidgets']);
    }
}
