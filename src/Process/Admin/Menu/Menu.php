<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Menu;

// Dotclear\Process\Admin\Menu\Menu
use Dotclear\App;
use Dotclear\Helper\GPC\GPC;

/**
 * Admin menu handling facilities.
 *
 * Accessible from App::core()->menu()
 *
 * @ingroup  Admin Menu
 */
final class Menu
{
    /**
     * @var array<string,MenuGroup> $stack
     *                              The menu list
     */
    private $stack = [];

    /**
     * Constructor.
     *
     * Set up sections.
     */
    public function __construct()
    {
        $this->addGroup(new MenuGroup(
            section: 'Dashboard',
            id: 'dashboard-menu',
            title: ''
        ));
        if (!App::core()->user()->preferences('interface')->getPreference('nofavmenu')) {
            $this->addGroup(new MenuGroup(
                section: 'Favorites',
                id: 'favorites-menu',
                title: __('My favorites')
            ));
        }
        $this->addGroup(new MenuGroup(
            section: 'Blog',
            id: 'blog-menu',
            title: __('Blog')
        ));
        $this->addGroup(new MenuGroup(
            section: 'System',
            id: 'system-menu',
            title: __('System settings')
        ));
        $this->addGroup(new MenuGroup(
            section: 'Plugins',
            id: 'plugins-menu',
            title: __('Miscellaneous')
        ));
    }

    /**
     * Add a menu.
     *
     * @param MenuGroup $menu The menu group
     */
    public function addGroup(MenuGroup $menu): void
    {
        $this->stack[$menu->section] = $menu;
    }

    /**
     * Get a menu.
     *
     * @param string $section The menu name
     *
     * @return null|MenuGroup The menu instance or null if not exists
     */
    public function getGroup(string $section): ?MenuGroup
    {
        return $this->stack[$section] ?? null;
    }

    /**
     * Get all menus.
     *
     * @return array<string,MenuGroup> The menu list
     */
    public function getGroups(): array
    {
        return $this->stack;
    }

    /**
     * Compose HTML icon markup for favorites, menu.
     *
     * Icon changes according to theme light or dark).
     * Icon must be accessible from Amdin resources handler,
     * but $img must not contain "?df=".
     *
     * @param mixed  $img      string (default) or array (0 : light, 1 : dark)
     * @param bool   $fallback use fallback image if none given
     * @param string $alt      alt attribute
     * @param string $title    title attribute
     * @param mixed  $class
     *
     * @return string The icon HTML markup
     */
    public function getIconTheme($img, $fallback = true, $alt = '', $title = '', $class = '')
    {
        $unknown_img = 'images/menu/no-icon.svg';
        $dark_img    = '';
        if (is_array($img)) {
            $light_img = $img[0] ?: ($fallback ? $unknown_img : '');   // Fallback to no icon if necessary
            if (isset($img[1]) && '' !== $img[1]) {
                $dark_img = $img[1];
            }
        } else {
            $light_img = $img ?: ($fallback ? $unknown_img : '');  // Fallback to no icon if necessary
        }

        $title = '' !== $title ? ' title="' . $title . '"' : '';
        if ('' !== $light_img && '' !== $dark_img) {
            $icon = '<img src="?df=' . $light_img . '" class="light-only' . ('' !== $class ? ' ' . $class : '') . '" alt="' . $alt . '"' . $title . ' />' .
                '<img src="?df=' . $dark_img . '" class="dark-only' . (''       !== $class ? ' ' . $class : '') . '" alt="' . $alt . '"' . $title . ' />';
        } elseif ('' !== $light_img) {
            $icon = '<img src="?df=' . $light_img . '" class="' . ('' !== $class ? $class : '') . '" alt="' . $alt . '"' . $title . ' />';
        } else {
            $icon = '';
        }

        return $icon;
    }

    /**
     * Populate menus.
     *
     * This method should be called only from Admin Prepend.
     */
    public function setDefaultItems(): void
    {
        $this->getGroup('Blog')->addItem(new MenuItem(
            title: __('Blog settings'),
            url: App::core()->adminurl()->get('admin.blog.pref'),
            icons: ['images/menu/blog-pref.svg', 'images/menu/blog-pref-dark.svg'],
            permission: 'admin',
        ));
        if (App::core()->blog()->public_path) {
            $this->getGroup('Blog')->addItem(new MenuItem(
                title: __('Media manager'),
                url: App::core()->adminurl()->get('admin.media'),
                icons: ['images/menu/media.svg', 'images/menu/media-dark.svg'],
                permission: 'media,media_admin',
            ));
        }
        $this->getGroup('Blog')->addItem(new MenuItem(
            title: __('Categories'),
            url: App::core()->adminurl()->get('admin.categories'),
            icons: ['images/menu/categories.svg', 'images/menu/categories-dark.svg'],
            permission: 'categories',
        ));
        $this->getGroup('Blog')->addItem(new MenuItem(
            title: __('Search'),
            url: App::core()->adminurl()->get('admin.search'),
            icons: ['images/menu/search.svg', 'images/menu/search-dark.svg'],
            permission: 'usage,contentadmin',
        ));
        $this->getGroup('Blog')->addItem(new MenuItem(
            title: __('Comments'),
            url: App::core()->adminurl()->get('admin.comments'),
            icons: ['images/menu/comments.svg', 'images/menu/comments-dark.svg'],
            permission: 'usage,contentadmin',
        ));
        $this->getGroup('Blog')->addItem(new MenuItem(
            title: __('Posts'),
            url: App::core()->adminurl()->get('admin.posts'),
            icons: ['images/menu/entries.svg', 'images/menu/entries-dark.svg'],
            permission: 'usage,contentadmin',
        ));
        $this->getGroup('Blog')->addItem(new MenuItem(
            title: __('New post'),
            url: App::core()->adminurl()->get('admin.post'),
            icons: ['images/menu/edit.svg', 'images/menu/edit-dark.svg'],
            permission: 'usage,contentadmin',
            activation: App::core()->adminurl()->is('admin.post') && 1 == GPC::get()->count(),
            pinned: true
        ));
        $this->getGroup('System')->addItem(new MenuItem(
            title: __('Update'),
            url: App::core()->adminurl()->get('admin.update'),
            icons: ['images/menu/update.svg', 'images/menu/update-dark.svg'],
            permission: App::core()->user()->isSuperAdmin() && is_readable(App::core()->config()->get('digests_dir')),
        ));
        $this->getGroup('System')->addItem(new MenuItem(
            title: __('Languages'),
            url: App::core()->adminurl()->get('admin.langs'),
            icons: ['images/menu/langs.svg', 'images/menu/langs-dark.svg'],
        ));
        $this->getGroup('System')->addItem(new MenuItem(
            title: __('Users'),
            url: App::core()->adminurl()->get('admin.users'),
            icons: 'images/menu/users.svg',
        ));
        $this->getGroup('System')->addItem(new MenuItem(
            title: __('Blogs'),
            url: App::core()->adminurl()->get('admin.blogs'),
            icons: ['images/menu/blogs.svg', 'images/menu/blogs-dark.svg'],
            permission: App::core()->user()->isSuperAdmin() || App::core()->user()->check('usage,contentadmin', App::core()->blog()->id) && 1 < App::core()->user()->getBlogCount(),
        ));

        // Add default top menus (favorites)
        if (!App::core()->user()->preferences('interface')->getPreference('nofavmenu')) {
            foreach (App::core()->favorite()->getUserItems() as $item) {
                $this->getGroup('Favorites')->addItem(new MenuItem(
                    title: $item->title,
                    url: $item->url,
                    icons: $item->icons,
                    activation: $item->active,
                    permission: true,
                    pinned: true,
                ));
            }
        }
    }
}
