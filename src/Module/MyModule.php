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

use dcModuleDefine;

/**
 * Generic My module class.
 * 
 * This class is an helper to have short access to
 * module properties and common requiremets.
 * 
 * A module My class must not extend this class 
 * but must extend MyPlugin or MyTheme class.
 * 
 * PHP_MIN could be override in module My class.
 * 
 * (DEV: waiting php 8.1 to use final on context constants)
 * (DEV: maybe PHP_MIN should be defined in "requires" from _define.php file?)
 *
 * @since 2.27
 */
abstract class MyModule
{
    /** @var    int     Install context */
    public const INSTALL = 0;

    /** @var    int     Prepend context */
    public const PREPEND = 1;

    /** @var    int     Frontend context */
    public const FRONTEND = 2;

    /** @var    int     Backend context (usually when the connected user may access at least one functionnality of this module) */
    public const BACKEND = 3;

    /** @var    int     Manage context (main page of module) */
    public const MANAGE = 4;

    /** @var    int     Config context (config page of module) */
    public const CONFIG = 5;

    /** @var    int     Menu context (adding a admin menu item) */
    public const MENU = 6;

    /** @var    int     Widgets context (managing blog's widgets) */
    public const WIDGETS = 7;

    /** @var    int     Uninstall context */
    public const UNINSTALL = 8;

    /** @var    string  The default supported php version */
    public const PHP_MIN = '7.4';

    /** @var    dcModuleDefine  The module define */
    protected static $define;

    /**
     * Load (once) the module define.
     * 
     * This method is defined in MyPlugin or MyTheme.
     *
     * @return  dcModuleDefine  The module define
     */
    abstract protected static function define(): dcModuleDefine;

    /**
     * Check context permission.
     * 
     * Module My class could implement this method 
     * to check specific context permissions, 
     * and else return null for classic context permissions.
     *
     * @param   int     $context     context
     *
     * @return  null|bool    true if allowed, false if not, null to let MyModule do check
     */
    protected static function checkCustomContext(int $context): ?bool
    {
        return null;
    }

    /**
     * Check permission depending on given context.
     * 
     * (DEV: waiting php 8.0 to use match synthax)
     *
     * @param   int     $context     context
     *
     * @return  bool    true if allowed, else false
     */
    final public static function checkContext(int $context): bool
    {
        // nullsafe (should never happened)
        if (is_null(dcCore::app()->auth) || is_null(dcCore::app()->blog)) {
            static::exception();
        }

        // module contextual permissions
        $check = static::checkCustomContext($context);
        if (!is_null($check)) {
            return $check;
        }

        // else default permissions
        switch ($context) {
            case self::INSTALL:    // Installation of module
                return defined('DC_CONTEXT_ADMIN')
                    && self::phpCompliant()
                    && dcCore::app()->auth->isSuperAdmin()   // Manageable only by super-admin
                    && dcCore::app()->newVersion(self::id(), dcCore::app()->plugins->moduleInfo(self::id(), 'version'));

            case self::UNINSTALL:  // Uninstallation of module
                return defined('DC_RC_PATH')
                    && self::phpCompliant()
                    && dcCore::app()->auth->isSuperAdmin();   // Manageable only by super-admin

            case self::PREPEND:    // Prepend context
                return defined('DC_RC_PATH')
                    && self::phpCompliant();

            case self::FRONTEND:    // Frontend context
                return defined('DC_RC_PATH')
                    && self::phpCompliant();

            case self::BACKEND:     // Backend context
                return defined('DC_CONTEXT_ADMIN')
                    && self::phpCompliant()
                    // Check specific permission
                    && dcCore::app()->blog && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_USAGE,
                        dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
                    ]), dcCore::app()->blog->id);

            case self::MANAGE:      // Main page of module
                return defined('DC_CONTEXT_ADMIN')
                    && self::phpCompliant()
                    // Check specific permission
                    && dcCore::app()->blog && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_ADMIN,  // Admin+
                    ]), dcCore::app()->blog->id);

            case self::CONFIG:      // Config page of module
                return defined('DC_CONTEXT_ADMIN')
                    && self::phpCompliant()
                    && dcCore::app()->auth->isSuperAdmin();   // Manageable only by super-admin

            case self::MENU:        // Admin menu
                return defined('DC_CONTEXT_ADMIN')
                    && self::phpCompliant()
                    // Check specific permission
                    && dcCore::app()->blog && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_ADMIN,  // Admin+
                    ]), dcCore::app()->blog->id);

            case self::WIDGETS:     // Blog widgets
                return defined('DC_CONTEXT_ADMIN')
                    && self::phpCompliant()
                    // Check specific permission
                    && dcCore::app()->blog && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_ADMIN,  // Admin+
                    ]), dcCore::app()->blog->id);
        }

        return false;
    }

    /**
     * Get the module path.
     *
     * @return  string  The module path
     */
    final public static function path(): string
    {
        $value = static::define()->get('root');
        if (!is_string($value)) {
            static::exception();
        }

        return $value;
    }

    /**
     * The module ID.
     *
     * @return  string The module ID
     */
    final public static function id(): string
    {
        return static::define()->getId();
    }

    /**
     * The module name.
     *
     * @return  string The module translated name
     */
    final public static function name(): string
    {
        $value = static::define()->get('name');

        return __(is_string($value) ? $value : static::id());
    }

    /**
     * Check php version.
     *
     * @return  bool    True if module work on current PHP version
     */
    final public static function phpCompliant(): bool
    {
        return version_compare(phpversion(), static::PHP_MIN, '>=');
    }

    /**
     * Extract module ID from its namespace.
     * 
     * This method is used to load module define.
     * see MyPlugin::define() and MyTheme::define()
     * 
     * @return  string  The module id
     */
    final protected static function idFromNamespace(): string
    {
        $part = explode('\\', static::class);
        if (count($part) != 4) {
            static::exception();
        }
        
        return $part[2];
    }

    /**
     * Throw exception.
     *
     * (DEV: should be more explicit in DC_DEV mode)
     */
    final protected function exception()
    {
        throw new Exception('Invalid module structure');
    }
}