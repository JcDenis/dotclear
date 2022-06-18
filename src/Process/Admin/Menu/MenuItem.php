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
use Dotclear\Exception\InvalidValueReference;

/**
 * Admin menu item helper.
 *
 * @ingroup  Admin Menu Item
 */
final class MenuItem
{
    public readonly bool $active;
    public readonly bool $show;

    /**
     * Constructor.
     *
     * @param string       $title      The title
     * @param string       $url        The url
     * @param array|string $icons      The image(s)
     * @param string       $class      The menu HTML li class
     * @param string       $attributes The HTML a attributes
     * @param bool         $pinned     The pinned flag
     * @param mixed        $permission The permission callback
     * @param mixed        $activation The activation callback
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string|array $icons,
        public readonly string $class = '',
        public readonly string $attributes = '',
        public readonly bool $pinned = false,
        public readonly mixed $permission = null,
        public readonly mixed $activation = null,
    ) {
        if (empty($this->title)) {
            throw new InvalidValueReference(__('Menu item has no title'));
        }

        // Use value sets on item creation
        if (is_bool($this->activation)) {
            $active = $this->activation;
        // Use callback if defined to match whether favorite is active or not
        } elseif (!empty($this->activation) && is_callable($this->activation)) {
            $active = call_user_func($this->activation);
        // Or use called handler
        } else {
            parse_str(parse_url($this->url, PHP_URL_QUERY), $url);
            $active = App::core()->adminurl()->is($url['handler'] ?: null);
        }

        $show = App::core()->user()->isSuperAdmin();
        if (null !== $this->permission) {
            if (is_bool($this->permission)) {
                $show = $this->permission;
            } elseif (is_string($this->permission)) {
                $show = App::core()->user()->check($this->permission, App::core()->blog()->id);
            } else {
                $show = false;
            }
        }

        $this->active = (bool) $active;
        $this->show   = $show;
    }

    /**
     * Get the HTML repesentation of the menu item.
     *
     * @return string The HTML code of the menu item
     */
    public function toHTML(): string
    {
        return
            '<li' . (($this->active || $this->class) ? ' class="' . (($this->active) ? 'active ' : '') . (($this->class) ? $this->class : '') . '"' : '') . '>' .
            '<a href="' . $this->url . '"' . $this->attributes . '>' . App::core()->menu()->getIconTheme($this->icons) . $this->title . '</a>' .
            '</li>' . "\n";
    }
}
