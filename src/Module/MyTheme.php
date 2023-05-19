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

/**
 * Theme module helper.
 *
 * My class of module of type "theme" SHOULD extedns this class.
 */
abstract class MyTheme extends MyModule
{
    protected static function define(): Define
    {
        if (!(static::$define instanceof Define)) {
            // should never happend but hey.
            if (!(dcCore::app()->themes instanceof dcThemes)) {
                dcCore::app()->themes = new dcThemes();
                dcCore::app()->themes->loadModules((string) dcCore::app()->blog?->themes_path, null);
            }

            static::$define = static::getDefineFromNamespace(dcCore::app()->themes);
        }

        return static::$define;
    }

    protected static function checkCustomContext(int $context): ?bool
    {
        // themes specific context permissions
        return match($context) {
            self::BACKEND =>     // Backend context
                defined('DC_CONTEXT_ADMIN')
                    // Check specific permission, limited to blog admin for themes
                    && !is_null(dcCore::app()->blog) && !is_null(dcCore::app()->auth)
                    && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_ADMIN,
                    ]), dcCore::app()->blog->id),
            self::CONFIG =>      // Config page of module
                defined('DC_CONTEXT_ADMIN')
                    // Check specific permission, allowed to blog admin for themes
                    && !is_null(dcCore::app()->blog) && !is_null(dcCore::app()->auth)
                    && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_ADMIN,
                    ]), dcCore::app()->blog->id),

            default => null,
        };
    }
}