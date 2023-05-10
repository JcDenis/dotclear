<?php
/**
 * @brief Plugin My module class.
 * 
 * A plugin My class must extend this class.
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
use dcModuleDefine;

abstract class MyPlugin extends MyModule
{
    protected static function define(): dcModuleDefine
    {
        if (!(static::$define instanceof dcModuleDefine)) {

            static::$define = static::getDefineFromNamespace(dcCore::app()->plugins);
        }

        return static::$define;
    }
}