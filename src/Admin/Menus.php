<?php
/**
 * @class Dotclear\Admin\Favorites
 * @brief Dotclear admin favorites handling facilities class
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use function Dotclear\core;

use ArrayObject;

use Dotclear\Admin\Menu;

use Dotclear\File\Files;
use Dotclear\File\Path;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Menus extends ArrayObject
{
    protected $core;
    public static $iconset;

    public function __construct()
    {
        if (!self::$iconset) {
            self::$iconset   = (string) @core()->auth->user_prefs->interface->iconset;
        }

        parent::__construct();

        $this->add('Dashboard', 'dashboard-menu', '');
        if (!core()->auth->user_prefs->interface->nofavmenu) {
            core()->favs->appendMenuTitle($this);
        }
        $this->add('Blog', 'blog-menu', __('Blog'));
        $this->add('System', 'system-menu', __('System settings'));
        $this->add('Plugins', 'plugins-menu', __('Miscellaneous'));
    }

    /**
     * Add a menu
     *
     * @param   string  $name       The menu name
     * @param   string  $id         The menu id
     * @param   string  $title      The menu title
     * @param   string  $itemSpace
     */
    public function add(string $name, string $id, string $title, string $itemSpace = ''): void
    {
        $this->offsetSet($name, new Menu($id, $title, $itemSpace));
    }

    /**
     * Add a menu item.
     *
     * @param   string  $section    The section
     * @param   string  $desc       The description
     * @param   string  $adminurl   The adminurl
     * @param   mixed   $icon       The icon(s)
     * @param   mixed   $perm       The permission
     * @param   bool    $pinned     The pinned
     * @param   bool    $strict     The strict
     */
    public function register($section, $desc, $adminurl, $icon, $perm, $pinned = false, $strict = false): void
    {
        $match = core()->adminurl->called() == $adminurl;
        if ($strict && $match) {
            $match = count($_GET) == 1;
        }

        $this->offsetGet($section)->prependItem(
            $desc,
            core()->adminurl->get($adminurl),
            $icon,
            $match,
            $perm,
            null,
            null,
            $pinned
        );
    }

    /**
     * Compose HTML icon markup for favorites, menu, … depending on theme (light, dark)
     *
     * @param   mixed   $img        string (default) or array (0 : light, 1 : dark)
     * @param   bool    $fallback   use fallback image if none given
     * @param   string  $alt        alt attribute
     * @param   string  $title      title attribute
     *
     * @return  string
     */
    public function getIconTheme($img, $fallback = true, $alt = '', $title = '', $class = '')
    {
        $unknown_img = 'images/menu/no-icon.svg';
        $dark_img    = '';
        if (is_array($img)) {
            $light_img = $img[0] ?: ($fallback ? $unknown_img : '');   // Fallback to no icon if necessary
            if (isset($img[1]) && $img[1] !== '') {
                $dark_img = $img[1];
            }
        } else {
            $light_img = $img ?: ($fallback ? $unknown_img : '');  // Fallback to no icon if necessary
        }

        $title = $title !== '' ? ' title="' . $title . '"' : '';
        if ($light_img !== '' && $dark_img !== '') {
            $icon = '<img src="' . $this->getIconURL($light_img) .
            '" class="light-only' . ($class !== '' ? ' ' . $class : '') . '" alt="' . $alt . '"' . $title . ' />' .
                '<img src="' . $this->getIconURL($dark_img) .
            '" class="dark-only' . ($class !== '' ? ' ' . $class : '') . '" alt="' . $alt . '"' . $title . ' />';
        } elseif ($light_img !== '') {
            $icon = '<img src="' . $this->getIconURL($light_img) .
            '" class="' . ($class !== '' ? $class : '') . '" alt="' . $alt . '"' . $title . ' />';
        } else {
            $icon = '';
        }

        return $icon;
    }

    /**
     * Parse icon path and url from Iconset
     *
     * This can't call "Iconset" nor behaviors as modules are not loaded yet.
     * This use self::$iconset that content full path to iconset icons.
     *
     * @param   string  $img    Image path
     *
     * @return  string          New image path
     */
    public function getIconURL(string|array $img): string
    {
        $allow_types = ['svg', 'png', 'webp', 'jpg', 'jpeg', 'gif'];
        if (!empty(self::$iconset) && !empty($img)) {

            # Extract module name from path
            $split  = explode('/', self::$iconset);
            $module = array_pop($split);
            if ((preg_match('/^images\/menu\/(.+)(\..*)$/', $img, $m)) || (preg_match('/\?mf=(.+)(\..*)$/', $img, $m))) {
                $name = $m[1] ?? '';
                $ext  = $m[2] ?? '';
                if ($name !== '' && $ext !== '') {
                    $icon = Path::real(self::$iconset . '/files/' . $name . $ext, true);
                    if ($icon !== false) {
                        # Find same (name and extension)
                        if (is_file($icon) && is_readable($icon) && in_array(Files::getExtension($icon), $allow_types)) {
                            return '?mf=Iconset/' . $module . '/files/' . $name . $ext;
                        }
                    }
                    # Look for other extensions
                    foreach ($allow_types as $ext) {
                        $icon = Path::real(self::$iconset . '/files/' . $name . '.' . $ext, true);
                        if ($icon !== false) {
                            if (is_file($icon) && is_readable($icon)) {
                                return '?mf=Iconset/' . $module . '/files/' . $name . '.' . $ext;
                            }
                        }
                    }
                    /*
                    # Not in iconset nor in Dotclear
                    $icon = Path::real(core()::path(DOTCLEAR_ROOT_DIR, 'Admin', 'files', $img));
                    if ($icon === false || !is_file($icon) || !is_readable($icon)) {
                        $img = 'images/menu/no-icon.svg';
                    }
                    //*/
                }
            }
        }

        # By default use Dotclear Admin files
        if (strpos($img, '?') === false) {
            $img = '?df=' . $img;
        }

        return $img;
    }

    public function setup()
    {
        $this->initDefaultMenus();
        core()->behaviors->call('adminMenus', $this);
    }

    protected function initDefaultMenus()
    {
        # add fefault items to menu
        $this->register(
            'Blog',
            __('Blog settings'),
            'admin.blog.pref',
            ['images/menu/blog-pref.svg', 'images/menu/blog-pref-dark.svg'],
            core()->auth->check('admin', core()->blog->id)
        );
        $this->register(
            'Blog',
            __('Media manager'),
            'admin.media',
            ['images/menu/media.svg', 'images/menu/media-dark.svg'],
            core()->auth->check('media,media_admin', core()->blog->id)
        );
        $this->register(
            'Blog',
            __('Categories'),
            'admin.categories',
            ['images/menu/categories.svg', 'images/menu/categories-dark.svg'],
            core()->auth->check('categories', core()->blog->id)
        );
        $this->register(
            'Blog',
            __('Search'),
            'admin.search',
            ['images/menu/search.svg','images/menu/search-dark.svg'],
            core()->auth->check('usage,contentadmin', core()->blog->id)
        );
        $this->register(
            'Blog',
            __('Comments'),
            'admin.comments',
            ['images/menu/comments.svg', 'images/menu/comments-dark.svg'],
            core()->auth->check('usage,contentadmin', core()->blog->id)
        );
        $this->register(
            'Blog',
            __('Posts'),
            'admin.posts',
            ['images/menu/entries.svg', 'images/menu/entries-dark.svg'],
            core()->auth->check('usage,contentadmin', core()->blog->id)
        );
        $this->register(
            'Blog',
            __('New post'),
            'admin.post',
             ['images/menu/edit.svg', 'images/menu/edit-dark.svg'],
            core()->auth->check('usage,contentadmin', core()->blog->id),
            true,
            true
        );

        $this->register(
            'System',
            __('Update'),
            'admin.update',
            ['images/menu/update.svg', 'images/menu/update-dark.svg'],
            core()->auth->isSuperAdmin() && is_readable(DOTCLEAR_DIGESTS_DIR)
        );
        $this->register(
            'System',
            __('Languages'),
            'admin.langs',
            ['images/menu/langs.svg', 'images/menu/langs-dark.svg'],
            core()->auth->isSuperAdmin()
        );
        $this->register(
            'System',
            __('Users'),
            'admin.users',
            'images/menu/users.svg',
            core()->auth->isSuperAdmin()
        );
        $this->register(
            'System',
            __('Blogs'),
            'admin.blogs',
            ['images/menu/blogs.svg', 'images/menu/blogs-dark.svg'],
            core()->auth->isSuperAdmin() || core()->auth->check('usage,contentadmin', core()->blog->id) && core()->auth->getBlogCount() > 1
        );
    }
}
