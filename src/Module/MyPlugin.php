<?php
/**
 * @brief Modules defined properties.
 *
 * Provides an object to handle modules properties (themes or plugins).
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use dcCore;
use dcModuleDefine;

/**
 * Plugin My module class.
 * 
 * A plugin My class must extend this class.
 *
 * @since 2.27
 */
abstract class MyPlugin extends MyModule
{
    protected static function define(): dcModuleDefine
    {
        if (!(static::$define instanceof dcModuleDefine)) {

            static::$define = dcCore::app()->plugins->getDefine(static::idFromNamespace());
        }

        return static::$define;
    }
}