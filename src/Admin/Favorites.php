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

use ArrayObject;

use Dotclear\Core\Core;

use Dotclear\Admin\Menus;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Favorites
{
    /** @var    Core    Core instance */
    protected $core;

    /** @var    ArrayObject     list of favorite definitions  */
    protected $fav_defs;

    /** @var    Workspace   current favorite landing workspace */
    protected $ws;

    /** @var    array   list of user-defined favorite ids */
    protected $local_prefs;

    /** @var    array   list of globally-defined favorite ids */
    protected $global_prefs;

    /** @var    array   list of user preferences (either one of the 2 above, or not!) */
    protected $user_prefs;

    /** @var    array  Default favorites values */
    protected $default_favorites = [
        # favorite title (localized)
        'title'        => '',
        # favorite URL
        'url'          => '',
        # favorite small icon (for menu)
        'small-icon'   => 'images/menu/no-icon.svg',
        # favorite large icon (for dashboard)
        'large-icon'   => 'images/menu/no-icon.svg',
        # (optional) comma-separated list of permissions for thie fav, if not set : no restriction
        'permissions'  => '',
        # (optional) callback to modify title if dynamic, if not set : title is taken as is
        'dashboard_cb' => '',
        #  (optional) callback to tell whether current page matches favorite or not, for complex pages
        'active_cb'    => '',
    ];

    /**
     * Class constructor
     *
     * @param   Core    $core   Core instance
     */
    public function __construct(Core $core)
    {
        $this->core       = $core;
        $this->fav_defs   = new ArrayObject();
        $this->ws         = $core->auth->user_prefs->addWorkspace('dashboard');
        $this->user_prefs = [];

        if ($this->ws->prefExists('favorites')) {
            $this->local_prefs  = $this->ws->getLocal('favorites');
            $this->global_prefs = $this->ws->getGlobal('favorites');
            // Since we never know what user puts through user:preferences ...
            if (!is_array($this->local_prefs)) {
                $this->local_prefs = [];
            }
            if (!is_array($this->global_prefs)) {
                $this->global_prefs = [];
            }
        } else {
            // No favorite defined ? Huhu, let's go for a migration
            $this->migrateFavorites();
        }
    }

    /**
     * Sets up favorites
     *
     * Fetch user favorites (against his permissions)
     * This method is to be called after loading plugins
     */
    public function setup(): void
    {
        $this->initDefaultFavorites();
        $this->core->behaviors->call('adminDashboardFavorites', $this);
        $this->setUserPrefs();
    }

    /**
     * Get Favorite
     *
     * Retrieves a favorite (complete description) from its id.
     *
     * @param   string|array    $p  The favorite id, or an array having 1 key 'name' set to id, ther keys are merged to favorite.
     *
     * @return  array               The favorite
     */
    public function getFavorite(string|array $p): array
    {
        if (is_array($p)) {
            $fname = $p['name'];
            if (!isset($this->fav_defs[$fname])) {
                return [];
            }
            $fattr = $p;
            unset($fattr['name']);
            $fattr = array_merge($this->fav_defs[$fname], $fattr);
        } else {
            if (!isset($this->fav_defs[$p])) {
                return [];
            }
            $fattr = $this->fav_defs[$p];
        }
        $fattr = array_merge(['id' => null, 'class' => null], $fattr);
        if (isset($fattr['permissions'])) {
            if (is_bool($fattr['permissions']) && !$fattr['permissions']) {
                return [];
            }
            if (!$this->core->auth->check($fattr['permissions'], $this->core->blog->id)) {
                return [];
            }
        } elseif (!$this->core->auth->isSuperAdmin()) {
            return [];
        }

        return $fattr;
    }

    /**
     * Get Favorites
     *
     * Retrieves a list of favorites.
     *
     * @param   array   $ids    An array of ids, as defined in getFavorite.
     *
     * @return  array           The favorites, can be empty if ids are not found (or not permitted)
     */
    public function getFavorites(array $ids): array
    {
        $prefs = [];
        foreach ($ids as $id) {
            $f = $this->getFavorite($id);
            if (!empty($f)) {
                $prefs[$id] = $f;
            }
        }

        return $prefs;
    }

    /**
     * Set user prefs
     *
     * Get user favorites from settings.
     * These are complete favorites, not ids only
     * returned favorites are the first non-empty list from :
     *    * user-defined favorites
     *    * globally-defined favorites
     *    * a failback list "new post" (shall never be empty)
     * This method is called by ::setup()
     */
    protected function setUserPrefs(): void
    {
        $this->user_prefs = $this->getFavorites($this->local_prefs);
        if (empty($this->user_prefs)) {
            $this->user_prefs = $this->getFavorites($this->global_prefs);
        }
        if (empty($this->user_prefs)) {
            $this->user_prefs = $this->getFavorites(['new_post']);
        }
        $uri = explode('?', $_SERVER['REQUEST_URI']);
        // take only last part of the URI, all plugins work like that
        $uri[0] = preg_replace('#(.*?)([^/]+)$#', '$2', $uri[0]);
        // Loop over prefs to enable active favorites
        foreach ($this->user_prefs as $k => &$v) {
            // duplicate request URI on each loop as it takes previous pref value ?!
            $u = $uri;
            if (!empty($v['active_cb']) && is_callable($v['active_cb'])) {
                // Use callback if defined to match whether favorite is active or not
                $v['active'] = call_user_func($v['active_cb'], $u[0], $_REQUEST);
            } else {
                // Failback active detection. We test against URI name & parameters
                $v['active'] = true; // true until something proves it is false
                $u           = explode('?', $v['url'], 2);
                if (!preg_match('/' . preg_quote($u[0], '/') . '/', $_SERVER['REQUEST_URI'])) {
                    $v['active'] = false; // no URI match
                }
                if (count($u) == 2) {
                    parse_str($u[1], $p);
                    // test against each request parameter.
                    foreach ($p as $k2 => $v2) {
                        if (!isset($_REQUEST[$k2]) || $_REQUEST[$k2] !== $v2) {
                            $v['active'] = false;
                        }
                    }
                }
            }
        }
    }

    /**
     * Migrate Favorites
     *
     * Migrate dc < 2.6 favorites to new format
     */
    protected function migrateFavorites(): void
    {
        $fav_ws             = $this->core->auth->user_prefs->addWorkspace('favorites');
        $this->local_prefs  = [];
        $this->global_prefs = [];
        foreach ($fav_ws->dumpPrefs() as $k => $v) {
            $fav = @unserialize($v['value']);
            if (is_array($fav)) {
                if ($v['global']) {
                    $this->global_prefs[] = $fav['name'];
                } else {
                    $this->local_prefs[] = $fav['name'];
                }
            }
        }
        $this->ws->put('favorites', $this->global_prefs, 'array', 'User favorites', true, true);
        $this->ws->put('favorites', $this->local_prefs);
        $this->user_prefs = $this->getFavorites($this->local_prefs);
    }

    /**
     * Get user favorites
     *
     * Returns favorites that correspond to current user
     * (may be local, global, or failback favorites)
     *
     * @return  array   Array of favorites (enriched)
     */
    public function getUserFavorites(): array
    {
        return $this->user_prefs;
    }

    /**
     * Get Favorite IDs
     *
     * Returns user-defined or global favorites ids list
     * shall not be called outside Admin\Page\UserPrefs
     *
     * @param   bool    $global     If true, retrieve global favs, user favs otherwise
     *
     * @return  array               Array of favorites ids (only ids, not enriched)
     */
    public function getFavoriteIDs(bool $global = false): array
    {
        return $global ? $this->global_prefs : $this->local_prefs;
    }

    /**
     * Set Favorite IDs
     *
     * Stores user-defined or global favorites ids list
     * shall not be called outside Admin\Page\UserPrefs
     *
     * @param   array   $ids        List of fav ids
     * @param   bool    $global     If true, retrieve global favs, user favs otherwise
     */
    public function setFavoriteIDs(array $ids, bool $global = false): void
    {
        $this->ws->put('favorites', $ids, 'array', null, true, $global);
    }

    /**
     * Get Available Favorites IDs
     *
     * Returns all available fav ids
     *
     * @return  array   Array of favorites ids (only ids, not enriched)
     */
    public function getAvailableFavoritesIDs(): array
    {
        return array_keys($this->fav_defs->getArrayCopy()); // @phpstan-ignore-line
    }

    /**
     * Append Menu Title
     *
     * Adds favorites section title to sidebar menu
     * shall not be called outside Admin\Prepend...
     *
     * @param   Menus   $menu   Menus instance
     */
    public function appendMenuTitle(Menus $menu): void
    {
        $menu->add('Favorites', 'favorites-menu', __('My favorites'));
    }

    /**
     * Append Menu
     *
     * Adds favorites items title to sidebar menu
     * shall not be called outside Admin\Prepend...
     *
     * @param   Menus   $menu   Menus instance
     */
    public function appendMenu(Menus $menu): void
    {
        foreach ($this->user_prefs as $k => $v) {
            $menu['Favorites']->addItem(
                $v['title'],
                $v['url'],
                $v['small-icon'],
                $v['active'],
                true,
                $v['id'],
                $v['class'],
                true
            );
        }
    }

    /**
     * Append Dashboard Icons
     *
     * Adds favorites icons to index page
     * shall not be called outside Admin\Page\Home
     *
     * @param   ArrayObject     $icons  Dashboard icon list to enrich
     */
    public function appendDashboardIcons(ArrayObject $icons): void
    {
        foreach ($this->user_prefs as $k => $v) {
            if (!empty($v['dashboard_cb']) && is_callable($v['dashboard_cb'])) {
                $v = new ArrayObject($v);
                call_user_func($v['dashboard_cb'], $this->core, $v);
            }
            $icons[$k] = new ArrayObject([$v['title'], $v['url'], $v['large-icon']]);
            $this->core->behaviors->call('adminDashboardFavsIcon', $k, $icons[$k]);
        }
    }

    /**
     * Register
     *
     * Registers a new favorite definition
     *
     * @param   string  $id     Favorite id
     * @param   array   $data   Favorite information. @see self::$default_favorites
     *
     * @return Favorites instance
     */
    public function register(string $id, array $data): Favorites
    {
        $this->fav_defs[$id] = array_merge($this->default_favorites, $data);

        return $this;
    }

    /**
     * Register Multiple
     *
     * Registers a list of favorites definition
     *
     * @see self::register()
     *
     * @param   array   $data an array defining all favorites key is the id, value is the data.
     *
     * @return Favorites instance
     */
    public function registerMultiple(array $data): Favorites
    {
        foreach ($data as $k => $v) {
            $this->register($k, $v);
        }

        return $this;
    }

    /**
     * Exists
     *
     * Tells whether a fav definition exists or not
     *
     * @param   string  $id     The fav id to test
     *
     * @return  bool            true if the fav definition exists, false otherwise
     */
    public function exists(string $id): bool
    {
        return isset($this->fav_defs[$id]);
    }

    /**
     * Initializes the default favorites.
     */
    protected function initDefaultFavorites(): void
    {
        $this->registerMultiple([
            'prefs' => [
                'title'      => __('My preferences'),
                'url'        => $this->core->adminurl->get('admin.user.pref'),
                'small-icon' => 'images/menu/user-pref.png',
                'large-icon' => 'images/menu/user-pref-b.png'],
            'new_post' => [
                'title'       => __('New post'),
                'url'         => $this->core->adminurl->get('admin.post'),
                'small-icon'  => ['images/menu/edit.svg', 'images/menu/edit-dark.svg'],
                'large-icon'  => ['images/menu/edit.svg', 'images/menu/edit-dark.svg'],
                'permissions' => 'usage,contentadmin',
                'active_cb'   => [__CLASS__, 'cbNewpostActive']],
            'posts' => [
                'title'        => __('Posts'),
                'url'          => $this->core->adminurl->get('admin.posts'),
                'small-icon'   => 'images/menu/entries.png',
                'large-icon'   => 'images/menu/entries-b.png',
                'permissions'  => 'usage,contentadmin',
                'dashboard_cb' => [__CLASS__, 'cbPostsDashboard']],
            'comments' => [
                'title'        => __('Comments'),
                'url'          => $this->core->adminurl->get('admin.comments'),
                'small-icon'   => 'images/menu/comments.png',
                'large-icon'   => 'images/menu/comments-b.png',
                'permissions'  => 'usage,contentadmin',
                'dashboard_cb' => [__CLASS__, 'cbCommentsDashboard']],
            'search' => [
                'title'       => __('Search'),
                'url'         => $this->core->adminurl->get('admin.search'),
                'small-icon'  => ['images/menu/search.svg','images/menu/search-dark.svg'],
                'large-icon'  => ['images/menu/search.svg','images/menu/search-dark.svg'],
                'permissions' => 'usage,contentadmin'],
            'categories' => [
                'title'       => __('Categories'),
                'url'         => $this->core->adminurl->get('admin.categories'),
                'small-icon'  => 'images/menu/categories.png',
                'large-icon'  => 'images/menu/categories-b.png',
                'permissions' => 'categories'],
            'media' => [
                'title'       => __('Media manager'),
                'url'         => $this->core->adminurl->get('admin.media'),
                'small-icon'  => ['images/menu/media.svg', 'images/menu/media-dark.svg'],
                'large-icon'  => ['images/menu/media.svg', 'images/menu/media-dark.svg'],
                'permissions' => 'media,media_admin'],
            'blog_pref' => [
                'title'       => __('Blog settings'),
                'url'         => $this->core->adminurl->get('admin.blog.pref'),
                'small-icon'  => ['images/menu/blog-pref.svg','images/menu/blog-pref-dark.svg'],
                'large-icon'  => ['images/menu/blog-pref.svg','images/menu/blog-pref-dark.svg'],
                'permissions' => 'admin'],
            'blogs' => [
                'title'       => __('Blogs'),
                'url'         => $this->core->adminurl->get('admin.blogs'),
                'small-icon'  => 'images/menu/blogs.png',
                'large-icon'  => 'images/menu/blogs-b.png',
                'permissions' => 'usage,contentadmin'],
            'users' => [
                'title'      => __('Users'),
                'url'        => $this->core->adminurl->get('admin.users'),
                'small-icon' => 'images/menu/users.png',
                'large-icon' => 'images/menu/users-b.png'],
            'langs' => [
                'title'      => __('Languages'),
                'url'        => $this->core->adminurl->get('admin.langs'),
                'small-icon' => 'images/menu/langs.png',
                'large-icon' => 'images/menu/langs-b.png'],
            'help' => [
                'title'      => __('Global help'),
                'url'        => $this->core->adminurl->get('admin.help'),
                'small-icon' => 'images/menu/help.svg',
                'large-icon' => 'images/menu/help.svg']
        ]);
    }

    /**
     * Helper for posts icon on dashboard
     *
     * @param   Core            $core   Core instance
     * @param   ArrayObject     $v      Favicon object
     */
    public static function cbPostsDashboard(Core $core, ArrayObject $v): void
    {
        $post_count  = (int) $core->blog->getPosts([], true)->f(0);
        $str_entries = __('%d post', '%d posts', $post_count);
        $v['title']  = sprintf($str_entries, $post_count);
    }

    /**
     * Helper for new post active menu
     *
     * Take account of post edition (if id is set)
     *
     * @param   string      $request_uri        The URI
     * @param   array       $request_params     The params
     * @return  boolean                         Active
     */
    public static function cbNewpostActive(string $request_uri, array $request_params): bool
    {
        return 'post.php' == $request_uri && !isset($request_params['id']);
    }

    /**
     * Helper for comments icon on dashboard
     *
     * @param   Core            $core   The core
     * @param   ArrayObject     $v      Favicon object
     */
    public static function cbCommentsDashboard(Core $core, ArrayObject $v): void
    {
        $comment_count = (int) $core->blog->getComments([], true)->f(0);
        $str_comments  = __('%d comment', '%d comments', $comment_count);
        $v['title']    = sprintf($str_comments, $comment_count);
    }
}
