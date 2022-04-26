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
use ArrayObject;
use Dotclear\App;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Widgets\Common\Widgets;
use Dotclear\Plugin\Widgets\Common\WidgetsStack;

/**
 * Admin prepend for plugin Widgets.
 *
 * @ingroup  Plugin Widgets
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        // Menu and Favorties
        $this->addStandardMenu('Blog');
        $this->addStandardFavorites();

        // Load widgets stack only on widget admin page
        if (App::core()->adminurl()->is('admin.plugin.Widgets')) {
            App::core()->behavior()->add('adminRteFlags', function (ArrayObject $rte): void {
                $rte['widgets_text'] = [true, __('Widget\'s textareas')];
            });
            App::core()->behavior()->add('adminPrepend', fn () => new WidgetsStack());
        }
    }

    public function installModule(): ?bool
    {
        $widgets  = new Widgets();
        $settings = App::core()->blog()->settings();
        if (null != $settings->get('widgets')->get('widgets_nav')) {
            $settings->get('widgets')->put('widgets_nav', $widgets->load($settings->get('widgets')->get('widgets_nav'))->store());
        } else {
            $settings->get('widgets')->put('widgets_nav', '', 'string', 'Navigation widgets', false);
        }
        if (null != $settings->get('widgets')->get('widgets_extra')) {
            $settings->get('widgets')->put('widgets_extra', $widgets->load($settings->get('widgets')->get('widgets_extra'))->store());
        } else {
            $settings->get('widgets')->put('widgets_extra', '', 'string', 'Extra widgets', false);
        }
        if (null != $settings->get('widgets')->get('widgets_custom')) {
            $settings->get('widgets')->put('widgets_custom', $widgets->load($settings->get('widgets')->get('widgets_custom'))->store());
        } else {
            $settings->get('widgets')->put('widgets_custom', '', 'string', 'Custom widgets', false);
        }

        return true;
    }
}
