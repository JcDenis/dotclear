<?php
/**
 * @class Dotclear\Process\Admin\Service\RestMethods
 * @brief Dotclear common admin REST methods
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Service;

use Dotclear\Exception\AdminException;
use Dotclear\Html\Html;
use Dotclear\Html\Validator;
use Dotclear\Html\XmlTag;
use Dotclear\Module\Store\Repository\Repository;
use Dotclear\Network\Feed\Reader;
use Dotclear\Process\Admin\Service\Updater;
use Dotclear\Utils\Dt;
use Dotclear\Utils\Text;

class RestMethods
{
    /**
     * Get number of posts (whatever are their status) for current blog
     *
     * @param   array   $get    Cleaned $_GET
     *
     * @return  XmlTag          The response
     */
    public static function getPostsCount(array $get): XmlTag
    {
        $count = dotclear()->blog()->posts()->getPosts([], true)->f(0);
        $str   = sprintf(__('%d post', '%d posts', $count), $count);

        $rsp      = new XmlTag('count');
        $rsp->ret = $str;

        return $rsp;
    }

    /**
     * Get number of comments (whatever are their status) for current blog
     *
     * @param   array   $get    Cleaned $_GET
     *
     * @return  XmlTag          The response
     */
    public static function getCommentsCount(array $get): XmlTag
    {
        $count = dotclear()->blog()->comments()->getComments([], true)->f(0);
        $str   = sprintf(__('%d comment', '%d comments', $count), $count);

        $rsp      = new XmlTag('count');
        $rsp->ret = $str;

        return $rsp;
    }

    /**
     * Check news update
     *
     * @param   array   $get    Cleaned $_GET
     *
     * @return  XmlTag          The response
     */
    public static function checkNewsUpdate(array $get): XmlTag
    {
        # Dotclear news

        $rsp        = new XmlTag('news');
        $rsp->check = false;
        $ret        = __('Dotclear news not available');

        if (dotclear()->user()->preference()->dashboard->dcnews) {
            try {
                if (empty(dotclear()->resources['rss_news'])) {
                    throw new AdminException();
                }
                $feed_reader = new Reader();
                $feed_reader->setCacheDir(dotclear()->config()->cache_dir);
                $feed_reader->setTimeout(2);
                $feed_reader->setUserAgent('Dotclear - https://dotclear.org/');
                $feed = $feed_reader->parse(dotclear()->resources['rss_news']);
                if ($feed) {
                    $ret = '<div class="box medium dc-box" id="ajax-news"><h3>' . __('Dotclear news') . '</h3><dl id="news">';
                    $i   = 1;
                    foreach ($feed->items as $item) {
                        /* @phpstan-ignore-next-line */
                        $dt = isset($item->link) ? '<a href="' . $item->link . '" class="outgoing" title="' . $item->title . '">' .
                        /* @phpstan-ignore-next-line */
                        $item->title . ' <img src="?df=images/outgoing-link.svg" alt="" /></a>' : $item->title;
                        $ret .= '<dt>' . $dt . '</dt>' .
                        '<dd><p><strong>' . Dt::dt2str(__('%d %B %Y:'), $item->pubdate, 'Europe/Paris') . '</strong> ' .
                        '<em>' . Text::cutString(Html::clean($item->content), 120) . '...</em></p></dd>';
                        $i++;
                        if ($i > 2) {
                            break;
                        }
                    }
                    $ret .= '</dl></div>';
                    $rsp->check = true;
                }
            } catch (\Exception $e) {
            }
        }
        $rsp->ret = $ret;

        return $rsp;
    }

    /**
     * Check core update
     *
     * @param   array   $get    Cleaned $_GET
     *
     * @return  XmlTag          The response
     */
    public static function checkCoreUpdate(array $get): XmlTag
    {
        # Dotclear updates notifications

        $rsp        = new XmlTag('update');
        $rsp->check = false;
        $ret        = __('Dotclear update not available');

        if (dotclear()->user()->isSuperAdmin()
            && !dotclear()->config()->core_update_noauto
            && is_readable(dotclear()->config()->digests_dir)
            && !dotclear()->user()->preference()->dashboard->nodcupdate
        ) {
            $updater      = new Updater(dotclear()->config()->core_update_url, 'dotclear', dotclear()->config()->core_update_channel, dotclear()->config()->cache_dir . '/versions');
            $new_v        = $updater->check(dotclear()->config()->core_version);
            $version_info = $new_v ? $updater->getInfoURL() : '';

            if ($updater->getNotify() && $new_v) {
                // Check PHP version required
                if (version_compare(phpversion(), $updater->getPHPVersion()) >= 0) {
                    $ret = '<div class="dc-update" id="ajax-update"><h3>' . sprintf(__('Dotclear %s is available!'), $new_v) . '</h3> ' .
                    '<p><a class="button submit" href="' . dotclear()->adminurl()->get('admin.update') . '">' . sprintf(__('Upgrade now'), $new_v) . '</a> ' .
                    '<a class="button" href="' . dotclear()->adminurl()->get('admin.update', ['hide_msg' => 1]) . '">' . __('Remind me later') . '</a>' .
                        ($version_info ? ' </p>' .
                        '<p class="updt-info"><a href="' . $version_info . '">' . __('Information about this version') . '</a>' : '') . '</p>' .
                        '</div>';
                } else {
                    $ret = '<p class="info">' .
                    sprintf(
                        __('A new version of Dotclear is available but needs PHP version ≥ %s, your\'s is currently %s'),
                        $updater->getPHPVersion(),
                        phpversion()
                    ) .
                        '</p>';
                }
                $rsp->check = true;
            } else {
                if (version_compare(phpversion(), dotclear()->config()->php_next_required, '<')) {
                    if (!dotclear()->user()->preference()->interface->hidemoreinfo) {
                        $ret = '<p class="info">' .
                        sprintf(
                            __('The next versions of Dotclear will not support PHP version < %s, your\'s is currently %s'),
                            dotclear()->config()->php_next_required,
                            phpversion()
                        ) .
                        '</p>';
                        $rsp->check = true;
                    }
                }
            }
        }
        $rsp->ret = $ret;

        return $rsp;
    }

    /**
     * Check repository update
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  XmlTag          The response
     */
    public static function checkStoreUpdate(array $get, array $post): XmlTag
    {
        # Dotclear store updates notifications

        $rsp        = new XmlTag('update');
        $rsp->check = false;
        $rsp->nb    = 0;
        $ret        = __('No updates are available');
        $mod        = '';
        $url        = '';

        if (empty($post['store'])) {
            throw new AdminException('No store type');
        }

        if ($post['store'] == 'themes') {
            $mod = dotclear()->themes;
            $url = dotclear()->blog()->settings()->system->store_theme_url;
        } elseif ($post['store'] == 'plugins') {
            $mod = dotclear()->plugins;
            $url = dotclear()->blog()->settings()->system->store_plugin_url;
        } else {

            # --BEHAVIOR-- restCheckStoreUpdate
            dotclear()->behavior()->call('restCheckStoreUpdate', $post['store'], [& $mod], [& $url]);

            if (empty($mod) || empty($url)) {   // @phpstan-ignore-line
                throw new AdminException('Unknown store type');
            }
        }

        $repo = new Repository($mod, $url);
        $upd  = $repo->get(true);
        if (!empty($upd)) {
            $ret        = sprintf(__('An update is available', '%s updates are available.', count($upd)), count($upd));
            $rsp->check = true;
            $rsp->nb    = count($upd);
        }

        $rsp->ret = $ret;

        return $rsp;
    }

    /**
     * Get a post by its id
     *
     * @param   array   $get    Cleaned $_GET
     *
     * @return  XmlTag          The response
     */
    public static function getPostById(array $get): XmlTag
    {
        if (empty($get['id'])) {
            throw new AdminException('No post ID');
        }

        $params = ['post_id' => (int) $get['id']];

        if (isset($get['post_type'])) {
            $params['post_type'] = $get['post_type'];
        }

        $rs = dotclear()->blog()->posts()->getPosts($params);

        if ($rs->isEmpty()) {
            throw new AdminException('No post for this ID');
        }

        $rsp     = new XmlTag('post');
        $rsp->id = $rs->post_id;

        $rsp->blog_id($rs->blog_id);
        $rsp->user_id($rs->user_id);
        $rsp->cat_id($rs->cat_id);
        $rsp->post_dt($rs->post_dt);
        $rsp->post_creadt($rs->post_creadt);
        $rsp->post_upddt($rs->post_upddt);
        $rsp->post_format($rs->post_format);
        $rsp->post_url($rs->post_url);
        $rsp->post_lang($rs->post_lang);
        $rsp->post_title($rs->post_title);
        $rsp->post_excerpt($rs->post_excerpt);
        $rsp->post_excerpt_xhtml($rs->post_excerpt_xhtml);
        $rsp->post_content($rs->post_content);
        $rsp->post_content_xhtml($rs->post_content_xhtml);
        $rsp->post_notes($rs->post_notes);
        $rsp->post_status($rs->post_status);
        $rsp->post_selected($rs->post_selected);
        $rsp->post_open_comment($rs->post_open_comment);
        $rsp->post_open_tb($rs->post_open_tb);
        $rsp->nb_comment($rs->nb_comment);
        $rsp->nb_trackback($rs->nb_trackback);
        $rsp->user_name($rs->user_name);
        $rsp->user_firstname($rs->user_firstname);
        $rsp->user_displayname($rs->user_displayname);
        $rsp->user_email($rs->user_email);
        $rsp->user_url($rs->user_url);
        $rsp->cat_title($rs->cat_title);
        $rsp->cat_url($rs->cat_url);

        $rsp->post_display_content($rs->getContent(true));
        $rsp->post_display_excerpt($rs->getExcerpt(true));

        $metaTag = new XmlTag('meta');
        if (($meta = unserialize((string) $rs->post_meta)) !== false) {
            foreach ($meta as $K => $V) {
                foreach ($V as $v) {
                    $metaTag->$K($v);
                }
            }
        }
        $rsp->post_meta($metaTag);

        return $rsp;
    }

    /**
     * Get a comment by its id
     *
     * @param   array   $get    Cleaned $_GET
     *
     * @return  XmlTag          The response
     */
    public static function getCommentById(array $get): XmlTag
    {
        if (empty($get['id'])) {
            throw new AdminException('No comment ID');
        }

        $rs = dotclear()->blog()->comments()->getComments(['comment_id' => (int) $get['id']]);

        if ($rs->isEmpty()) {
            throw new AdminException('No comment for this ID');
        }

        $rsp     = new XmlTag('post');
        $rsp->id = $rs->comment_id;

        $rsp->comment_dt($rs->comment_dt);
        $rsp->comment_upddt($rs->comment_upddt);
        $rsp->comment_author($rs->comment_author);
        $rsp->comment_site($rs->comment_site);
        $rsp->comment_content($rs->comment_content);
        $rsp->comment_trackback($rs->comment_trackback);
        $rsp->comment_status($rs->comment_status);
        $rsp->post_title($rs->post_title);
        $rsp->post_url($rs->post_url);
        $rsp->post_id($rs->post_id);
        $rsp->post_dt($rs->post_dt);
        $rsp->user_id($rs->user_id);

        $rsp->comment_display_content($rs->getContent(true));

        if (dotclear()->user()->userID()) {
            $rsp->comment_ip($rs->comment_ip);
            $rsp->comment_email($rs->comment_email);
//!            $rsp->comment_spam_disp(dcAntispam::statusMessage($rs));
        }

        return $rsp;
    }

    /**
     * Do a post
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  XmlTag          The response
     */
    public static function quickPost(array $get, array $post): XmlTag
    {
        # Create category
        if (!empty($post['new_cat_title']) && dotclear()->user()->check('categories', dotclear()->blog()->id)) {
            $cur_cat            = dotclear()->con()->openCursor(dotclear()->prefix . 'category');
            $cur_cat->cat_title = $post['new_cat_title'];
            $cur_cat->cat_url   = '';

            $parent_cat = !empty($post['new_cat_parent']) ? $post['new_cat_parent'] : '';

            # --BEHAVIOR-- adminBeforeCategoryCreate
            dotclear()->behavior()->call('adminBeforeCategoryCreate', $cur_cat);

            $post['cat_id'] = dotclear()->blog()->categories()->addCategory($cur_cat, (int) $parent_cat);

            # --BEHAVIOR-- adminAfterCategoryCreate
            dotclear()->behavior()->call('adminAfterCategoryCreate', $cur_cat, $post['cat_id']);
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

        $cur->post_title        = !empty($post['post_title']) ? $post['post_title'] : '';
        $cur->user_id           = dotclear()->user()->userID();
        $cur->post_content      = !empty($post['post_content']) ? $post['post_content'] : '';
        $cur->cat_id            = !empty($post['cat_id']) ? (int) $post['cat_id'] : null;
        $cur->post_format       = !empty($post['post_format']) ? $post['post_format'] : 'xhtml';
        $cur->post_lang         = !empty($post['post_lang']) ? $post['post_lang'] : '';
        $cur->post_status       = !empty($post['post_status']) ? (int) $post['post_status'] : 0;
        $cur->post_open_comment = (int) dotclear()->blog()->settings()->system->allow_comments;
        $cur->post_open_tb      = (int) dotclear()->blog()->settings()->system->allow_trackbacks;

        # --BEHAVIOR-- adminBeforePostCreate
        dotclear()->behavior()->call('adminBeforePostCreate', $cur);

        $return_id = dotclear()->blog()->posts()->addPost($cur);

        # --BEHAVIOR-- adminAfterPostCreate
        dotclear()->behavior()->call('adminAfterPostCreate', $cur, $return_id);

        $rsp     = new XmlTag('post');
        $rsp->id = $return_id;

        $post = dotclear()->blog()->posts()->getPosts(['post_id' => $return_id]);

        $rsp->post_status = $post->post_status;
        $rsp->post_url    = $post->getURL();

        return $rsp;
    }

    /**
     * Check a post markup
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  XmlTag          The response
     */
    public static function validatePostMarkup(array $get, array $post): XmlTag
    {
        if (!isset($post['excerpt'])) {
            throw new AdminException('No entry excerpt');
        }

        if (!isset($post['content'])) {
            throw new AdminException('No entry content');
        }

        if (empty($post['format'])) {
            throw new AdminException('No entry format');
        }

        if (!isset($post['lang'])) {
            throw new AdminException('No entry lang');
        }

        $excerpt       = $post['excerpt'];
        $excerpt_xhtml = '';
        $content       = $post['content'];
        $content_xhtml = '';
        $format        = $post['format'];
        $lang          = $post['lang'];

        dotclear()->blog()->posts()->setPostContent(0, $format, $lang, $excerpt, $excerpt_xhtml, $content, $content_xhtml);

        $rsp = new XmlTag('result');

        $v = Validator::validate($excerpt_xhtml . $content_xhtml);

        $rsp->valid($v['valid']);
        $rsp->errors($v['errors']);

        return $rsp;
    }

    /**
     * Get zipped media
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  XmlTag          The response
     */
    public static function getZipMediaContent(array $get, array $post): XmlTag
    {
        if (empty($get['id'])) {
            throw new AdminException('No media ID');
        }

        $id = (int) $get['id'];

        if (!dotclear()->user()->check('media,media_admin', dotclear()->blog()->id)) {
            throw new AdminException('Permission denied');
        }

        if (!dotclear()->blog()->public_path) {
            throw new AdminException('No media path');
        }

        $file = null;

        try {
            $file = dotclear()->media()->getFile($id);
        } catch (\Exception $e) {
        }

        if ($file === null || $file->type != 'application/zip' || !$file->editable) {
            throw new AdminException('Not a valid file');
        }

        $rsp     = new XmlTag('result');
        $content = dotclear()->media()->getZipContent($file);

        foreach ($content as $k => $v) {
            $rsp->file($k);
        }

        return $rsp;
    }

    /**
     * Get meta
     *
     * @param   array   $get    Cleaned $_GET
     *
     * @return  XmlTag          The response
     */
    public static function getMeta(array $get): XmlTag
    {
        $postid   = !empty($get['postId']) ? $get['postId'] : null;
        $limit    = !empty($get['limit']) ? $get['limit'] : null;
        $metaId   = !empty($get['metaId']) ? $get['metaId'] : null;
        $metaType = !empty($get['metaType']) ? $get['metaType'] : null;

        $sortby = !empty($get['sortby']) ? $get['sortby'] : 'meta_type,asc';

        $rs = dotclear()->meta()->getMetadata([
            'meta_type' => $metaType,
            'limit'     => $limit,
            'meta_id'   => $metaId,
            'post_id'   => $postid, ]);
        $rs = dotclear()->meta()->computeMetaStats($rs);

        $sortby = explode(',', $sortby);
        $sort   = $sortby[0];
        $order  = $sortby[1] ?? 'asc';

        switch ($sort) {
            case 'metaId':
                $sort = 'meta_id_lower';

                break;
            case 'count':
                $sort = 'count';

                break;
            case 'metaType':
                $sort = 'meta_type';

                break;
            default:
                $sort = 'meta_type';
        }

        $rs->sort($sort, $order);

        $rsp = new XmlTag();

        while ($rs->fetch()) {
            $metaTag               = new XmlTag('meta');
            $metaTag->type         = $rs->meta_type;
            $metaTag->uri          = rawurlencode($rs->meta_id);
            $metaTag->count        = $rs->count;
            $metaTag->percent      = $rs->percent;
            $metaTag->roundpercent = $rs->roundpercent;
            $metaTag->CDATA($rs->meta_id);

            $rsp->insertNode($metaTag);
        }

        return $rsp;
    }

    /**
     * Set a post meta
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  bool            The success
     */
    public static function setPostMeta(array $get, array $post): bool
    {
        if (empty($post['postId'])) {
            throw new AdminException('No post ID');
        }

        if (empty($post['meta']) && $post['meta'] != '0') {
            throw new AdminException('No meta');
        }

        if (empty($post['metaType'])) {
            throw new AdminException('No meta type');
        }

        # Get previous meta for post
        $post_meta = dotclear()->meta()->getMetadata([
            'meta_type' => $post['metaType'],
            'post_id'   => $post['postId'], ]);
        $pm = [];
        while ($post_meta->fetch()) {
            $pm[] = $post_meta->meta_id;
        }

        foreach (dotclear()->meta()->splitMetaValues($post['meta']) as $m) {
            if (!in_array($m, $pm)) {
                dotclear()->meta()->setPostMeta($post['postId'], $post['metaType'], $m);
            }
        }

        return true;
    }

    /**
     * Delete meta
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  bool            The success
     */
    public static function delMeta(array $get, array $post): bool
    {
        if (empty($post['postId'])) {
            throw new AdminException('No post ID');
        }

        if (empty($post['metaId']) && $post['metaId'] != '0') {
            throw new AdminException('No meta ID');
        }

        if (empty($post['metaType'])) {
            throw new AdminException('No meta type');
        }

        dotclear()->meta()->delPostMeta($post['postId'], $post['metaType'], $post['metaId']);

        return true;
    }

    /**
     * Search meta
     *
     * @param   array   $get    Cleaned $_GET
     *
     * @return  XmlTag          The response
     */
    public static function searchMeta(array $get): XmlTag
    {
        $q        = !empty($get['q']) ? $get['q'] : null;
        $metaType = !empty($get['metaType']) ? $get['metaType'] : null;

        $sortby = !empty($get['sortby']) ? $get['sortby'] : 'meta_type,asc';

        $rs = dotclear()->meta()->getMetadata(['meta_type' => $metaType]);
        $rs = dotclear()->meta()->computeMetaStats($rs);

        $sortby = explode(',', $sortby);
        $sort   = $sortby[0];
        $order  = $sortby[1] ?? 'asc';

        switch ($sort) {
            case 'metaId':
                $sort = 'meta_id_lower';

                break;
            case 'count':
                $sort = 'count';

                break;
            case 'metaType':
                $sort = 'meta_type';

                break;
            default:
                $sort = 'meta_type';
        }

        $rs->sort($sort, $order);

        $rsp = new XmlTag();

        while ($rs->fetch()) {
            if (stripos($rs->meta_id, $q) === 0) {
                $metaTag               = new XmlTag('meta');
                $metaTag->type         = $rs->meta_type;
                $metaTag->uri          = rawurlencode($rs->meta_id);
                $metaTag->count        = $rs->count;
                $metaTag->percent      = $rs->percent;
                $metaTag->roundpercent = $rs->roundpercent;
                $metaTag->CDATA($rs->meta_id);

                $rsp->insertNode($metaTag);
            }
        }

        return $rsp;
    }

    /**
     * Set preference on foldable section
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  bool            The success
     */
    public static function setSectionFold(array $get, array $post): bool
    {
        if (empty($post['section'])) {
            throw new AdminException('No section name');
        }
        if (dotclear()->user()->preference()->toggles === null) {
            dotclear()->user()->preference()->addWorkspace('toggles');
        }
        $section = $post['section'];
        $status  = isset($post['value']) && ($post['value'] != 0);
        if (dotclear()->user()->preference()->toggles->prefExists('unfolded_sections')) {
            $toggles = explode(',', trim((string) dotclear()->user()->preference()->toggles->unfolded_sections));
        } else {
            $toggles = [];
        }
        $k = array_search($section, $toggles);
        if ($status) {
            // true == Fold section ==> remove it from unfolded list
            if ($k !== false) {
                unset($toggles[$k]);
            }
        } else {
            // false == unfold section ==> add it to unfolded list
            if ($k === false) {
                $toggles[] = $section;
            };
        }
        dotclear()->user()->preference()->toggles->put('unfolded_sections', join(',', $toggles));

        return true;
    }

    /**
     * Set dashboard elements position
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  bool            The success
     */
    public static function setDashboardPositions(array $get, array $post): bool
    {
        if (empty($post['id'])) {
            throw new AdminException('No zone name');
        }
        if (empty($post['list'])) {
            throw new AdminException('No sorted list of id');
        }

        if (dotclear()->user()->preference()->dashboard === null) {
            dotclear()->user()->preference()->addWorkspace('dashboard');
        }

        $zone  = $post['id'];
        $order = $post['list'];

        dotclear()->user()->preference()->dashboard->put($zone, $order);

        return true;
    }

    /**
     * Set list option preference
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  XmlTag          The response
     */
    public static function setListsOptions(array $get, array $post): XmlTag
    {
        if (empty($post['id'])) {
            throw new AdminException('No list name');
        }

        $sorts = dotclear()->listoption()->getUserFilters();

        if (!isset($sorts[$post['id']])) {
            throw new AdminException('List name invalid');
        }

        $res = new XmlTag('result');

        $su = [];
        foreach ($sorts as $sort_type => $sort_data) {
            if (null !== $sort_data[1]) {
                $k                 = 'sort';
                $su[$sort_type][0] = $sort_type == $post['id'] && isset($post[$k]) && in_array($post[$k], $sort_data[1]) ? $post[$k] : $sort_data[2];
            }
            if (null !== $sort_data[3]) {
                $k                 = 'order';
                $su[$sort_type][1] = $sort_type == $post['id'] && isset($post[$k]) && in_array($post[$k], ['asc', 'desc']) ? $post[$k] : $sort_data[3];
            }
            if (null !== $sort_data[4]) {
                $k                 = 'nb';
                $su[$sort_type][2] = $sort_type == $post['id'] && isset($post[$k]) ? abs((int) $post[$k]) : $sort_data[4][1];
            }
        }
        if (dotclear()->user()->preference()->interface === null) {
            dotclear()->user()->preference()->addWorkspace('interface');
        }
        dotclear()->user()->preference()->interface->put('sorts', $su, 'array');

        $res->msg = __('List options saved');

        return $res;
    }

    /**
     * Get a module (define) by its id
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @return  XmlTag          The response
     */
    public static function getModuleById(array $get, array $post): XmlTag
    {
        if (empty($get['id'])) {
            throw new AdminException('No module ID');
        }
        if (empty($get['list'])) {
            throw new AdminException('No list ID');
        }

        $id     = $get['id'];
        $list   = $get['list'];
        $module = [];

        if ($list == 'plugin-activate') {
            $modules = dotclear()->plugins->getModules();
            if (empty($modules) || !isset($modules[$id])) {
                throw new AdminException('Unknown module ID');
            }
            $module = $modules[$id];
        } elseif ($list == 'plugin-new') {
            $store = new Repository(
                dotclear()->plugins,
                dotclear()->blog()->settings()->system->store_plugin_url
            );
            $store->check();

            $modules = $store->get();
            if (empty($modules) || !isset($modules[$id])) {
                throw new AdminException('Unknown module ID');
            }
            $module = $modules[$id];
        }
        // behavior not implemented yet

        if (empty($module)) {
            throw new AdminException('Unknown module ID');
        }

        $rsp     = new XmlTag('module');
        $rsp->id = $id;

        foreach ($module->properties() as $k => $v) {
            $rsp->{$k}((string) $v);
        }

        return $rsp;
    }

    /**
     * Register Dotclear default Rest methods
     */
    public static function initDefaultRestMethods()
    {
        $methods = [
            'getPostsCount',
            'getCommentsCount',
            'checkNewsUpdate',
            'checkCoreUpdate',
            'checkStoreUpdate',
            'getPostById',
            'getCommentById',
            'quickPost',
            'validatePostMarkup',
            'getZipMediaContent',
            'getMeta',
            'delMeta',
            'setPostMeta',
            'searchMeta',
            'setSectionFold',
            'getModuleById',
            'setDashboardPositions',
            'setListsOptions',
        ];

        foreach($methods as $method) {
            dotclear()->rest()->addFunction($method, [__CLASS__, $method]);
        }
    }
}
