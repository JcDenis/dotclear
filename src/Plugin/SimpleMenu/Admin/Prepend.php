<?php
/**
 * @class Dotclear\Plugin\SimpleMenu\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Plugin\SimpleMenu\Lib\TraitPrependSimpleMenu;
use Dotclear\Plugin\Widgets\Lib\Widgets;

use Dotclear\Core\Core;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin, TraitPrependSimpleMenu;

    public static function checkModule(Core $core): bool
    {
        return true;
    }

    public static function loadModule(Core $core): void
    {
        # Add Plugin Admin Page sidebar menu item
        $core->menu['Blog']->addItem(
            __('Simple menu'),
            $core->adminurl->get('admin.plugin.SimpleMenu'),
            '?mf=Plugin/SimpleMenu/icon.png',
            $core->adminurl->called() == 'admin.plugin.SimpleMenu',
            $core->auth->check('usage,contentadmin', $core->blog->id)
        );

        # Add Plugin Admin behaviors
        $core->behaviors->add('initWidgets', [__CLASS__, 'initWidgets']);
        $core->behaviors->add('adminDashboardFavorites', [__CLASS__, 'behaviorAdminDashboardFavorites']);
        $core->behaviors->add('adminDashboardIcons', [__CLASS__, 'behaviorAdminDashboardIcons']);
    }

    public static function installModule(Core $core): ?bool
    {
        # Menu par défaut
        $blog_url     = html::stripHostURL($core->blog->url);
        $menu_default = [
            ['label' => 'Home', 'descr' => 'Recent posts', 'url' => $blog_url, 'targetBlank' => false],
            ['label' => 'Archives', 'descr' => '', 'url' => $blog_url . $core->url->getURLFor('archive'), 'targetBlank' => false]
        ];
        $core->blog->settings->system->put('simpleMenu', $menu_default, 'array', 'simpleMenu default menu', false, true);
        $core->blog->settings->system->put('simpleMenu_active', true, 'boolean', 'Active', false, true);

        return true;
    }

    public static function behaviorAdminDashboardIcons($core, $icons)
    {
        $icons['simpleMenu'] = new ArrayObject([__('Simple menu'),
            $core->adminurl->get('admin.plugin.SimpleMenu'),
            '?mf=Plugin/SimpleMenu/icon.png'
        ]);
    }

    public static function behaviorAdminDashboardFavorites($core, $favs)
    {
        $favs->register('simpleMenu', [
            'title'       => __('Simple menu'),
            'url'         => $core->adminurl->get('admin.plugin.SimpleMenu'),
            'small-icon'  => '?mf=Plugin/SimpleMenu/icon-small.png',
            'large-icon'  => '?mf=Plugin/SimpleMenu/icon.png',
            'permissions' => 'usage,contentadmin'
        ]);
    }
}
