<?php
/**
 * @brief Generic My module class.
 * 
 * This class is an helper to have short access to
 * module properties and common requiremets.
 * 
 * A module My class must not extend this class 
 * but must extend MyPlugin or MyTheme class.
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
use Dotclear\Helper\L10n;
use Exception;

/**
 * Module helper.
 *
 * Module My class MUST NOT extend this class
 * but MyPlugin or MyTheme.
 */
abstract class MyModule
{
    /** @var    int     Install context */
    final public const INSTALL = 0;

    /** @var    int     Prepend context */
    final public const PREPEND = 1;

    /** @var    int     Frontend context */
    final public const FRONTEND = 2;

    /** @var    int     Backend context (usually when the connected user may access at least one functionnality of this module) */
    final public const BACKEND = 3;

    /** @var    int     Manage context (main page of module) */
    final public const MANAGE = 4;

    /** @var    int     Config context (config page of module) */
    final public const CONFIG = 5;

    /** @var    int     Menu context (adding a admin menu item) */
    final public const MENU = 6;

    /** @var    int     Widgets context (managing blog's widgets) */
    final public const WIDGETS = 7;

    /** @var    int     Uninstall context */
    final public const UNINSTALL = 8;

    /** @var    array<string,Define>    The know modules defines */
    protected static $defines = [];

    /**
     * Load (once) the module define.
     * 
     * This method is defined in MyPlugin or MyTheme.
     *
     * @return  Define  The module define
     */
    abstract protected static function define(): Define;

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
     * @param   int     $context     context
     *
     * @return  bool    true if allowed, else false
     */
    final public static function checkContext(int $context): bool
    {
        // module contextual permissions
        $check = static::checkCustomContext($context);
        if (!is_null($check)) {
            return $check;
        }

        // else default permissions
        return match($context) {
            self::INSTALL =>    // Installation of module
                defined('DC_CONTEXT_ADMIN')
                    && dcCore::app()->auth->isSuperAdmin()   // Manageable only by super-admin
                    && dcCore::app()->newVersion(self::id(), dcCore::app()->plugins->getDefine(self::id())->version),

            self::UNINSTALL =>  // Uninstallation of module
                defined('DC_RC_PATH')
                    && dcCore::app()->auth->isSuperAdmin(),   // Manageable only by super-admin

            self::PREPEND, self::FRONTEND =>    // Prpend and Frontend context
                defined('DC_RC_PATH'),

            self::BACKEND =>     // Backend context
                defined('DC_CONTEXT_ADMIN')
                    // Check specific permission
                    && !is_null(dcCore::app()->blog)
                    && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_USAGE,
                        dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
                    ]), dcCore::app()->blog->id),

            self::MANAGE, self::MENU, self::WIDGETS =>  // Main page of module, Admin menu, Blog widgets
                defined('DC_CONTEXT_ADMIN')
                    // Check specific permission
                    && !is_null(dcCore::app()->blog)
                    && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_ADMIN,  // Admin+
                    ]), dcCore::app()->blog->id),

            self::CONFIG =>      // Config page of module
                defined('DC_CONTEXT_ADMIN')
                    && dcCore::app()->auth->isSuperAdmin(),   // Manageable only by super-admin

            default => false,
        };
    }

    /**
     * Get the module path.
     *
     * @return  string  The module path
     */
    final public static function path(): string
    {
        return static::define()->root;
    }

    /**
     * The module ID.
     *
     * @return  string The module ID
     */
    final public static function id(): string
    {
        return static::define()->id;
    }

    /**
     * The module name.
     *
     * @return  string The module translated name
     */
    final public static function name(): string
    {
        return static::define()->name;
    }

    /**
     * Set module locales.
     *
     * @param   string  $process    The locales process
     */
    final public static function l10n(string $process): void
    {
        L10n::set(implode(DIRECTORY_SEPARATOR, [static::path(), 'locales', dcCore::app()->lang, $process]));
    }

    /**
     * Get module define from its namespace.
     *
     * This method is used to load module define.
     * see MyPlugin::define() and MyTheme::define()
     * 
     * @param   Modules     $modules    The modules instance (Themes or Plugins)
     *
     * @return  Define  The module define
     */
    final protected static function getDefineFromNamespace(Modules $modules): Define
    {
        // check if Define is already known
        if (!isset(static::$defines[static::class])) {
            // note: namespace from Modules start with a backslash
            $find = $modules->searchDefines([
                'namespace' => '\\' . (new \ReflectionClass(static::class))->getNamespaceName()
            ]);
            if (count($find) != 1) {
                static::exception('Failed to find namespace from ' . static::class);
            }

            static::$defines[static::class] = $find[0];
        }

        return static::$defines[static::class];
    }

    /**
     * Throw exception on breaking script error.
     */
    final static protected function exception(string $msg = ''): void
    {
        $msg = defined('DC_DEV') && DC_DEV && !empty($msg) ? ': ' . $msg : '';

        throw new Exception('Invalid module structure' . $msg);
    }
}