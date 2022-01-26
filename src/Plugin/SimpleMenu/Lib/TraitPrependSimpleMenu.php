<?php
/**
 * @class Dotclear\Plugin\SimpleMenu\Lib\TraitPrependSimpleMenu
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginSimpleMenu
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Lib;

use Dotclear\Core\Core;
use Dotclear\Plugin\Widgets\Lib\Widgets;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitPrependSimpleMenu
{
    public static $widgets;

    public static function initWidgets(Core $core, Widgets $w): void
    {
        $w
            ->create('simplemenu', __('Simple menu'), [self::$widgets, 'simpleMenuWidget'], null, 'List of simple menu items')
            ->addTitle(__('Menu'))
            ->setting('description', __('Item description'), 0, 'combo',
                [
                    __('Displayed in link')                   => 0, // span
                    __('Used as link title')                  => 1, // title
                    __('Displayed in link and used as title') => 2, // both
                    __('Not displayed nor used')              => 3 // none
                ])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }
}
