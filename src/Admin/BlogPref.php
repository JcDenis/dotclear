<?php
/**
 * @class Dotclear\Admin\BlogPref
 * @brief Dotclear admin blog preferences popup helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;

class BlogPref
{
    /**
     * JS Popup helper for static home linked to an entry
     *
     * @param      string  $editor  The editor
     *
     * @return     mixed
     */
    public static function adminPopupPosts(Core $core, $editor = '')
    {
        if (empty($editor) || $editor != 'admin.blog_pref') {
            return;
        }

        $res = Page::jsJson('admin.blog_pref', [
            'base_url' => $core->blog->url
        ]) .
        Page::jsLoad('js/_blog_pref_popup_posts.js');

        return $res;
    }
}
