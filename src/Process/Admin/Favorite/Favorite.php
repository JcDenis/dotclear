<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Favorite;

// Dotclear\Process\Admin\Favorite\Favorite
use ArrayObject;
use Dotclear\Core\User\Preference\Workspace;
use Dotclear\Process\Admin\Menu\Summary;

/**
 * Admin favorites handling facilities.
 *
 * Accessible from dotclear()->favorite()->
 *
 * @ingroup  Admin Favorite
 */
class Favorite
{
    /**
     * @var ArrayObject $fav_defs
     *                  list of favorite definitions
     */
    protected $fav_defs;

    /**
     * @var Workspace $ws
     *                current favorite landing workspace
     */
    protected $ws;

    /**
     * @var array<string,mixed> $local_prefs
     *                          list of user-defined favorite ids
     */
    protected $local_prefs = [];

    /**
     *  @var array<string,mixed> $global_prefs
     * list of globally-defined favorite ids
     */
    protected $global_prefs = [];

    /**
     * @var array<string,mixed> $user_prefs
     *                          list of user preferences (either one of the 2 above, or not!)
     */
    protected $user_prefs = [];

    /**
     * @var array<string,string> $default_favorites
     *                           Default favorites values
     */
    protected $default_favorites = [
        // favorite title (localized)
        'title' => '',
        // favorite URL
        'url' => '',
        // favorite small icon (for menu)
        'small-icon' => 'images/menu/no-icon.svg',
        // favorite large icon (for dashboard)
        'large-icon' => 'images/menu/no-icon.svg',
        // (optional) comma-separated list of permissions for thie fav, if not set : no restriction
        'permissions' => '',
        // (optional) callback to modify title if dynamic, if not set : title is taken as is
        'dashboard_cb' => '',
        //  (optional) callback to tell whether current page matches favorite or not, for complex pages
        'active_cb' => '',
    ];

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->fav_defs   = new ArrayObject();
        $this->ws         = dotclear()->user()->preference()->get('dashboard');
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
        }
    }

    /**
     * Sets up favorites.
     *
     * Fetch user favorites (against his permissions)
     * This method is to be called after loading plugins
     */
    public function setup(): void
    {
        $this->initDefaultFavorites();
        dotclear()->behavior()->call('adminDashboardFavorites', $this);
        $this->setUserPrefs();
    }

    /**
     * Get Favorite.
     *
     * Retrieves a favorite (complete description) from its id.
     *
     * @param array|string $p the favorite id, or an array having 1 key 'name' set to id, ther keys are merged to favorite
     *
     * @return array The favorite
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
            if (!dotclear()->user()->check($fattr['permissions'], dotclear()->blog()->id)) {
                return [];
            }
        } elseif (!dotclear()->user()->isSuperAdmin()) {
            return [];
        }

        return $fattr;
    }

    /**
     * Get Favorites.
     *
     * Retrieves a list of favorites.
     *
     * @param array $ids an array of ids, as defined in getFavorite
     *
     * @return array The favorites, can be empty if ids are not found (or not permitted)
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
     * Set user prefs.
     *
     * Get user favorites from settings.
     * These are complete favorites, not ids only
     * returned favorites are the first non-empty list from :
     * - user-defined favorites
     * - globally-defined favorites
     * - a failback list "new post" (shall never be empty)
     *
     * This method is called by self::setup()
     */
    protected function setUserPrefs(): void
    {
        $this->user_prefs = $this->local_prefs ? $this->getFavorites($this->local_prefs) : [];
        if (empty($this->user_prefs)) {
            $this->user_prefs = $this->global_prefs ? $this->getFavorites($this->global_prefs) : [];
        }
        if (empty($this->user_prefs)) {
            $this->user_prefs = $this->getFavorites(['new_post']);
        }
        // Loop over prefs to enable active favorites
        foreach ($this->user_prefs as $k => &$v) {
            // Use callback if defined to match whether favorite is active or not
            if (!empty($v['active_cb']) && is_callable($v['active_cb'])) {
                $v['active'] = call_user_func($v['active_cb']);
            // Or use called handler
            } else {
                parse_str(parse_url($v['url'], PHP_URL_QUERY), $url);
                $handler     = $url['handler'] ?: null;
                $v['active'] = dotclear()->adminurl()->is($handler);
            }
        }
    }

    /**
     * Get user favorites.
     *
     * Returns favorites that correspond to current user
     * (may be local, global, or failback favorites)
     *
     * @return array Array of favorites (enriched)
     */
    public function getUserFavorites(): array
    {
        return $this->user_prefs;
    }

    /**
     * Get Favorite IDs.
     *
     * Returns user-defined or global favorites ids list
     * shall not be called outside handler UserPref.
     *
     * @param bool $global If true, retrieve global favs, user favs otherwise
     *
     * @return array Array of favorites ids (only ids, not enriched)
     */
    public function getFavoriteIDs(bool $global = false): array
    {
        return $global ? $this->global_prefs : $this->local_prefs;
    }

    /**
     * Set Favorite IDs.
     *
     * Stores user-defined or global favorites ids list
     * shall not be called outside handler UserPref.
     *
     * @param array $ids    List of fav ids
     * @param bool  $global If true, retrieve global favs, user favs otherwise
     */
    public function setFavoriteIDs(array $ids, bool $global = false): void
    {
        $this->ws->put('favorites', $ids, 'array', null, true, $global);
    }

    /**
     * Get Available Favorites IDs.
     *
     * Returns all available fav ids
     *
     * @return array Array of favorites ids (only ids, not enriched)
     */
    public function getAvailableFavoritesIDs(): array
    {
        return array_keys($this->fav_defs->getArrayCopy());
    }

    /**
     * Append Menu Title.
     *
     * Adds favorites section title to sidebar menu
     * shall not be called outside Admin Prepend.
     *
     * @param Summary $menu Summary instance
     */
    public function appendMenuTitle(Summary $menu): void
    {
        $menu->add('Favorites', 'favorites-menu', __('My favorites'));
    }

    /**
     * Append Menu.
     *
     * Adds favorites items title to sidebar menu
     * shall not be called outside Admin Prepend.
     *
     * @param Summary $menu Summary instance
     */
    public function appendMenu(Summary $menu): void
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
     * Append Dashboard Icons.
     *
     * Adds favorites icons to index page
     * shall not be called outside Admin handler Home.
     *
     * @param ArrayObject $icons Dashboard icon list to enrich
     */
    public function appendDashboardIcons(ArrayObject $icons): void
    {
        foreach ($this->user_prefs as $k => $v) {
            if (!empty($v['dashboard_cb']) && is_callable($v['dashboard_cb'])) {
                $v = new ArrayObject($v);
                call_user_func($v['dashboard_cb'], $v);
            }
            $icons[$k] = new ArrayObject([$v['title'], $v['url'], $v['large-icon']]);
            dotclear()->behavior()->call('adminDashboardFavsIcon', $k, $icons[$k]);
        }
    }

    /**
     * Register.
     *
     * Registers a new favorite definition
     *
     * @param string $id   Favorite id
     * @param array  $data Favorite information. @see self::$default_favorites
     *
     * @return Favorite Favorite instance
     */
    public function register(string $id, array $data): Favorite
    {
        $this->fav_defs[$id] = array_merge($this->default_favorites, $data);

        return $this;
    }

    /**
     * Register Multiple.
     *
     * Registers a list of favorites definition
     *
     * @see self::register()
     *
     * @param array $data an array defining all favorites key is the id, value is the data
     *
     * @return Favorite Favorite instance
     */
    public function registerMultiple(array $data): Favorite
    {
        foreach ($data as $k => $v) {
            $this->register($k, $v);
        }

        return $this;
    }

    /**
     * Exists.
     *
     * Tells whether a fav definition exists or not
     *
     * @param string $id The fav id to test
     *
     * @return bool true if the fav definition exists, false otherwise
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
                'url'        => dotclear()->adminurl()->get('admin.user.pref'),
                'small-icon' => 'images/menu/user-pref.svg',
                'large-icon' => 'images/menu/user-pref.svg', ],
            'new_post' => [
                'title'       => __('New post'),
                'url'         => dotclear()->adminurl()->get('admin.post'),
                'small-icon'  => ['images/menu/edit.svg', 'images/menu/edit-dark.svg'],
                'large-icon'  => ['images/menu/edit.svg', 'images/menu/edit-dark.svg'],
                'permissions' => 'usage,contentadmin',
                'active_cb'   => [$this, 'cbNewpostActive'], ],
            'posts' => [
                'title'        => __('Posts'),
                'url'          => dotclear()->adminurl()->get('admin.posts'),
                'small-icon'   => ['images/menu/entries.svg', 'images/menu/entries-dark.svg'],
                'large-icon'   => ['images/menu/entries.svg', 'images/menu/entries-dark.svg'],
                'permissions'  => 'usage,contentadmin',
                'dashboard_cb' => [$this, 'cbPostsDashboard'], ],
            'comments' => [
                'title'        => __('Comments'),
                'url'          => dotclear()->adminurl()->get('admin.comments'),
                'small-icon'   => ['images/menu/comments.svg', 'images/menu/comments-dark.svg'],
                'large-icon'   => ['images/menu/comments.svg', 'images/menu/comments-dark.svg'],
                'permissions'  => 'usage,contentadmin',
                'dashboard_cb' => [$this, 'cbCommentsDashboard'], ],
            'search' => [
                'title'       => __('Search'),
                'url'         => dotclear()->adminurl()->get('admin.search'),
                'small-icon'  => ['images/menu/search.svg', 'images/menu/search-dark.svg'],
                'large-icon'  => ['images/menu/search.svg', 'images/menu/search-dark.svg'],
                'permissions' => 'usage,contentadmin', ],
            'categories' => [
                'title'       => __('Categories'),
                'url'         => dotclear()->adminurl()->get('admin.categories'),
                'small-icon'  => ['images/menu/categories.svg', 'images/menu/categories-dark.svg'],
                'large-icon'  => ['images/menu/categories.svg', 'images/menu/categories-dark.svg'],
                'permissions' => 'categories', ],
            'blog_pref' => [
                'title'       => __('Blog settings'),
                'url'         => dotclear()->adminurl()->get('admin.blog.pref'),
                'small-icon'  => ['images/menu/blog-pref.svg', 'images/menu/blog-pref-dark.svg'],
                'large-icon'  => ['images/menu/blog-pref.svg', 'images/menu/blog-pref-dark.svg'],
                'permissions' => 'admin', ],
            'blogs' => [
                'title'       => __('Blogs'),
                'url'         => dotclear()->adminurl()->get('admin.blogs'),
                'small-icon'  => ['images/menu/blogs.svg', 'images/menu/blogs-dark.svg'],
                'large-icon'  => ['images/menu/blogs.svg', 'images/menu/blogs-dark.svg'],
                'permissions' => 'usage,contentadmin', ],
            'users' => [
                'title'      => __('Users'),
                'url'        => dotclear()->adminurl()->get('admin.users'),
                'small-icon' => 'images/menu/users.svg',
                'large-icon' => 'images/menu/users.svg', ],
            'langs' => [
                'title'      => __('Languages'),
                'url'        => dotclear()->adminurl()->get('admin.langs'),
                'small-icon' => ['images/menu/langs.svg', 'images/menu/langs-dark.svg'],
                'large-icon' => ['images/menu/langs.svg', 'images/menu/langs-dark.svg'], ],
            'help' => [
                'title'      => __('Global help'),
                'url'        => dotclear()->adminurl()->get('admin.help'),
                'small-icon' => 'images/menu/help.svg',
                'large-icon' => 'images/menu/help.svg', ],
        ]);

        if (dotclear()->blog()->public_path) {
            $this->register(
                'media',
                [
                    'title'       => __('Media manager'),
                    'url'         => dotclear()->adminurl()->get('admin.media'),
                    'small-icon'  => ['images/menu/media.svg', 'images/menu/media-dark.svg'],
                    'large-icon'  => ['images/menu/media.svg', 'images/menu/media-dark.svg'],
                    'permissions' => 'media,media_admin',
                ]
            );
        }
    }

    /**
     * Helper for posts icon on dashboard.
     *
     * @param ArrayObject $v Favicon object
     */
    public function cbPostsDashboard(ArrayObject $v): void
    {
        $post_count  = dotclear()->blog()->posts()->getPosts([], true)->fInt();
        $str_entries = __('%d post', '%d posts', $post_count);
        $v['title']  = sprintf($str_entries, $post_count);
    }

    /**
     * Helper for new post active menu.
     *
     * Take account of post edition (if id is set)
     *
     * @return bool Active
     */
    public function cbNewpostActive(): bool
    {
        return dotclear()->adminurl()->is('admin.post') && !isset($_REQUEST['id']);
    }

    /**
     * Helper for comments icon on dashboard.
     *
     * @param ArrayObject $v Favicon object
     */
    public function cbCommentsDashboard(ArrayObject $v): void
    {
        $comment_count = dotclear()->blog()->comments()->getComments([], true)->fInt();
        $str_comments  = __('%d comment', '%d comments', $comment_count);
        $v['title']    = sprintf($str_comments, $comment_count);
    }
}
