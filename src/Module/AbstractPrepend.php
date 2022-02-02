<?php
/**
 * @class Dotclear\Module\AbstractPrepend
 * @brief Dotclear Module abstract Prepend
 *
 * Module Prepend class must extends this class.
 * It provides information on Module load.
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Module\AbstractDefine;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

abstract class AbstractPrepend
{
    /**
     * Module Define instance
     *
     * Temporary accessible during check/load/install
     *
     * @var AbstractDefine|null
     */
    protected static $define = null;

    public static function setDefine(AbstractDefine $define): void
    {
        static::$define = $define;
    }

    public static function unsetDefine(): void
    {
        static::$define = null;
    }

    /**
     * Check Module during process (Amdin, Public, Install, ...)
     *
     * Module can check their specifics requirements here.
     * This methods must exists and return True or False.
     *
     * @return  bool        False to stop module loading, True to go on
     */
    abstract public static function checkModule(): bool;

    /**
     * Load Module during process (Amdin, Public, Install, ...)
     *
     * For exemple, if module required Prepend class
     * for backend (Admin) to load admin menu, etc...
     * Prepend class must be present in Admin sub folder.
     */
    abstract public static function loadModule(): void;

    /**
     * Install Module during process (Amdin, Public, Install, ...)
     *
     * For exemple, if module required Prepend class
     * to set up settings, database table, etc...
     * Prepend class must be present in current process sub folder.
     * For now only Admin process support install method.
     *
     * @return  bool        True on success
     */
    abstract public static function installModule(): ?bool;
}
