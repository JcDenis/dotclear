<?php
/**
 * @class Dotclear\Core\Module\AbstractPrepend
 * @brief Dotclear Module abstract Prepend
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
     * Load Module during process (Amdin, Public, Instal, ...)
     *
     * For exemple, if module required Prepend class
     * for backend (Admin) to load admin menu, etc...
     * Prepend class must be present in Admin sub folder.
     *
     * @param   Core        $core   Core instance
     * @return  bool|null           Null to stop module loading, True to go on
     */
    abstract public static function loadModule(Core $core): ?bool;
}
