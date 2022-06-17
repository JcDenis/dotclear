<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Favorite;

// Dotclear\Process\Admin\Favorite\DashboardIcon
use Dotclear\App;

/**
 * Admin favorites dahsboard icon.
 *
 * @ingroup  Admin Favorite
 */
class DashboardIcon
{
    /**
     * Constructor.
     *
     * @param string       $id    The dahsboard icon ID
     * @param string       $title The dashboard icon title
     * @param string       $url   The dashboard icon URL
     * @param array|string $icons The dashboard icon icons
     */
    public function __construct(
        public readonly string $id,
        private string $title,
        private string $url,
        private string|array $icons
    ) {
    }

    /**
     * Append a title part to existing dashboard icon title.
     *
     * @param string $title The dahsboard icon title part to add
     */
    public function appendTitle(string $title)
    {
        $this->title .= $title;
    }

    /**
     * Replace the dashboard icon title.
     *
     * @param string $title The new dahsboard icon title
     */
    public function replaceTitle(string $title)
    {
        $this->title = $title;
    }

    /**
     * Replace dashboard icon icons.
     *
     * @param array|string $icons The new dashboard icon icons
     */
    public function replaceIcons(string|array $icons)
    {
        $this->icons = $icons;
    }

    /**
     * Get th eHTML repesentation of the dashboard icon.
     *
     * @return string The HTML representation of the dashboard icon
     */
    public function toHtml()
    {
        return '<p id="db-icon-' . $this->id . '"><a href="' . $this->url . '">' . App::core()->menus()->getIconTheme($this->icons) .
                '<br /><span class="db-icon-title">' . $this->title . '</span></a></p>';
    }
}
