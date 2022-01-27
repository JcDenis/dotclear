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

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Plugin\Widgets\Lib\WidgetsStack;
use Dotclear\Plugin\Widgets\Lib\Widgets;

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function checkModule(Core $core): bool
    {
        return true;
    }

    public static function loadModule(Core $core): void
    {
        # Add Plugin Admin Page sidebar menu item
        $core->menu['Blog']->addItem(
            __('Presentation widgets'),
            $core->adminurl->get('admin.plugin.Widgets'),
            '?mf=Plugin/Widgets/icon.png',
            $core->adminurl->called() == 'admin.plugin.Widgets',
            $core->auth->check('admin', $core->blog->id)
        );

        # Add Plugin Admin behaviors
        $core->behaviors->add('adminDashboardFavorites', [__CLASS__, 'behaviorAdminDashboardFavorites']);
        $core->behaviors->add('adminRteFlags', [__CLASS__, 'behaviorAdminRteFlags']);

        # Load widgets
        WidgetsStack::initWidgets($core);
    }

    public static function installModule(Core $core): ?bool
    {
        $settings = $core->blog->settings;
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

    public static function behaviorAdminDashboardFavorites($core, $favs)
    {
        $favs->register('Widgets', [
            'title'      => __('Presentation widgets'),
            'url'        => $core->adminurl->get('admin.plugin.Widgets'),
            'small-icon' => '?mf=Plugin/Widgets/icon.png',
            'large-icon' => '?mf=Plugin/Widgets/icon-big.png'
        ]);
    }

    public static function behaviorAdminRteFlags($core, $rte)
    {
        $rte['widgets_text'] = [true, __('Widget\'s textareas')];
    }
}
