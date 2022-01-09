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

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

abstract class AbstractPrepend
{
    /**
     * Check Module during process (Amdin, Public, Instal, ...)
     *
     * Module can check their specifics requirements here.
     * This methods must exists and return True or False.
     *
     * @param   Core        $core   Core instance
     *
     * @return  bool        False to stop module loading, True to go on
     */
    abstract public static function checkModule(Core $core): bool;

    /**
     * Load Module during process (Amdin, Public, Install, ...)
     *
     * For exemple, if module required Prepend class
     * for backend (Admin) to load admin menu, etc...
     * Prepend class must be present in Admin sub folder.
     *
     * @param   Core        $core   Core instance
     */
    public static function loadModule(Core $core): void
    {
        return;
    }

    /**
     * Install Module during process (Amdin, Public, Install, ...)
     *
     * For exemple, if module required Prepend class
     * to set up settings, database table, etc...
     * Prepend class must be present in current process sub folder.
     * For now only Admin process support install method.
     *
     * @param   Core        $core   Core instance
     *
     * @return  bool        True on success
     */
    public static function installModule(Core $core): ?bool
    {
        return null;
    }
}
