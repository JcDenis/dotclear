<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Admin;

// Dotclear\Plugin\Widgets\Admin\Prepend
use Dotclear\App;
use Dotclear\Core\User\Preference\RteFlags;
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\Widgets\Common\Widgets;
use Dotclear\Plugin\Widgets\Common\WidgetsStack;

/**
 * Admin prepend for plugin Widgets.
 *
 * @ingroup  Plugin Widgets
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        // Menu and Favorties
        $this->addStandardMenu('Blog');
        $this->addStandardFavorites();

        App::core()->behavior('adminAfterSetRteFlags')->add(function (RteFlags $rte): void {
            $rte->setFlag('widgets_text', __('Widget\'s textareas'), true);
        });

        // Load widgets stack only on widget admin page
        if (App::core()->adminurl()->is('admin.plugin.Widgets')) {
            App::core()->behavior('adminPrepend')->add(fn () => new WidgetsStack());
        }
    }

    public function installModule(): ?bool
    {
        new WidgetsStack();
        $widgets  = new Widgets();
        $settings = App::core()->blog()->settings()->getGroup('widgets');
        if (null != $settings->getSetting('widgets_nav')) {
            $settings->putSetting('widgets_nav', $widgets->load($settings->getSetting('widgets_nav'))->store());
        } else {
            $settings->putSetting('widgets_nav', '', 'string', 'Navigation widgets', false);
        }
        if (null != $settings->getSetting('widgets_extra')) {
            $settings->putSetting('widgets_extra', $widgets->load($settings->getSetting('widgets_extra'))->store());
        } else {
            $settings->putSetting('widgets_extra', '', 'string', 'Extra widgets', false);
        }
        if (null != $settings->getSetting('widgets_custom')) {
            $settings->putSetting('widgets_custom', $widgets->load($settings->getSetting('widgets_custom'))->store());
        } else {
            $settings->putSetting('widgets_custom', '', 'string', 'Custom widgets', false);
        }

        return true;
    }
}
