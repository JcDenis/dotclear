<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Admin;

// Dotclear\Plugin\SimpleMenu\Admin\Prepend
use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\SimpleMenu\Common\SimpleMenuWidgets;

/**
 * Admin prepend for plugin SimpleMenu.
 *
 * @ingroup  Plugin SimpleMenu
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        $this->addStandardMenu('Blog');
        $this->addStandardFavorites();

        // Widgets
        if (App::core()->adminurl()->is('admin.plugin.Widgets')) {
            new SimpleMenuWidgets();
        }
    }

    public function installModule(): ?bool
    {
        // Menu par défaut
        $blog_url     = Html::stripHostURL(App::core()->blog()->url);
        $menu_default = [
            ['label' => 'Home', 'descr' => 'Recent posts', 'url' => $blog_url, 'targetBlank' => false],
            ['label' => 'Archives', 'descr' => '', 'url' => $blog_url . App::core()->url()->getURLFor('archive'), 'targetBlank' => false],
        ];
        App::core()->blog()->settings()->get('system')->put('simpleMenu', $menu_default, 'array', 'simpleMenu default menu', false, true);
        App::core()->blog()->settings()->get('system')->put('simpleMenu_active', true, 'boolean', 'Active', false, true);

        return true;
    }
}
