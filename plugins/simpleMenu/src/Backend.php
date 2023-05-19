<?php
/**
 * @brief simpleMenu, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\simpleMenu;

use dcAdmin;
use dcCore;
use dcFavorites;
use dcNsProcess;

class Backend extends dcNsProcess
{
    public static function init(): bool
    {
        return (static::$init = My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        dcCore::app()->addBehaviors([
            'adminDashboardFavoritesV2' => function (dcFavorites $favs) {
                $favs->register(My::id(), [
                    'title'       => My::name(),
                    'url'         => My::backendUrl(),
                    'small-icon'  => My::icons(),
                    'large-icon'  => My::icons(),
                    'permissions' => dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_ADMIN,
                    ]),
                ]);
            },
            'initWidgets' => [Widgets::class, 'initWidgets'],
        ]);

        My::backendSidebarMenuIcon(dcAdmin::MENU_BLOG);

        return true;
    }
}
