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
use Dotclear\App;
use Dotclear\Core\User\Preferences\PreferencesGroup;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Mapper\Strings;

/**
 * Admin favorites handling facilities.
 *
 * Accessible from App::core()->favorite()->
 *
 * @ingroup  Admin Favorite
 */
final class Favorite
{
    /**
     * @var array<string,FavoriteItem> $favorites
     *                                 The list of favorite items
     */
    private $favorites = [];

    /**
     * @var PreferencesGroup $ws
     *                       The current favorite landing workspace
     */
    private $ws;

    /**
     * @var Strings $local_prefs
     *              The list of user-defined favorite ids
     */
    private $local_prefs;

    /**
     * @var Strings $global_prefs
     *              The list of globally-defined favorite ids
     */
    private $global_prefs;

    /**
     * @var array<string,FavoriteItem> $user_prefs
     *                                 The list of user preferences (either one of the 2 above, or not!)
     */
    private $user_prefs = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->ws         = App::core()->user()->preferences('dashboard');
        $this->user_prefs = [];

        if ($this->ws->hasLocalPreference('favorites')) {
            $this->local_prefs  = new Strings($this->ws->getLocalPreference('favorites'));
            $this->global_prefs = new Strings($this->ws->getGlobalPreference('favorites'));
        } else {
            $this->local_prefs  = new Strings();
            $this->global_prefs = new Strings();
        }
    }

    /**
     * Add a favorite item.
     *
     * Registers a new favorite definition
     *
     * @param FavoriteItem $item The favorite item
     */
    public function addItem(FavoriteItem $item): void
    {
        $this->favorites[$item->id] = $item;
    }

    /**
     * Check if a Favorite item exist.
     *
     * Tells whether a fav definition exists or not.
     *
     * @param string $id The fav id to test
     *
     * @return bool true if the fav definition exists, false otherwise
     */
    public function hasItem(string $id): bool
    {
        return isset($this->favorites[$id]);
    }

    /**
     * Get a Favorite item.
     *
     * Retrieves a favorite (complete description) from its id.
     *
     * @param string $id The favorite id
     *
     * @return null|FavoriteItem The favorite item
     */
    public function getItem(string $id): ?FavoriteItem
    {
        return $this->hasItem(id: $id) && $this->favorites[$id]->show ? $this->favorites[$id] : null;
    }

    /**
     * Get Favorites items.
     *
     * Retrieves a list of favorites.
     *
     * @param Strings $ids an array of ids, as defined in getFavorite
     *
     * @return array<string,FavoriteItem> The favorites, can be empty if ids are not found (or not permitted)
     */
    public function getItems(Strings $ids): array
    {
        $prefs = [];
        foreach ($ids->dump() as $id) {
            if (null != ($item = $this->getItem(id: $id))) {
                $prefs[$id] = $item;
            }
        }

        return $prefs;
    }

    /**
     * Get Favorites IDs.
     *
     * Returns all available fav ids
     *
     * @return Strings List of favorites ids (only ids, not enriched)
     */
    public function getIds(): Strings
    {
        return new Strings(array_keys($this->favorites));
    }

    /**
     * Get user favorites.
     *
     * Returns favorites that correspond to current user
     * (may be local, global, or failback favorites)
     *
     * @return array<string,FavoriteItem> The favorite items (enriched)
     */
    public function getUserItems(): array
    {
        return $this->user_prefs;
    }

    /**
     * Get global Favorite IDs.
     *
     * Returns global favorites ids list
     * shall not be called outside handler UserPref.
     *
     * @return Strings The list of favorites ids (only ids, not enriched)
     */
    public function getGlobalIds(): Strings
    {
        return $this->global_prefs;
    }

    /**
     * Set global Favorite IDs.
     *
     * Stores global favorites ids list
     * shall not be called outside handler UserPref.
     *
     * @param Strings $ids List of fav ids
     */
    public function setGlobalIds(Strings $ids): void
    {
        $this->ws->putPreference('favorites', $ids->dump(), 'array', null, true, true);
    }

    /**
     * Get local Favorite IDs.
     *
     * Returns user-defined favorites ids list
     * shall not be called outside handler UserPref.
     *
     * @return Strings The list of favorites ids (only ids, not enriched)
     */
    public function getLocalIds(): Strings
    {
        return $this->local_prefs;
    }

    /**
     * Set local Favorite IDs.
     *
     * Stores user-defined favorites ids list
     * shall not be called outside handler UserPref.
     *
     * @param Strings $ids List of fav ids
     */
    public function setLocalIds(Strings $ids): void
    {
        $this->ws->putPreference('favorites', $ids->dump(), 'array', null, true, false);
    }

    /**
     * Sets up favorites.
     *
     * Fetch user favorites (against his permissions)
     * This method is to be called after loading plugins
     */
    public function setDefaultItems(): void
    {
        $this->addItem(new FavoriteItem(
            id: 'prefs',
            title: __('My preferences'),
            url: App::core()->adminurl()->get('admin.user.pref'),
            icons: 'images/menu/user-pref.svg',
        ));
        $this->addItem(new FavoriteItem(
            id: 'new_post',
            title: __('New post'),
            url: App::core()->adminurl()->get('admin.post'),
            icons: ['images/menu/edit.svg', 'images/menu/edit-dark.svg'],
            permission: 'usage,contentadmin',
            activation: App::core()->adminurl()->is('admin.post') && !GPC::request()->isset('id'),
        ));
        $this->addItem(new FavoriteItem(
            id: 'posts',
            title: __('Posts'),
            url: App::core()->adminurl()->get('admin.posts'),
            icons: ['images/menu/entries.svg', 'images/menu/entries-dark.svg'],
            permission: 'usage,contentadmin',
            dashboard: function (DashboardIcon $icon): void {
                $post_count  = App::core()->blog()->posts()->countPosts();
                $icon->replaceTitle(sprintf(__('%d post', '%d posts', $post_count), $post_count));
            },
        ));
        $this->addItem(new FavoriteItem(
            id: 'comments',
            title: __('Comments'),
            url: App::core()->adminurl()->get('admin.comments'),
            icons: ['images/menu/comments.svg', 'images/menu/comments-dark.svg'],
            permission: 'usage,contentadmin',
            dashboard: function (DashboardIcon $icon): void {
                $comment_count = App::core()->blog()->comments()->countComments();
                $icon->replaceTitle(sprintf(__('%d comment', '%d comments', $comment_count), $comment_count));
            },
        ));
        $this->addItem(new FavoriteItem(
            id: 'search',
            title: __('Search'),
            url: App::core()->adminurl()->get('admin.search'),
            icons: ['images/menu/search.svg', 'images/menu/search-dark.svg'],
            permission: 'usage,contentadmin',
        ));
        $this->addItem(new FavoriteItem(
            id: 'categories',
            title: __('Categories'),
            url: App::core()->adminurl()->get('admin.categories'),
            icons: ['images/menu/categories.svg', 'images/menu/categories-dark.svg'],
            permission: 'categories',
        ));
        $this->addItem(new FavoriteItem(
            id: 'blog_pref',
            title: __('Blog settings'),
            url: App::core()->adminurl()->get('admin.blog.pref'),
            icons: ['images/menu/blog-pref.svg', 'images/menu/blog-pref-dark.svg'],
            permission: 'admin',
        ));
        $this->addItem(new FavoriteItem(
            id: 'blogs',
            title: __('Blogs'),
            url: App::core()->adminurl()->get('admin.blogs'),
            icons: ['images/menu/blogs.svg', 'images/menu/blogs-dark.svg'],
            permission: 'usage,contentadmin',
        ));
        $this->addItem(new FavoriteItem(
            id: 'users',
            title: __('Users'),
            url: App::core()->adminurl()->get('admin.users'),
            icons: 'images/menu/users.svg',
        ));
        $this->addItem(new FavoriteItem(
            id: 'langs',
            title: __('Languages'),
            url: App::core()->adminurl()->get('admin.langs'),
            icons: ['images/menu/langs.svg', 'images/menu/langs-dark.svg'],
        ));
        $this->addItem(new FavoriteItem(
            id: 'help',
            title: __('Global help'),
            url: App::core()->adminurl()->get('admin.help'),
            icons: 'images/menu/help.svg',
        ));

        if (App::core()->blog()->public_path) {
            $this->addItem(new FavoriteItem(
                id: 'media',
                title: __('Media manager'),
                url: App::core()->adminurl()->get('admin.media'),
                icons: ['images/menu/media.svg', 'images/menu/media-dark.svg'],
                permission: 'media,media_admin',
            ));
        }

        App::core()->behavior('adminAfterSetDefaultFavoriteItems')->call(favorite: $this);

        // Set user favorites preferences
        $this->user_prefs = $this->local_prefs->count() ? $this->getItems(ids: $this->local_prefs) : [];
        if (empty($this->user_prefs)) {
            $this->user_prefs = $this->global_prefs->count() ? $this->getItems(ids: $this->global_prefs) : [];
        }
        if (empty($this->user_prefs)) {
            $this->user_prefs = $this->getItems(ids: new Strings(['new_post']));
        }
    }
}
