<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Favorite;

// Dotclear\Process\Admin\Favorite\DashboardIcons
use Dotclear\App;

/**
 * Admin favorites dahsboard icons.
 *
 * @ingroup  Admin Favorite
 */
class DashboardIcons
{
    /**
     * @var array<string,DashboardIcon> $icons
     *                                  The dashboard icons
     */
    private $icons;

    /**
     * Constructor.
     *
     * Add user favorite dashboard icons
     */
    public function __construct()
    {
        foreach (App::core()->favorite()->getUserItems() as $item) {
            $icon = new DashboardIcon(
                id: $item->id,
                title: $item->title,
                url: $item->url,
                icons: $item->icons,
            );

            if (!empty($item->dashboard) && is_callable($item->dashboard)) {
                call_user_func($item->dashboard, $icon);
            }

            App::core()->behavior('adminBeforeAddDashboardIcon')->call(icon: $icon);

            $this->addIcon(icon: $icon);
        }
    }

    /**
     * Add a dashboard icon.
     */
    public function addIcon(DashboardIcon $icon)
    {
        $this->icons[$icon->id] = $icon;
    }

    /**
     * Get the HTML representation of the dashboard icons.
     *
     * @return string The HTML representation of the dashboard icons
     */
    public function toHtml()
    {
        $res = '';
        foreach ($this->icons as $icon) {
            $res .= $icon->toHtml();
        }

        return '<div id="icons">' . $res . '</div>';
    }
}
