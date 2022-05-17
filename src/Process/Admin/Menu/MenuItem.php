<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Menu;

// Dotclear\Process\Admin\Menu\MenuItem
use Dotclear\App;
use Dotclear\Exception\AdminException;

/**
 * Admin menu item helper.
 *
 * @ingroup  Admin
 */
class MenuItem
{
    /**
     * Constructor.
     *
     * @param string       $title  The title
     * @param array|string $url    The url
     * @param array|string $img    The image
     * @param bool         $active The active flag
     * @param bool         $show   The show flag
     * @param null|string  $id     The identifier
     * @param null|string  $class  The class
     * @param bool         $pinned The pinned flag
     */
    public function __construct(
        private string $title,
        private string|array $url,
        private string|array $img,
        private bool $active = false,
        private bool $show = true,
        private ?string $id = null,
        private ?string $class = null,
        private bool $pinned = false
    ) {
        if (empty($this->title)) {
            throw new AdminException(__('Menu item has no title'));
        }
    }

    /**
     * Get menu item id.
     *
     * not really used...
     *
     * @return null|string The menu item id
     */
    public function id(): ?string
    {
        return $this->id;
    }

    /**
     * Get menu item title.
     *
     * @return string The menu item id
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Check if menu item should be displayed.
     *
     * @return bool True if the menu item should be displayed
     */
    public function show(): bool
    {
        return $this->show;
    }

    /**
     * Check if menu item is pinned.
     *
     * @return bool True if the men uitem is pinned
     */
    public function pinned(): bool
    {
        return $this->pinned;
    }

    /**
     * Get the HTML repesentation of the menu item.
     *
     * @return string The HTML code of the menu item
     */
    public function html(): string
    {
        if (is_array($this->url)) {
            $link  = $this->url[0];
            $ahtml = (!empty($this->url[1])) ? ' ' . $this->url[1] : '';
        } else {
            $link  = $this->url;
            $ahtml = '';
        }

        return
            '<li' . (($this->active || $this->class) ? ' class="' . (($this->active) ? 'active ' : '') . (($this->class) ? $this->class : '') . '"' : '') . '>' .
            '<a href="' . $link . '"' . $ahtml . '>' . App::core()->summary()->getIconTheme($this->img) . $this->title . '</a>' .
            '</li>' . "\n";
    }
}
