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

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        # Menu and Favorties
        static::addStandardMenu('Blog');
        static::addStandardFavorites();

        # rte
        dcCore()->behaviors->add('adminRteFlags', function (ArrayObject $rte): void {
            $rte['widgets_text'] = [true, __('Widget\'s textareas')];
        });

        # Widgets
        new WidgetsStack();
    }

    public static function installModule(): ?bool
    {
        $widgets  = new Widgets();
        $settings = dcCore()->blog->settings;
        $settings->addNamespace('widgets');
        if ($settings->widgets->widgets_nav != null) {
            $settings->widgets->put('widgets_nav', $widgets->load($settings->widgets->widgets_nav)->store());
        } else {
            $settings->widgets->put('widgets_nav', '', 'string', 'Navigation widgets', false);
        }
        if ($settings->widgets->widgets_extra != null) {
            $settings->widgets->put('widgets_extra', $widgets->load($settings->widgets->widgets_extra)->store());
        } else {
            $settings->widgets->put('widgets_extra', '', 'string', 'Extra widgets', false);
        }
        if ($settings->widgets->widgets_custom != null) {
            $settings->widgets->put('widgets_custom', $widgets->load($settings->widgets->widgets_custom)->store());
        } else {
            $settings->widgets->put('widgets_custom', '', 'string', 'Custom widgets', false);
        }

        return true;
    }
}
