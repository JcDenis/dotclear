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
    /** @var    AbstractDefine|null     Module Define instance */
    private $define = null;

    /**
     * Constructor
     *
     * @param   AbstractDefine  $define     Module Define instance
     */
    public function __construct(AbstractDefine $define)
    {
        $this->define = $define;
    }

    /**
     * Get module definitions
     *
     * @return  AbstractDefine  Module Define instance
     */
    protected function define(): AbstractDefine
    {
        return $this->define;
    }

    /**
     * Check Module during process (Amdin, Public, Install, ...)
     *
     * Module can check their specifics requirements here.
     * This methods must exists and return True or False.
     *
     * @return  bool    False to stop module loading, True to go on
     */
    abstract public function checkModule(): bool;

    /**
     * Load Module during process (Amdin, Public, Install, ...)
     *
     * For exemple, if module required Prepend class
     * for backend (Admin) to load admin menu, etc...
     * Prepend class must be present in Admin sub folder.
     */
    abstract public function loadModule(): void;

    /**
     * Install Module during process (Amdin, Public, Install, ...)
     *
     * For exemple, if module required Prepend class
     * to set up settings, database table, etc...
     * Prepend class must be present in current process sub folder.
     * For now only Admin process support install method.
     *
     * @return  bool    True on success
     */
    abstract public function installModule(): ?bool;
}
