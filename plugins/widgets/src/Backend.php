<?php
/**
 * @brief widgets, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\widgets;

use ArrayObject;
use dcAdmin;
use dcCore;
use dcFavorites;
use dcNsProcess;
use dcPage;

class Backend extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init = My::checkContext(My::BACKEND);

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        dcCore::app()->addBehaviors([
            'adminDashboardFavoritesV2' => function (dcFavorites $favs) {
                $favs->register('widgets', [
                    'title'      => __('Presentation widgets'),
                    'url'        => dcCore::app()->adminurl->get('admin.plugin.widgets'),
                    'small-icon' => [dcPage::getPF('widgets/icon.svg'), dcPage::getPF('widgets/icon-dark.svg')],
                    'large-icon' => [dcPage::getPF('widgets/icon.svg'), dcPage::getPF('widgets/icon-dark.svg')],
                ]);
            },
            'adminRteFlagsV2' => function (ArrayObject $rte) {
                $rte['widgets_text'] = [true, __('Widget\'s textareas')];
            },
        ]);

        My::backendSidebarMenuIcon(dcAdmin::MENU_BLOG);

        return true;
    }
}
