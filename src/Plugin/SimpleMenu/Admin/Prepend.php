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

    public static function loadModule(): void
    {
        static::addStandardMenu('Blog');
        static::addStandardFavorites();

        # Widgets
        if (dotclear()->adminurl->called() == 'admin.plugin.Widgets') {
            new SimpleMenuWidgets();
        }
    }

    public static function installModule(): ?bool
    {
        # Menu par défaut
        $blog_url     = Html::stripHostURL(dotclear()->blog()->url);
        $menu_default = [
            ['label' => 'Home', 'descr' => 'Recent posts', 'url' => $blog_url, 'targetBlank' => false],
            ['label' => 'Archives', 'descr' => '', 'url' => $blog_url . dotclear()->url()->getURLFor('archive'), 'targetBlank' => false]
        ];
        dotclear()->blog()->settings->system->put('simpleMenu', $menu_default, 'array', 'simpleMenu default menu', false, true);
        dotclear()->blog()->settings->system->put('simpleMenu_active', true, 'boolean', 'Active', false, true);

        return true;
    }
}
