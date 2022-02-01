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

use Dotclear\Plugin\SimpleMenu\Lib\SimpleMenuWidgets;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function checkModule(): bool
    {
        return true;
    }

    public static function loadModule(): void
    {
        # Add Plugin Admin Page sidebar menu item
        dcCore()->menu['Blog']->addItem(
            __('Simple menu'),
            dcCore()->adminurl->get('admin.plugin.SimpleMenu'),
            '?mf=Plugin/SimpleMenu/icon.svg',
            dcCore()->adminurl->called() == 'admin.plugin.SimpleMenu',
            dcCore()->auth->check('usage,contentadmin', dcCore()->blog->id)
        );

        # Add Plugin Admin behaviors
        dcCore()->behaviors->add('adminDashboardFavorites', [__CLASS__, 'behaviorAdminDashboardFavorites']);
        dcCore()->behaviors->add('adminDashboardIcons', [__CLASS__, 'behaviorAdminDashboardIcons']);

        # Widgets
        if (dcCore()->adminurl->called() == 'admin.plugin.Widgets') {
            new SimpleMenuWidgets();
        }
    }

    public static function installModule(): ?bool
    {
        # Menu par défaut
        $blog_url     = html::stripHostURL(dcCore()->blog->url);
        $menu_default = [
            ['label' => 'Home', 'descr' => 'Recent posts', 'url' => $blog_url, 'targetBlank' => false],
            ['label' => 'Archives', 'descr' => '', 'url' => $blog_url . dcCore()->url->getURLFor('archive'), 'targetBlank' => false]
        ];
        dcCore()->blog->settings->system->put('simpleMenu', $menu_default, 'array', 'simpleMenu default menu', false, true);
        dcCore()->blog->settings->system->put('simpleMenu_active', true, 'boolean', 'Active', false, true);

        return true;
    }

    public static function behaviorAdminDashboardIcons($icons)
    {
        $icons['simpleMenu'] = new ArrayObject([__('Simple menu'),
            dcCore()->adminurl->get('admin.plugin.SimpleMenu'),
            '?mf=Plugin/SimpleMenu/icon.svg'
        ]);
    }

    public static function behaviorAdminDashboardFavorites($favs)
    {
        $favs->register('simpleMenu', [
            'title'       => __('Simple menu'),
            'url'         => dcCore()->adminurl->get('admin.plugin.SimpleMenu'),
            'small-icon'  => '?mf=Plugin/SimpleMenu/icon.svg',
            'large-icon'  => '?mf=Plugin/SimpleMenu/icon.svg',
            'permissions' => 'usage,contentadmin'
        ]);
    }
}
