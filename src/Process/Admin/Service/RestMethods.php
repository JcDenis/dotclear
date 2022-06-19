<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Service;

// Dotclear\Process\Admin\Service\RestMethods
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\Validator;
use Dotclear\Helper\Html\XmlTag;
use Dotclear\Helper\Network\Feed\Reader;
use Dotclear\Helper\Text;
use Dotclear\Modules\Repository\Repository;

/**
 * Common admin REST methods.
 *
 * @ingroup  Admin Rest
 */
class RestMethods
{
    /**
     * Register Dotclear default Rest methods.
     */
    public function __construct()
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

        foreach ($methods as $method) {
            App::core()->rest()->addFunction($method, [$this, $method]);
        }
    }

    /**
     * Get number of posts (whatever are their status) for current blog.
     *
     * @param array $get Cleaned $_GET
     *
     * @return XmlTag The response
     */
    public function getPostsCount(array $get): XmlTag
    {
        $count = App::core()->blog()->posts()->countPosts();
        $str   = sprintf(__('%d post', '%d posts', $count), $count);

        $rsp = new XmlTag('count');
        $rsp->insertAttr('ret', $str);

        return $rsp;
    }

    /**
     * Get number of comments (whatever are their status) for current blog.
     *
     * @param array $get Cleaned $_GET
     *
     * @return XmlTag The response
     */
    public function getCommentsCount(array $get): XmlTag
    {
        $count = App::core()->blog()->comments()->countComments();
        $str   = sprintf(__('%d comment', '%d comments', $count), $count);

        $rsp = new XmlTag('count');
        $rsp->insertAttr('ret', $str);

        return $rsp;
    }

    /**
     * Check news update.
     *
     * @param array $get Cleaned $_GET
     *
     * @return XmlTag The response
     */
    public function checkNewsUpdate(array $get): XmlTag
    {
        // Dotclear news

        $rsp = new XmlTag('news');
        $rsp->insertAttr('check', false);
        $ret = __('Dotclear news not available');

        if (App::core()->user()->preference()->get('dashboard')->get('dcnews')) {
            try {
                if (!App::core()->help()->news()) {
                    throw new AdminException();
                }
                $feed_reader = new Reader();
                $feed_reader->setCacheDir(App::core()->config()->get('cache_dir'));
                $feed_reader->setTimeout(2);
                $feed_reader->setUserAgent('Dotclear - https://dotclear.org/');
                $feed = $feed_reader->parse(App::core()->help()->news());
                if ($feed) {
                    $ret = '<div class="box medium dc-box" id="ajax-news"><h3>' . __('Dotclear news') . '</h3><dl id="news">';
                    $i   = 1;
                    foreach ($feed->items as $item) {
                        $dt = isset($item->link) ? '<a href="' . $item->link . '" class="outgoing" title="' . $item->title . '">' .
                        $item->title . ' <img src="?df=images/outgoing-link.svg" alt="" /></a>' : $item->title;

                        $ret .= '<dt>' . $dt . '</dt>' .
                        '<dd><p><strong>' . Clock::str(format: __('%d %B %Y:'), date: $item->pubdate, to: 'Europe/Paris') . '</strong> ' .
                        '<em>' . Text::cutString(Html::clean($item->content), 120) . '...</em></p></dd>';
                        ++$i;
                        if (2 < $i) {
                            break;
                        }
                    }
                    $ret .= '</dl></div>';
                    $rsp->insertAttr('check', true);
                }
            } catch (\Exception) {
            }
        }
        $rsp->insertAttr('ret', $ret);

        return $rsp;
    }

    /**
     * Check core update.
     *
     * @param array $get Cleaned $_GET
     *
     * @return XmlTag The response
     */
    public function checkCoreUpdate(array $get): XmlTag
    {
        // Dotclear updates notifications

        $rsp = new XmlTag('update');
        $rsp->insertAttr('check', false);
        $ret = __('Dotclear update not available');

        if (App::core()->user()->isSuperAdmin()
            && !App::core()->config()->get('core_update_noauto')
            && is_readable(App::core()->config()->get('digests_dir'))
            && !App::core()->user()->preference()->get('dashboard')->get('nodcupdate')
        ) {
            $updater      = new Updater(App::core()->config()->get('core_update_url'), 'dotclear', App::core()->config()->get('core_update_channel'), App::core()->config()->get('cache_dir') . '/versions');
            $new_v        = $updater->check(App::core()->config()->get('core_version'));
            $version_info = empty($new_v) ? '' : $updater->getInfoURL();

            if ($updater->getNotify() && !empty($new_v)) {
                // Check PHP version required
                if (version_compare(phpversion(), $updater->getPHPVersion()) >= 0) {
                    $ret = '<div class="dc-update" id="ajax-update"><h3>' . sprintf(__('Dotclear %s is available!'), $new_v) . '</h3> ' .
                    '<p><a class="button submit" href="' . App::core()->adminurl()->get('admin.update') . '">' . sprintf(__('Upgrade now'), $new_v) . '</a> ' .
                    '<a class="button" href="' . App::core()->adminurl()->get('admin.update', ['hide_msg' => 1]) . '">' . __('Remind me later') . '</a>' .
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
                $rsp->insertAttr('check', true);
            } else {
                if (version_compare(phpversion(), App::core()->config()->get('php_next_required'), '<')) {
                    if (!App::core()->user()->preference()->get('interface')->get('hidemoreinfo')) {
                        $ret = '<p class="info">' .
                        sprintf(
                            __('The next versions of Dotclear will not support PHP version < %s, your\'s is currently %s'),
                            App::core()->config()->get('php_next_required'),
                            phpversion()
                        ) .
                        '</p>';
                        $rsp->insertAttr('check', true);
                    }
                }
            }
        }
        $rsp->insertAttr('ret', $ret);

        return $rsp;
    }

    /**
     * Check repository update.
     *
     * @param array<string,mixed> $get  Cleaned $_GET
     * @param array<string,mixed> $post Cleaned $_POST
     *
     * @return XmlTag The response
     */
    public function checkStoreUpdate(array $get, array $post): XmlTag
    {
        // Dotclear store updates notifications

        $rsp = new XmlTag('update');
        $rsp->insertAttr('check', false);
        $rsp->insertAttr('nb', 0);

        $ret = __('No updates are available');
        $mod = null;
        $url = '';

        if (empty($post['store'])) {
            throw new AdminException('No store type');
        }

        $upd = new ArrayObject([]);

        // --BEHAVIOR-- restCheckStoreUpdate, string, ArrayObject
        App::core()->behavior('restCheckStoreUpdate')->call($post['store'], $upd);

        if (count($upd)) {
            $ret = sprintf(__('An update is available', '%s updates are available.', count($upd)), count($upd));
            $rsp->insertAttr('check', true);
            $rsp->insertAttr('nb', count($upd));
        }

        $rsp->insertAttr('ret', $ret);

        return $rsp;
    }

    /**
     * Get a post by its id.
     *
     * @param array $get Cleaned $_GET
     *
     * @return XmlTag The response
     */
    public function getPostById(array $get): XmlTag
    {
        if (empty($get['id'])) {
            throw new AdminException('No post ID');
        }

        $param = new Param();
        $param->set('post_id', (int) $get['id']);

        if (isset($get['post_type'])) {
            $param->set('post_type', $get['post_type']);
        }

        $rs = App::core()->blog()->posts()->getPosts(param: $param);

        if ($rs->isEmpty()) {
            throw new AdminException('No post for this ID');
        }

        $rsp = new XmlTag('post');
        $rsp->insertAttr('id', $rs->field('post_id'));

        $rsp->insertNode([
            'blog_id'            => $rs->field('blog_id'),
            'user_id'            => $rs->field('user_id'),
            'cat_id'             => $rs->field('cat_id'),
            'post_dt'            => $rs->field('post_dt'),
            'post_creadt'        => $rs->field('post_creadt'),
            'post_upddt'         => $rs->field('post_upddt'),
            'post_format'        => $rs->field('post_format'),
            'post_url'           => $rs->field('post_url'),
            'post_lang'          => $rs->field('post_lang'),
            'post_title'         => $rs->field('post_title'),
            'post_excerpt'       => $rs->field('post_excerpt'),
            'post_excerpt_xhtml' => $rs->field('post_excerpt_xhtml'),
            'post_content'       => $rs->field('post_content'),
            'post_content_xhtml' => $rs->field('post_content_xhtml'),
            'post_notes'         => $rs->field('post_notes'),
            'post_status'        => $rs->field('post_status'),
            'post_selected'      => $rs->field('post_selected'),
            'post_open_comment'  => $rs->field('post_open_comment'),
            'post_open_tb'       => $rs->field('post_open_tb'),
            'nb_comment'         => $rs->field('nb_comment'),
            'nb_trackback'       => $rs->field('nb_trackback'),
            'user_name'          => $rs->field('user_name'),
            'user_firstname'     => $rs->field('user_firstname'),
            'user_displayname'   => $rs->field('user_displayname'),
            'user_email'         => $rs->field('user_email'),
            'user_url'           => $rs->field('user_url'),
            'cat_title'          => $rs->field('cat_title'),
            'cat_url'            => $rs->field('cat_url'),

            'post_display_content' => $rs->getContent(true),
            'post_display_excerpt' => $rs->getExcerpt(true),
        ]);

        $metaTag = new XmlTag('meta');
        if (false !== ($meta = unserialize((string) $rs->field('post_meta')))) {
            foreach ($meta as $K => $V) {
                foreach ($V as $v) {
                    $metaTag->insertNode([$K => $v]);
                }
            }
        }
        $rsp->insertNode(['post_meta' => $metaTag]);

        return $rsp;
    }

    /**
     * Get a comment by its id.
     *
     * @param array $get Cleaned $_GET
     *
     * @return XmlTag The response
     */
    public function getCommentById(array $get): XmlTag
    {
        if (empty($get['id'])) {
            throw new AdminException('No comment ID');
        }

        $param = new Param();
        $param->set('comment_id', (int) $get['id']);
        $rs = App::core()->blog()->comments()->getComments(param: $param);

        if ($rs->isEmpty()) {
            throw new AdminException('No comment for this ID');
        }

        $rsp = new XmlTag('post');
        $rsp->insertAttr('id', $rs->field('comment_id'));

        $rsp->insertNode([
            'comment_dt'        => $rs->field('comment_dt'),
            'comment_upddt'     => $rs->field('comment_upddt'),
            'comment_author'    => $rs->field('comment_author'),
            'comment_site'      => $rs->field('comment_site'),
            'comment_content'   => $rs->field('comment_content'),
            'comment_trackback' => $rs->field('comment_trackback'),
            'comment_status'    => $rs->field('comment_status'),
            'post_title'        => $rs->field('post_title'),
            'post_url'          => $rs->field('post_url'),
            'post_id'           => $rs->field('post_id'),
            'post_dt'           => $rs->field('post_dt'),
            'user_id'           => $rs->field('user_id'),

            'comment_display_content' => $rs->getContent(true),
        ]);

        if (App::core()->user()->userID()) {
            $rsp->insertNode([
                'comment_ip'    => $rs->field('comment_ip'),
                'comment_email' => $rs->field('comment_email'),
            ]);
            // !            $rsp->insertNode(['comment_spam_disp' => dcAntispam::statusMessage($rs)]);
        }

        return $rsp;
    }

    /**
     * Do a post.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return XmlTag The response
     */
    public function quickPost(array $get, array $post): XmlTag
    {
        // Create category
        if (!empty($post['new_cat_title']) && App::core()->user()->check('categories', App::core()->blog()->id)) {
            $cursor = App::core()->con()->openCursor(App::core()->prefix() . 'category');
            $cursor->setField('cat_title', $post['new_cat_title']);
            $cursor->setField('cat_url', '');

            $post['cat_id'] = App::core()->blog()->categories()->createCategory(
                cursor: $cursor,
                parent: !empty($post['new_cat_parent']) ? (int) $post['new_cat_parent'] : 0
            );
            unset($cursor);
        }

        $cursor = App::core()->con()->openCursor(App::core()->prefix() . 'post');

        $cursor->setField('post_title', !empty($post['post_title']) ? $post['post_title'] : '');
        $cursor->setField('user_id', App::core()->user()->userID());
        $cursor->setField('post_content', !empty($post['post_content']) ? $post['post_content'] : '');
        $cursor->setField('cat_id', !empty($post['cat_id']) ? (int) $post['cat_id'] : null);
        $cursor->setField('post_format', !empty($post['post_format']) ? $post['post_format'] : 'xhtml');
        $cursor->setField('post_lang', !empty($post['post_lang']) ? $post['post_lang'] : '');
        $cursor->setField('post_status', !empty($post['post_status']) ? (int) $post['post_status'] : 0);
        $cursor->setField('post_open_comment', (int) App::core()->blog()->settings()->getGroup('system')->getSetting('allow_comments'));
        $cursor->setField('post_open_tb', (int) App::core()->blog()->settings()->getGroup('system')->getSetting('allow_trackbacks'));

        $return_id = App::core()->blog()->posts()->createPost(cursor: $cursor);

        $rsp = new XmlTag('post');
        $rsp->insertAttr('id', $return_id);

        $param = new Param();
        $param->set('post_id', $return_id);
        $post = App::core()->blog()->posts()->getPosts(param: $param);

        $rsp->insertAttr('post_status', $post->field('post_status'));
        $rsp->insertAttr('post_url', $post->getURL());

        return $rsp;
    }

    /**
     * Check a post markup.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return XmlTag The response
     */
    public function validatePostMarkup(array $get, array $post): XmlTag
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

        App::core()->blog()->posts()->formatPostContent(
            id: 0,
            format: $post['format'],
            lang: $post['lang'],
            excerpt: $excerpt,
            excerpt_xhtml: $excerpt_xhtml,
            content: $content,
            content_xhtml: $content_xhtml
        );

        $rsp = new XmlTag('result');

        $v = Validator::validate($excerpt_xhtml . $content_xhtml);

        $rsp->insertNode([
            'valid'  => $v['valid'],
            'errors' => $v['errors'],
        ]);

        return $rsp;
    }

    /**
     * Get zipped media.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return XmlTag The response
     */
    public function getZipMediaContent(array $get, array $post): XmlTag
    {
        if (empty($get['id'])) {
            throw new AdminException('No media ID');
        }

        $id = (int) $get['id'];

        if (!App::core()->user()->check('media,media_admin', App::core()->blog()->id)) {
            throw new AdminException('Permission denied');
        }

        if (!App::core()->media()) {
            throw new AdminException('No media path');
        }

        $file = null;

        try {
            $file = App::core()->media()->getFile($id);
        } catch (\Exception) {
        }

        if (null === $file || 'application/zip' != $file->type || !$file->editable) {
            throw new AdminException('Not a valid file');
        }

        $rsp     = new XmlTag('result');
        $content = App::core()->media()->getZipContent($file);

        foreach ($content as $k => $v) {
            $rsp->insertNode(['file' => $k]);
        }

        return $rsp;
    }

    /**
     * Get meta.
     *
     * @param array $get Cleaned $_GET
     *
     * @return XmlTag The response
     */
    public function getMeta(array $get): XmlTag
    {
        $postId   = !empty($get['postId']) ? (int) $get['postId'] : null;
        $limit    = !empty($get['limit']) ? $get['limit'] : null;
        $metaId   = !empty($get['metaId']) ? $get['metaId'] : null;
        $metaType = !empty($get['metaType']) ? $get['metaType'] : null;

        $sortby = !empty($get['sortby']) ? $get['sortby'] : 'meta_type,asc';

        $param = new Param();
        $param->set('meta_type', $metaType);
        $param->set('limit', $limit);
        $param->set('meta_id', $metaId);
        $param->set('post_id', $postId);

        $rs = App::core()->meta()->getMetadata(param: $param);
        $rs = App::core()->meta()->computeMetaStats($rs);

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
            $metaTag = new XmlTag('meta');
            $metaTag->insertAttr('type', $rs->field('meta_type'));
            $metaTag->insertAttr('uri', rawurlencode($rs->field('meta_id')));
            $metaTag->insertAttr('count', $rs->field('count'));
            $metaTag->insertAttr('percent', $rs->field('percent'));
            $metaTag->insertAttr('roundpercent', $rs->field('roundpercent'));
            $metaTag->CDATA($rs->field('meta_id'));

            $rsp->insertNode($metaTag);
        }

        return $rsp;
    }

    /**
     * Set a post meta.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return bool The success
     */
    public function setPostMeta(array $get, array $post): bool
    {
        if (empty($post['postId'])) {
            throw new AdminException('No post ID');
        }

        if (empty($post['meta']) && '0' != $post['meta']) {
            throw new AdminException('No meta');
        }

        if (empty($post['metaType'])) {
            throw new AdminException('No meta type');
        }

        // Get previous meta for post
        $param = new Param();
        $param->set('meta_type', $post['metaType']);
        $param->set('post_id', (int) $post['postId']);

        $post_meta = App::core()->meta()->getMetadata(param: $param);
        $pm        = [];
        while ($post_meta->fetch()) {
            $pm[] = $post_meta->field('meta_id');
        }

        foreach (App::core()->meta()->splitMetaValues($post['meta']) as $m) {
            if (!in_array($m, $pm)) {
                App::core()->meta()->setPostMeta((int) $post['postId'], $post['metaType'], $m);
            }
        }

        return true;
    }

    /**
     * Delete meta.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return bool The success
     */
    public function delMeta(array $get, array $post): bool
    {
        if (empty($post['postId'])) {
            throw new AdminException('No post ID');
        }

        if (empty($post['metaId']) && '0' != $post['metaId']) {
            throw new AdminException('No meta ID');
        }

        if (empty($post['metaType'])) {
            throw new AdminException('No meta type');
        }

        App::core()->meta()->delPostMeta((int) $post['postId'], $post['metaType'], $post['metaId']);

        return true;
    }

    /**
     * Search meta.
     *
     * @param array $get Cleaned $_GET
     *
     * @return XmlTag The response
     */
    public function searchMeta(array $get): XmlTag
    {
        $q        = !empty($get['q']) ? $get['q'] : null;
        $metaType = !empty($get['metaType']) ? $get['metaType'] : null;

        $sortby = !empty($get['sortby']) ? $get['sortby'] : 'meta_type,asc';

        $param = new Param();
        $param->set('meta_type', $metaType);

        $rs = App::core()->meta()->getMetadata(param: $param);
        $rs = App::core()->meta()->computeMetaStats($rs);

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
            if (0 === stripos($rs->field('meta_id'), $q)) {
                $metaTag = new XmlTag('meta');
                $metaTag->insertAttr('type', $rs->field('meta_type'));
                $metaTag->insertAttr('uri', rawurlencode($rs->field('meta_id')));
                $metaTag->insertAttr('count', $rs->field('count'));
                $metaTag->insertAttr('percent', $rs->field('percent'));
                $metaTag->insertAttr('roundpercent', $rs->field('roundpercent'));
                $metaTag->CDATA($rs->field('meta_id'));

                $rsp->insertNode($metaTag);
            }
        }

        return $rsp;
    }

    /**
     * Set preference on foldable section.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return bool The success
     */
    public function setSectionFold(array $get, array $post): bool
    {
        if (empty($post['section'])) {
            throw new AdminException('No section name');
        }

        $section = $post['section'];
        $status  = isset($post['value']) && 0 != $post['value'];
        if (App::core()->user()->preference()->get('toggles')->prefExists('unfolded_sections')) {
            $toggles = explode(',', trim((string) App::core()->user()->preference()->get('toggles')->get('unfolded_sections')));
        } else {
            $toggles = [];
        }
        $k = array_search($section, $toggles);
        if ($status) {
            // true == Fold section ==> remove it from unfolded list
            if (false !== $k) {
                unset($toggles[$k]);
            }
        } else {
            // false == unfold section ==> add it to unfolded list
            if (false === $k) {
                $toggles[] = $section;
            }
        }
        App::core()->user()->preference()->get('toggles')->put('unfolded_sections', join(',', $toggles));

        return true;
    }

    /**
     * Set dashboard elements position.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return bool The success
     */
    public function setDashboardPositions(array $get, array $post): bool
    {
        if (empty($post['id'])) {
            throw new AdminException('No zone name');
        }
        if (empty($post['list'])) {
            throw new AdminException('No sorted list of id');
        }

        $zone  = $post['id'];
        $order = $post['list'];

        App::core()->user()->preference()->get('dashboard')->put($zone, $order);

        return true;
    }

    /**
     * Set list option preference.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return XmlTag The response
     */
    public function setListsOptions(array $get, array $post): XmlTag
    {
        if (empty($post['id'])) {
            throw new AdminException('No list name');
        }

        $sorts = App::core()->listoption()->sort()->getGroups();

        if (!isset($sorts[$post['id']])) {
            throw new AdminException('List name invalid');
        }

        $rsp = new XmlTag('result');

        $su = [];
        foreach ($sorts as $group) {
            if (null !== $group->getSortBy()) {
                $k                 = 'sort';
                $su[$group->id][0] = $group->id == $post['id'] && isset($post[$k]) && in_array($post[$k], $group->combo) ? $post[$k] : $group->getSortBy();
            }
            if (null !== $group->getSortOrder()) {
                $k                 = 'order';
                $su[$group->id][1] = $group->id == $post['id'] && isset($post[$k]) && in_array($post[$k], App::core()->combo()->getOrderCombo()) ? $post[$k] : $group->getSortOrder();
            }
            if (null !== $group->getSortLimit()) {
                $k                 = 'nb';
                $su[$group->id][2] = $group->id == $post['id'] && isset($post[$k]) ? abs((int) $post[$k]) : $group->getSortLimit();
            }
        }

        App::core()->user()->preference()->get('interface')->put('sorts', $su, 'array');

        $rsp->insertAttr('msg', __('List options saved'));

        return $rsp;
    }

    /**
     * Get a module (define) by its id.
     *
     * @param array $get  Cleaned $_GET
     * @param array $post Cleaned $_POST
     *
     * @return XmlTag The response
     */
    public function getModuleById(array $get, array $post): XmlTag
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

        if ('plugin-activate' == $list) {
            $modules = App::core()->plugins()->getModules();
            if (empty($modules) || !isset($modules[$id])) {
                throw new AdminException('Unknown module ID');
            }
            $module = $modules[$id];
        } elseif ('plugin-new' == $list) {
            $store = new Repository(
                App::core()->plugins(),
                App::core()->blog()->settings()->getGroup('system')->getSetting('store_plugin_url')
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

        $rsp = new XmlTag('module');
        $rsp->insertAttr('id', $id);

        foreach ($module->properties() as $k => $v) {
            $rsp->insertNode([$k => (string) $v]);
        }

        return $rsp;
    }
}
