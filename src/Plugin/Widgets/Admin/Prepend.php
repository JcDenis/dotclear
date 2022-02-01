<?php
/**
 * @class Dotclear\Plugin\Widgets\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Admin;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Plugin\Widgets\Lib\WidgetsStack;
use Dotclear\Plugin\Widgets\Lib\Widgets;

use Dotclear\Admin\Favorites;

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
            __('Presentation widgets'),
            dcCore()->adminurl->get('admin.plugin.Widgets'),
            ['?mf=Plugin/Widgets/icon.svg', '?mf=Plugin/Widgets/icon-dark.svg'],
            dcCore()->adminurl->called() == 'admin.plugin.Widgets',
            dcCore()->auth->check('admin', dcCore()->blog->id)
        );

        # Add Plugin Admin behaviors
        dcCore()->behaviors->add('adminDashboardFavorites', [__CLASS__, 'behaviorAdminDashboardFavorites']);
        dcCore()->behaviors->add('adminRteFlags', [__CLASS__, 'behaviorAdminRteFlags']);

        # Load widgets
        new WidgetsStack();
    }

    public static function installModule(): ?bool
    {
        $settings = dcCore()->blog->settings;
        $settings->addNamespace('widgets');
        if ($settings->widgets->widgets_nav != null) {
            $settings->widgets->put('widgets_nav', Widgets::load($settings->widgets->widgets_nav)->store());
        } else {
            $settings->widgets->put('widgets_nav', '', 'string', 'Navigation widgets', false);
        }
        if ($settings->widgets->widgets_extra != null) {
            $settings->widgets->put('widgets_extra', Widgets::load($settings->widgets->widgets_extra)->store());
        } else {
            $settings->widgets->put('widgets_extra', '', 'string', 'Extra widgets', false);
        }
        if ($settings->widgets->widgets_custom != null) {
            $settings->widgets->put('widgets_custom', Widgets::load($settings->widgets->widgets_custom)->store());
        } else {
            $settings->widgets->put('widgets_custom', '', 'string', 'Custom widgets', false);
        }

        return true;
    }

    public static function behaviorAdminDashboardFavorites(Favorites $favs): void
    {
        $favs->register('Widgets', [
            'title'      => __('Presentation widgets'),
            'url'        => dcCore()->adminurl->get('admin.plugin.Widgets'),
            'small-icon' => ['?mf=Plugin/Widgets/icon.svg', '?mf=Plugin/Widgets/icon-dark.svg'],
            'large-icon' => ['?mf=Plugin/Widgets/icon.svg', '?mf=Plugin/Widgets/icon-dark.svg'],
        ]);
    }

    public static function behaviorAdminRteFlags(ArrayObject $rte): void
    {
        $rte['widgets_text'] = [true, __('Widget\'s textareas')];
    }
}
