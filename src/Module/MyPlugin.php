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

use dcAdmin;
use dcCore;
use dcMenu;
use dcModuleDefine;
use dcPage;

abstract class MyPlugin extends MyModule
{
    protected static function define(): dcModuleDefine
    {
        if (!(static::$define instanceof dcModuleDefine)) {
            static::$define = static::getDefineFromNamespace(dcCore::app()->plugins);
        }

        return static::$define;
    }

    /**
     * Register backend sidebar menu icon.
     *
     * @param   string                  $menu   The menu (from dcAdmin constant)
     * @param   array<string,string>    $param  The URL params
     * @param   string                  $scheme the URL end scheme
     */
    public static function backendSidebarMenuIcon(string $menu = dcAdmin::MENU_PLUGINS, array $params = [], string $scheme = '(&.*)?$'): void
    {
        if (!defined('DC_CONTEXT_ADMIN') || is_null(dcCore::app()->adminurl) || !(dcCore::app()->menu[$menu] instanceof dcMenu)) {
            return;
        }

        dcCore::app()->menu[$menu]->addItem(
            static::name(),
            dcCore::app()->adminurl->get('admin.plugin.' . static::id(), $params),
            static::icons(),
            preg_match('/' . preg_quote(dcCore::app()->adminurl->get('admin.plugin.' . static::id())) . $scheme . '/', $_SERVER['REQUEST_URI']),
            static::checkContext(static::MENU)
        );
    }

    /**
     * Get modules icon URLs.
     *
     * @return  array<string,string>    The module icons URLs
     */
    protected static function icons(): array
    {
        $icons = [urldecode(dcPage::getPF(static::id() . '/icon.svg'))];
        if (file_exists(static::path() . DIRECTORY_SEPARATOR . 'icon-dark.svg')) {
            $icons = [urldecode(dcPage::getPF(static::id() . '/icon-dark.svg'))];
        }

        retunr $icons;
    }
}