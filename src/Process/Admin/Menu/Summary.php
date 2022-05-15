<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Menu;

// Dotclear\Process\Admin\Menu\Summary
use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;

/**
 * Admin menu handling facilities.
 *
 * Accessible from App::core()->summary()->
 *
 * @ingroup  Admin
 */
class Summary extends ArrayObject
{
    public function __construct()
    {
        parent::__construct();

        $this->add('Dashboard', 'dashboard-menu', '');
        if (!App::core()->user()->preference()->get('interface')->get('nofavmenu')) {
            App::core()->favorite()->appendMenuTitle($this);
        }
        $this->add('Blog', 'blog-menu', __('Blog'));
        $this->add('System', 'system-menu', __('System settings'));
        $this->add('Plugins', 'plugins-menu', __('Miscellaneous'));
    }

    /**
     * Add a menu.
     *
     * This create a Menu instance
     *
     * @param string $name      The menu name
     * @param string $id        The menu id
     * @param string $title     The menu title
     * @param string $itemSpace The item space
     */
    public function add(string $name, string $id, string $title, string $itemSpace = ''): void
    {
        $this->offsetSet($name, new Menu($id, $title, $itemSpace));
    }

    /**
     * Add a menu item.
     *
     * @param string $section  The section
     * @param string $desc     The description
     * @param string $adminurl The adminurl
     * @param mixed  $icon     The icon(s)
     * @param mixed  $perm     The permission
     * @param bool   $pinned   The pinned
     * @param bool   $strict   The strict
     */
    public function register($section, $desc, $adminurl, $icon, $perm, $pinned = false, $strict = false): void
    {
        $match = App::core()->adminurl()->is($adminurl);
        if ($strict && $match) {
            $match = 1 == count($_GET);
        }

        $this->offsetGet($section)->prependItem(
            $desc,
            App::core()->adminurl()->get($adminurl),
            $icon,
            $match,
            $perm,
            null,
            null,
            $pinned
        );
    }

    /**
     * Compose HTML icon markup for favorites, menu.
     * 
     * Icon changes according to theme light or dark).
     * Icon must be accessible from Amdin URL handler, 
     * but $img must not contain "?df=".
     *
     * @param mixed  $img      string (default) or array (0 : light, 1 : dark)
     * @param bool   $fallback use fallback image if none given
     * @param string $alt      alt attribute
     * @param string $title    title attribute
     * @param mixed  $class
     *
     * @return string
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
                '<img src="?df=' . $dark_img . '" class="dark-only' . ('' !== $class ? ' ' . $class : '') . '" alt="' . $alt . '"' . $title . ' />';
        } elseif ('' !== $light_img) {
            $icon = '<img src="?df=' . $light_img . '" class="' . ('' !== $class ? $class : '') . '" alt="' . $alt . '"' . $title . ' />';
        } else {
            $icon = '';
        }

        return $icon;
    }

    public function setup()
    {
        $this->initDefaultMenus();
        App::core()->behavior()->call('adminMenus', $this);
    }

    protected function initDefaultMenus()
    {
        // add fefault items to menu
        $this->register(
            'Blog',
            __('Blog settings'),
            'admin.blog.pref',
            ['images/menu/blog-pref.svg', 'images/menu/blog-pref-dark.svg'],
            App::core()->user()->check('admin', App::core()->blog()->id)
        );
        if (App::core()->blog()->public_path) {
            $this->register(
                'Blog',
                __('Media manager'),
                'admin.media',
                ['images/menu/media.svg', 'images/menu/media-dark.svg'],
                App::core()->user()->check('media,media_admin', App::core()->blog()->id)
            );
        }
        $this->register(
            'Blog',
            __('Categories'),
            'admin.categories',
            ['images/menu/categories.svg', 'images/menu/categories-dark.svg'],
            App::core()->user()->check('categories', App::core()->blog()->id)
        );
        $this->register(
            'Blog',
            __('Search'),
            'admin.search',
            ['images/menu/search.svg', 'images/menu/search-dark.svg'],
            App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)
        );
        $this->register(
            'Blog',
            __('Comments'),
            'admin.comments',
            ['images/menu/comments.svg', 'images/menu/comments-dark.svg'],
            App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)
        );
        $this->register(
            'Blog',
            __('Posts'),
            'admin.posts',
            ['images/menu/entries.svg', 'images/menu/entries-dark.svg'],
            App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)
        );
        $this->register(
            'Blog',
            __('New post'),
            'admin.post',
            ['images/menu/edit.svg', 'images/menu/edit-dark.svg'],
            App::core()->user()->check('usage,contentadmin', App::core()->blog()->id),
            true,
            true
        );

        $this->register(
            'System',
            __('Update'),
            'admin.update',
            ['images/menu/update.svg', 'images/menu/update-dark.svg'],
            App::core()->user()->isSuperAdmin() && is_readable(App::core()->config()->get('digests_dir'))
        );
        $this->register(
            'System',
            __('Languages'),
            'admin.langs',
            ['images/menu/langs.svg', 'images/menu/langs-dark.svg'],
            App::core()->user()->isSuperAdmin()
        );
        $this->register(
            'System',
            __('Users'),
            'admin.users',
            'images/menu/users.svg',
            App::core()->user()->isSuperAdmin()
        );
        $this->register(
            'System',
            __('Blogs'),
            'admin.blogs',
            ['images/menu/blogs.svg', 'images/menu/blogs-dark.svg'],
            App::core()->user()->isSuperAdmin() || App::core()->user()->check('usage,contentadmin', App::core()->blog()->id) && 1 < App::core()->user()->getBlogCount()
        );
    }
}
