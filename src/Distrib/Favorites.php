<?php
/**
 * @brief Dotclear admin default favorites class
 *
 * @package Dotclear
 * @subpackage Distrib
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Distrib;

use Dotclear\Core\Core;

use Dotclear\Admin\Favorites as BaseFavorites;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Favorites
{
    /**
     * Initializes the default favorites.
     *
     * @param      Core         $favs   The favs
     * @param      BaseFavorites  $favs   The favs
     */
    public static function initDefaultFavorites(Core $core, BaseFavorites $favs)
    {
        $favs->registerMultiple([
            'prefs' => [
                'title'      => __('My preferences'),
                'url'        => $core->adminurl->get('admin.user.preferences'),
                'small-icon' => 'images/menu/user-pref.png',
                'large-icon' => 'images/menu/user-pref-b.png'],
            'new_post' => [
                'title'       => __('New post'),
                'url'         => $core->adminurl->get('admin.post'),
                'small-icon'  => 'images/menu/edit.png',
                'large-icon'  => 'images/menu/edit-b.png',
                'permissions' => 'usage,contentadmin',
                'active_cb'   => ['Dotclear\Distrib\Favorites', 'newpostActive']],
            'posts' => [
                'title'        => __('Posts'),
                'url'          => $core->adminurl->get('admin.posts'),
                'small-icon'   => 'images/menu/entries.png',
                'large-icon'   => 'images/menu/entries-b.png',
                'permissions'  => 'usage,contentadmin',
                'dashboard_cb' => ['Dotclear\Distrib\Favorites', 'postsDashboard']],
            'comments' => [
                'title'        => __('Comments'),
                'url'          => $core->adminurl->get('admin.comments'),
                'small-icon'   => 'images/menu/comments.png',
                'large-icon'   => 'images/menu/comments-b.png',
                'permissions'  => 'usage,contentadmin',
                'dashboard_cb' => ['Dotclear\Distrib\Favorites', 'commentsDashboard']],
            'search' => [
                'title'       => __('Search'),
                'url'         => $core->adminurl->get('admin.search'),
                'small-icon'  => 'images/menu/search.png',
                'large-icon'  => 'images/menu/search-b.png',
                'permissions' => 'usage,contentadmin'],
            'categories' => [
                'title'       => __('Categories'),
                'url'         => $core->adminurl->get('admin.categories'),
                'small-icon'  => 'images/menu/categories.png',
                'large-icon'  => 'images/menu/categories-b.png',
                'permissions' => 'categories'],
            'media' => [
                'title'       => __('Media manager'),
                'url'         => $core->adminurl->get('admin.media'),
                'small-icon'  => 'images/menu/media.png',
                'large-icon'  => 'images/menu/media-b.png',
                'permissions' => 'media,media_admin'],
            'blog_pref' => [
                'title'       => __('Blog settings'),
                'url'         => $core->adminurl->get('admin.blog.pref'),
                'small-icon'  => 'images/menu/blog-pref.png',
                'large-icon'  => 'images/menu/blog-pref-b.png',
                'permissions' => 'admin'],
            'blog_theme' => [
                'title'       => __('Blog appearance'),
                'url'         => $core->adminurl->get('admin.blog.theme'),
                'small-icon'  => 'images/menu/themes.png',
                'large-icon'  => 'images/menu/blog-theme-b.png',
                'permissions' => 'admin'],
            'blogs' => [
                'title'       => __('Blogs'),
                'url'         => $core->adminurl->get('admin.blogs'),
                'small-icon'  => 'images/menu/blogs.png',
                'large-icon'  => 'images/menu/blogs-b.png',
                'permissions' => 'usage,contentadmin'],
            'users' => [
                'title'      => __('Users'),
                'url'        => $core->adminurl->get('admin.users'),
                'small-icon' => 'images/menu/users.png',
                'large-icon' => 'images/menu/users-b.png'],
            'plugins' => [
                'title'      => __('Plugins management'),
                'url'        => $core->adminurl->get('admin.plugins'),
                'small-icon' => 'images/menu/plugins.png',
                'large-icon' => 'images/menu/plugins-b.png'],
            'langs' => [
                'title'      => __('Languages'),
                'url'        => $core->adminurl->get('admin.langs'),
                'small-icon' => 'images/menu/langs.png',
                'large-icon' => 'images/menu/langs-b.png'],
            'help' => [
                'title'      => __('Global help'),
                'url'        => $core->adminurl->get('admin.help'),
                'small-icon' => 'images/menu/help.png',
                'large-icon' => 'images/menu/help-b.png']
        ]);
    }

    /**
     * Helper for posts icon on dashboard
     *
     * @param      dcCore  $core   The core
     * @param      mixed   $v      { parameter_description }
     */
    public static function postsDashboard($core, $v)
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
     * @param  string   $request_uri    The URI
     * @param  array    $request_params The params
     * @return boolean                  Active
     */
    public static function newpostActive($request_uri, $request_params)
    {
        return 'post.php' == $request_uri && !isset($request_params['id']);
    }

    /**
     * Helper for comments icon on dashboard
     *
     * @param      dcCore  $core   The core
     * @param      mixed   $v      { parameter_description }
     */
    public static function commentsDashboard($core, $v)
    {
        $comment_count = (int) $core->blog->getComments([], true)->f(0);
        $str_comments  = __('%d comment', '%d comments', $comment_count);
        $v['title']    = sprintf($str_comments, $comment_count);
    }
}
