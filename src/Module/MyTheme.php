<?php
/**
 * @brief Theme My module class.
 * 
 * A theme My class must extend this class.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 *
 * @since 2.27
 */
declare(strict_types=1);

namespace Dotclear\Module;

use dcCore;
use dcThemes;
use dcModuleDefine;

/**
 * Theme module helper.
 *
 * My class of module of type "theme" SHOULD extedns this class.
 */
abstract class MyTheme extends MyModule
{
    protected static function define(): dcModuleDefine
    {
        if (!(static::$define instanceof dcModuleDefine)) {
            // should never happend but hey.
            if (!(dcCore::app()->themes instanceof dcThemes)) {
                dcCore::app()->themes = new dcThemes();
                dcCore::app()->themes->loadModules((string) dcCore::app()->blog?->themes_path, null);
            }

            static::$define = static::getDefineFromNamespace(dcCore::app()->themes);
        }

        return static::$define;
    }
}