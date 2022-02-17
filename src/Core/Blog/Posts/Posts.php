<?php
/**
 * @class Dotclear\Core\Blog\Posts\Posts
 * @brief Dotclear core posts class
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Posts;

use ArrayObject;

use Dotclear\Core\Utils;
use Dotclear\Database\Record;
use Dotclear\Database\Cursor;
use Dotclear\Exception\CoreException;
use Dotclear\Exception\DeprecatedException;
use Dotclear\Html\Html;
use Dotclear\Utils\Dt;
use Dotclear\Utils\Text;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Posts
{
    /**
     * Retrieves entries. <b>$params</b> is an array taking the following
     * optionnal parameters:
     *
     * - no_content: Don't retrieve entry content (excerpt and content)
     * - post_type: Get only entries with given type (default "post", array for many types and '' for no type)
     * - post_id: (integer or array) Get entry with given post_id
     * - post_url: Get entry with given post_url field
     * - user_id: (integer) Get entries belonging to given user ID
     * - cat_id: (string or array) Get entries belonging to given category ID
     * - cat_id_not: deprecated (use cat_id with "id ?not" instead)
     * - cat_url: (string or array) Get entries belonging to given category URL
     * - cat_url_not: deprecated (use cat_url with "url ?not" instead)
     * - post_status: (integer) Get entries with given post_status
     * - post_selected: (boolean) Get select flaged entries
     * - post_year: (integer) Get entries with given year
     * - post_month: (integer) Get entries with given month
     * - post_day: (integer) Get entries with given day
     * - post_lang: Get entries with given language code
     * - search: Get entries corresponding of the following search string
     * - columns: (array) More columns to retrieve
     * - sql: Append SQL string at the end of the query
     * - from: Append SQL string after "FROM" statement in query
     * - order: Order of results (default "ORDER BY post_dt DES")
     * - limit: Limit parameter
     * - sql_only : return the sql request instead of results. Only ids are selected
     * - exclude_post_id : (integer or array) Exclude entries with given post_id
     *
     * Please note that on every cat_id or cat_url, you can add ?not to exclude
     * the category and ?sub to get subcategories.
     *
     * @since 3.0 : remove sql_only params (reimplement someting later)
     *
     * @param    ArrayObject|array  $params        Parameters
     * @param    bool   $count_only    Only counts results
     *
     * @return   Record    A record with some more capabilities or the SQL request
     */
    public function getPosts(ArrayObject|array $params = [], bool $count_only = false): Record
    {
        $params = new ArrayObject($params);

        # --BEHAVIOR-- coreBlogBeforeGetPosts ArrayObject
        dotclear()->behavior()->call('coreBlogBeforeGetPosts', $params);

        if ($count_only) {
            $strReq = 'SELECT count(DISTINCT P.post_id) ';
        } elseif (!empty($params['sql_only'])) {
            DeprecatedException::throw();
            $strReq = 'SELECT P.post_id ';
        } else {
            if (!empty($params['no_content'])) {
                $content_req = '';
            } else {
                $content_req = 'post_excerpt, post_excerpt_xhtml, ' .
                    'post_content, post_content_xhtml, post_notes, ';
            }

            if (!empty($params['columns']) && is_array($params['columns'])) {
                $content_req .= implode(', ', $params['columns']) . ', ';
            }

            $strReq = 'SELECT P.post_id, P.blog_id, P.user_id, P.cat_id, post_dt, ' .
                'post_tz, post_creadt, post_upddt, post_format, post_password, ' .
                'post_url, post_lang, post_title, ' . $content_req .
                'post_type, post_meta, ' .
                'post_status, post_firstpub, post_selected, post_position, ' .
                'post_open_comment, post_open_tb, nb_comment, nb_trackback, ' .
                'U.user_name, U.user_firstname, U.user_displayname, U.user_email, ' .
                'U.user_url, ' .
                'C.cat_title, C.cat_url, C.cat_desc ';
        }

        $strReq .= 'FROM ' . dotclear()->prefix . 'post P ' .
        'INNER JOIN ' . dotclear()->prefix . 'user U ON U.user_id = P.user_id ' .
        'LEFT OUTER JOIN ' . dotclear()->prefix . 'category C ON P.cat_id = C.cat_id ';

        if (!empty($params['from'])) {
            $strReq .= $params['from'] . ' ';
        }

        $strReq .= "WHERE P.blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";

        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= 'AND ((post_status = 1 ';

            if (dotclear()->blog()->withoutPassword()) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (dotclear()->user()->userID()) {
                $strReq .= "OR P.user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        #Adding parameters
        if (isset($params['post_type'])) {
            if (is_array($params['post_type']) || $params['post_type'] != '') {
                $strReq .= 'AND post_type ' . dotclear()->con()->in($params['post_type']);
            }
        } else {
            $strReq .= "AND post_type = 'post' ";
        }

        if (isset($params['post_id']) && $params['post_id'] !== '') {
            if (is_array($params['post_id'])) {
                array_walk($params['post_id'], function (&$v, $k) { if ($v !== null) {$v = (int) $v;}});
            } else {
                $params['post_id'] = [(int) $params['post_id']];
            }
            $strReq .= 'AND P.post_id ' . dotclear()->con()->in($params['post_id']);
        }

        if (isset($params['exclude_post_id']) && $params['exclude_post_id'] !== '') {
            if (is_array($params['exclude_post_id'])) {
                array_walk($params['exclude_post_id'], function (&$v, $k) { if ($v !== null) {$v = (int) $v;}});
            } else {
                $params['exclude_post_id'] = [(int) $params['exclude_post_id']];
            }
            $strReq .= 'AND P.post_id NOT ' . dotclear()->con()->in($params['exclude_post_id']);
        }

        if (isset($params['post_url']) && $params['post_url'] !== '') {
            $strReq .= "AND post_url = '" . dotclear()->con()->escape($params['post_url']) . "' ";
        }

        if (!empty($params['user_id'])) {
            $strReq .= "AND U.user_id = '" . dotclear()->con()->escape($params['user_id']) . "' ";
        }

        if (isset($params['cat_id']) && $params['cat_id'] !== '') {
            if (!is_array($params['cat_id'])) {
                $params['cat_id'] = [$params['cat_id']];
            }
            if (!empty($params['cat_id_not'])) {
                array_walk($params['cat_id'], function (&$v, $k) {$v = $v . ' ?not';});
            }
            $strReq .= 'AND ' . $this->getPostsCategoryFilter($params['cat_id'], 'cat_id') . ' ';
        } elseif (isset($params['cat_url']) && $params['cat_url'] !== '') {
            if (!is_array($params['cat_url'])) {
                $params['cat_url'] = [$params['cat_url']];
            }
            if (!empty($params['cat_url_not'])) {
                array_walk($params['cat_url'], function (&$v, $k) {$v = $v . ' ?not';});
            }
            $strReq .= 'AND ' . $this->getPostsCategoryFilter($params['cat_url'], 'cat_url') . ' ';
        }

        /* Other filters */
        if (isset($params['post_status'])) {
            $strReq .= 'AND post_status = ' . (int) $params['post_status'] . ' ';
        }

        if (isset($params['post_firstpub'])) {
            $strReq .= 'AND post_firstpub = ' . (int) $params['post_firstpub'] . ' ';
        }

        if (isset($params['post_selected'])) {
            $strReq .= 'AND post_selected = ' . (int) $params['post_selected'] . ' ';
        }

        if (!empty($params['post_year'])) {
            $strReq .= 'AND ' . dotclear()->con()->dateFormat('post_dt', '%Y') . ' = ' .
            "'" . sprintf('%04d', $params['post_year']) . "' ";
        }

        if (!empty($params['post_month'])) {
            $strReq .= 'AND ' . dotclear()->con()->dateFormat('post_dt', '%m') . ' = ' .
            "'" . sprintf('%02d', $params['post_month']) . "' ";
        }

        if (!empty($params['post_day'])) {
            $strReq .= 'AND ' . dotclear()->con()->dateFormat('post_dt', '%d') . ' = ' .
            "'" . sprintf('%02d', $params['post_day']) . "' ";
        }

        if (!empty($params['post_lang'])) {
            $strReq .= "AND P.post_lang = '" . dotclear()->con()->escape($params['post_lang']) . "' ";
        }

        if (!empty($params['search'])) {
            $words = Text::splitWords($params['search']);

            if (!empty($words)) {
                if (dotclear()->behavior()->has('corePostSearch')) {

                    # --BEHAVIOR-- corePostSearch, array
                    dotclear()->behavior()->call('corePostSearch', [&$words, &$strReq, &$params]);
                }

                foreach ($words as $i => $w) {
                    $words[$i] = "post_words LIKE '%" . dotclear()->con()->escape($w) . "%'";
                }
                $strReq .= 'AND ' . implode(' AND ', $words) . ' ';
            }
        }

        if (isset($params['media'])) {
            if ($params['media'] == '0') {
                $strReq .= 'AND NOT ';
            } else {
                $strReq .= 'AND ';
            }
            $strReq .= 'EXISTS (SELECT M.post_id FROM ' . dotclear()->prefix . 'post_media M ' .
                'WHERE M.post_id = P.post_id ';
            if (isset($params['link_type'])) {
                $strReq .= ' AND M.link_type ' . dotclear()->con()->in($params['link_type']) . ' ';
            }
            $strReq .= ')';
        }

        if (!empty($params['where'])) {
            $strReq .= $params['where'] . ' ';
        }

        if (!empty($params['sql'])) {
            $strReq .= $params['sql'] . ' ';
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . dotclear()->con()->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY post_dt DESC ';
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $strReq .= dotclear()->con()->limit($params['limit']);
        }

        $rs            = dotclear()->con()->select($strReq);
        $rs->_nb_media = [];
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtPost');

        # --BEHAVIOR-- coreBlogGetPosts
        dotclear()->behavior()->call('coreBlogGetPosts', $rs);

        $alt = new ArrayObject(['rs' => null, 'params' => $params, 'count_only' => $count_only]);

        # --BEHAVIOR-- coreBlogAfterGetPosts, ArrayObject, array
        dotclear()->behavior()->call('coreBlogAfterGetPosts', $rs, $alt);

        if ($alt['rs'] instanceof Record) { // @phpstan-ignore-line
            $rs = $alt['rs'];
        }

        return $rs;
    }

    /**
     * Returns a record with post id, title and date for next or previous post
     * according to the post ID.
     * $dir could be 1 (next post) or -1 (previous post).
     *
     * @param      Record  $post                  The post ID
     * @param      int     $dir                   The search direction
     * @param      bool    $restrict_to_category  Restrict to same category
     * @param      bool    $restrict_to_lang      Restrict to same language
     *
     * @return     null|Record   The next post.
     */
    public function getNextPost(Record $post, int $dir, bool $restrict_to_category = false, bool $restrict_to_lang = false): ?Record
    {
        $dt      = $post->post_dt;
        $post_id = (int) $post->post_id;

        if ($dir > 0) {
            $sign  = '>';
            $order = 'ASC';
        } else {
            $sign  = '<';
            $order = 'DESC';
        }

        $params['post_type'] = $post->post_type;
        $params['limit']     = 1;
        $params['order']     = 'post_dt ' . $order . ', P.post_id ' . $order;
        $params['sql']       = 'AND ( ' .
        "   (post_dt = '" . dotclear()->con()->escape($dt) . "' AND P.post_id " . $sign . ' ' . $post_id . ') ' .
        '   OR post_dt ' . $sign . " '" . dotclear()->con()->escape($dt) . "' " .
            ') ';

        if ($restrict_to_category) {
            $params['sql'] .= $post->cat_id ? 'AND P.cat_id = ' . (int) $post->cat_id . ' ' : 'AND P.cat_id IS NULL ';
        }

        if ($restrict_to_lang) {
            $params['sql'] .= $post->post_lang ? 'AND P.post_lang = \'' . dotclear()->con()->escape($post->post_lang) . '\' ' : 'AND P.post_lang IS NULL ';
        }

        $rs = $this->getPosts($params);

        if ($rs->isEmpty()) {
            return null;
        }

        return $rs;
    }

    /**
     * Retrieves different languages and post count on blog, based on post_lang
     * field. <var>$params</var> is an array taking the following optionnal
     * parameters:
     *
     * - post_type: Get only entries with given type (default "post", '' for no type)
     * - lang: retrieve post count for selected lang
     * - order: order statement (default post_lang DESC)
     *
     * @param      array   $params  The parameters
     *
     * @return     Record  The langs.
     */
    public function getLangs(array $params = []): Record
    {
        $strReq = 'SELECT COUNT(post_id) as nb_post, post_lang ' .
        'FROM ' . dotclear()->prefix . 'post ' .
        "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            "AND post_lang <> '' " .
            'AND post_lang IS NOT NULL ';

        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= 'AND ((post_status = 1 ';

            if (dotclear()->blog()->withoutPassword()) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (dotclear()->user()->userID()) {
                $strReq .= "OR user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        if (isset($params['post_type'])) {
            if ($params['post_type'] != '') {
                $strReq .= "AND post_type = '" . dotclear()->con()->escape($params['post_type']) . "' ";
            }
        } else {
            $strReq .= "AND post_type = 'post' ";
        }

        if (isset($params['lang'])) {
            $strReq .= "AND post_lang = '" . dotclear()->con()->escape($params['lang']) . "' ";
        }

        $strReq .= 'GROUP BY post_lang ';

        $order = 'desc';
        if (!empty($params['order']) && preg_match('/^(desc|asc)$/i', $params['order'])) {
            $order = $params['order'];
        }
        $strReq .= 'ORDER BY post_lang ' . $order . ' ';

        return dotclear()->con()->select($strReq);
    }

    /**
     * Returns a record with all distinct blog dates and post count.
     * <var>$params</var> is an array taking the following optionnal parameters:
     *
     * - type: (day|month|year) Get days, months or years
     * - year: (integer) Get dates for given year
     * - month: (integer) Get dates for given month
     * - day: (integer) Get dates for given day
     * - cat_id: (integer) Category ID filter
     * - cat_url: Category URL filter
     * - post_lang: lang of the posts
     * - next: Get date following match
     * - previous: Get date before match
     * - order: Sort by date "ASC" or "DESC"
     *
     * @param      array   $params  The parameters
     *
     * @return     record  The dates.
     */
    public function getDates(array $params = []): Record
    {
        $dt_f  = '%Y-%m-%d';
        $dt_fc = '%Y%m%d';
        if (isset($params['type'])) {
            if ($params['type'] == 'year') {
                $dt_f  = '%Y-01-01';
                $dt_fc = '%Y0101';
            } elseif ($params['type'] == 'month') {
                $dt_f  = '%Y-%m-01';
                $dt_fc = '%Y%m01';
            }
        }
        $dt_f  .= ' 00:00:00';
        $dt_fc .= '000000';

        $cat_field = $catReq = $limit = '';

        if (isset($params['cat_id']) && $params['cat_id'] !== '') {
            $catReq    = 'AND P.cat_id = ' . (int) $params['cat_id'] . ' ';
            $cat_field = ', C.cat_url ';
        } elseif (isset($params['cat_url']) && $params['cat_url'] !== '') {
            $catReq    = "AND C.cat_url = '" . dotclear()->con()->escape($params['cat_url']) . "' ";
            $cat_field = ', C.cat_url ';
        }
        if (!empty($params['post_lang'])) {
            $catReq = 'AND P.post_lang = \'' . $params['post_lang'] . '\' ';
        }

        $strReq = 'SELECT DISTINCT(' . dotclear()->con()->dateFormat('post_dt', $dt_f) . ') AS dt ' .
        $cat_field .
        ',COUNT(P.post_id) AS nb_post ' .
        'FROM ' . dotclear()->prefix . 'post P LEFT JOIN ' . dotclear()->prefix . 'category C ' .
        'ON P.cat_id = C.cat_id ' .
        "WHERE P.blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            $catReq;

        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= 'AND ((post_status = 1 ';

            if (dotclear()->blog()->withoutPassword()) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (dotclear()->user()->userID()) {
                $strReq .= "OR P.user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        if (!empty($params['post_type'])) {
            $strReq .= 'AND post_type ' . dotclear()->con()->in($params['post_type']) . ' ';
        } else {
            $strReq .= "AND post_type = 'post' ";
        }

        if (!empty($params['year'])) {
            $strReq .= 'AND ' . dotclear()->con()->dateFormat('post_dt', '%Y') . " = '" . sprintf('%04d', $params['year']) . "' ";
        }

        if (!empty($params['month'])) {
            $strReq .= 'AND ' . dotclear()->con()->dateFormat('post_dt', '%m') . " = '" . sprintf('%02d', $params['month']) . "' ";
        }

        if (!empty($params['day'])) {
            $strReq .= 'AND ' . dotclear()->con()->dateFormat('post_dt', '%d') . " = '" . sprintf('%02d', $params['day']) . "' ";
        }

        # Get next or previous date
        if (!empty($params['next']) || !empty($params['previous'])) {
            if (!empty($params['next'])) {
                $pdir            = ' > ';
                $params['order'] = 'asc';
                $dt              = $params['next'];
            } else {
                $pdir            = ' < ';
                $params['order'] = 'desc';
                $dt              = $params['previous'];
            }

            $dt = date('YmdHis', strtotime($dt));

            $strReq .= 'AND ' . dotclear()->con()->dateFormat('post_dt', $dt_fc) . $pdir . "'" . $dt . "' ";
            $limit = dotclear()->con()->limit(1);
        }

        $strReq .= 'GROUP BY dt ' . $cat_field;

        $order = 'desc';
        if (!empty($params['order']) && preg_match('/^(desc|asc)$/i', $params['order'])) {
            $order = $params['order'];
        }

        $strReq .= 'ORDER BY dt ' . $order . ' ' .
            $limit;

        $rs = dotclear()->con()->select($strReq);
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtDates');

        return $rs;
    }

    /**
     * Creates a new entry. Takes a cursor as input and returns the new entry ID.
     *
     * @param      Cursor     $cur    The post cursor
     *
     * @throws     CoreException
     *
     * @return     int
     */
    public function addPost(Cursor $cur): int
    {
        if (!dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to create an entry'));
        }

        dotclear()->con()->writeLock(dotclear()->prefix . 'post');

        try {
            # Get ID
            $rs = dotclear()->con()->select(
                'SELECT MAX(post_id) ' .
                'FROM ' . dotclear()->prefix . 'post '
            );

            $cur->post_id     = (int) $rs->f(0) + 1;
            $cur->blog_id     = (string) dotclear()->blog()->id;
            $cur->post_creadt = date('Y-m-d H:i:s');
            $cur->post_upddt  = date('Y-m-d H:i:s');
            $cur->post_tz     = dotclear()->user()->getInfo('user_tz');

            # Post excerpt and content
            $this->getPostContent($cur, $cur->post_id);

            $this->getPostCursor($cur);

            $cur->post_url = $this->getPostURL($cur->post_url, $cur->post_dt, $cur->post_title, $cur->post_id);

            if (!dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
                $cur->post_status = -2;
            }

            # --BEHAVIOR-- coreBeforePostCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
            dotclear()->behavior()->call('coreBeforePostCreate', $this, $cur);

            $cur->insert();
            dotclear()->con()->unlock();
        } catch (\Exception $e) {
            dotclear()->con()->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterPostCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreAfterPostCreate', $this, $cur);

        dotclear()->blog()->triggerBlog();

        $this->firstPublicationEntries($cur->post_id);

        return (int) $cur->post_id;
    }

    /**
     * Updates an existing post.
     *
     * @param      int     $id     The post identifier
     * @param      Cursor      $cur    The post cursor
     *
     * @throws     CoreException
     */
    public function updPost(int $id, Cursor $cur): void
    {
        if (!dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to update entries'));
        }

        $id = (int) $id;

        if (empty($id)) {
            throw new CoreException(__('No such entry ID'));
        }

        # Post excerpt and content
        $this->getPostContent($cur, $id);

        $this->getPostCursor($cur);

        if ($cur->post_url !== null) {
            $cur->post_url = $this->getPostURL($cur->post_url, $cur->post_dt, $cur->post_title, $id);
        }

        if (!dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            $cur->unsetField('post_status');
        }

        $cur->post_upddt = date('Y-m-d H:i:s');

        #If user is only "usage", we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq = 'SELECT post_id ' .
            'FROM ' . dotclear()->prefix . 'post ' .
            'WHERE post_id = ' . $id . ' ' .
            "AND user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";

            $rs = dotclear()->con()->select($strReq);

            if ($rs->isEmpty()) {
                throw new CoreException(__('You are not allowed to edit this entry'));
            }
        }

        # --BEHAVIOR-- coreBeforePostUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreBeforePostUpdate', $this, $cur);

        $cur->update('WHERE post_id = ' . $id . ' ');

        # --BEHAVIOR-- coreBeforePostUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreBeforePostUpdate', $this, $cur);

        dotclear()->blog()->triggerBlog();

        $this->firstPublicationEntries($id);
    }

    /**
     * Update post status.
     *
     * @param      int  $id      The identifier
     * @param      int  $status  The status
     */
    public function updPostStatus(int $id, int $status): void
    {
        $this->updPostsStatus($id, $status);
    }

    /**
     * Updates posts status.
     *
     * @param      int|array|ArrayObject    $ids     The identifiers
     * @param      int                      $status  The status
     *
     * @throws     CoreException
     */
    public function updPostsStatus($ids, int $status): void
    {
        if (!dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry status'));
        }

        $posts_ids = Utils::cleanIds($ids);
        $status    = (int) $status;

        $strReq = "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
        'AND post_id ' . dotclear()->con()->in($posts_ids);

        #If user can only publish, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= "AND user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

        $cur->post_status = $status;
        $cur->post_upddt  = date('Y-m-d H:i:s');

        $cur->update($strReq);
        dotclear()->blog()->triggerBlog();

        $this->firstPublicationEntries($posts_ids);
    }

    /**
     * Updates post selection.
     *
     * @param      int              $id        The identifier
     * @param      bool|int|null    $selected  The selected flag
     */
    public function updPostSelected(int $id, $selected): void
    {
        $this->updPostsSelected($id, $selected);
    }

    /**
     * Updates posts selection.
     *
     * @param      int|array|ArrayObject          $ids       The identifiers
     * @param      bool|int|null                  $selected  The selected flag
     *
     * @throws     CoreException
     */
    public function updPostsSelected($ids, $selected): void
    {
        if (!dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry category'));
        }

        $posts_ids = Utils::cleanIds($ids);
        $selected  = (bool) $selected;

        $strReq = "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
        'AND post_id ' . dotclear()->con()->in($posts_ids);

        # If user is only usage, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= "AND user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

        $cur->post_selected = (int) $selected;
        $cur->post_upddt    = date('Y-m-d H:i:s');

        $cur->update($strReq);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Updates post category. <var>$cat_id</var> can be null.
     *
     * @param      int  $id         The identifier
     * @param      bool|int|null    $cat_id  The cat identifier
     */
    public function updPostCategory(int $id, $cat_id): void
    {
        $this->updPostsCategory($id, $cat_id);
    }

    /**
     * Updates posts category. <var>$cat_id</var> can be null.
     *
     * @param      int|array|ArrayObject    $ids     The identifiers
     * @param      boo|int|null             $cat_id  The cat identifier
     *
     * @throws     CoreException
     */
    public function updPostsCategory($ids, $cat_id): void
    {
        if (!dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry category'));
        }

        $posts_ids = Utils::cleanIds($ids);
        $cat_id    = (int) $cat_id;

        $strReq = "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
        'AND post_id ' . dotclear()->con()->in($posts_ids);

        # If user is only usage, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= "AND user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

        $cur->cat_id     = ($cat_id ?: null);
        $cur->post_upddt = date('Y-m-d H:i:s');

        $cur->update($strReq);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Updates posts category. <var>$new_cat_id</var> can be null.
     *
     * @param      int|null    $old_cat_id  The old cat identifier
     * @param      int|null    $new_cat_id  The new cat identifier
     *
     * @throws     CoreException
     */
    public function changePostsCategory(?int $old_cat_id, ?int $new_cat_id): void
    {
        if (!dotclear()->user()->check('contentadmin,categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change entries category'));
        }

        $old_cat_id = (int) $old_cat_id;
        $new_cat_id = (int) $new_cat_id;

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

        $cur->cat_id     = ($new_cat_id ?: null);
        $cur->post_upddt = date('Y-m-d H:i:s');

        $cur->update(
            'WHERE cat_id = ' . $old_cat_id . ' ' .
            "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
        );
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Deletes a post.
     *
     * @param      int  $id     The post identifier
     */
    public function delPost(int $id): void
    {
        $this->delPosts($id);
    }

    /**
     * Deletes multiple posts.
     *
     * @param      int|array|ArrayObject    $ids    The posts identifiers
     *
     * @throws     CoreException
     */
    public function delPosts($ids): void
    {
        if (!dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete entries'));
        }

        $posts_ids = Utils::cleanIds($ids);

        if (empty($posts_ids)) {
            throw new CoreException(__('No such entry ID'));
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'post ' .
        "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
        'AND post_id ' . dotclear()->con()->in($posts_ids);

        #If user can only delete, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= "AND user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";
        }

        dotclear()->con()->execute($strReq);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Publishes all entries flaged as "scheduled".
     */
    public function publishScheduledEntries(): void
    {
        $strReq = 'SELECT post_id, post_dt, post_tz ' .
        'FROM ' . dotclear()->prefix . 'post ' .
        'WHERE post_status = -1 ' .
        "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";

        $rs = dotclear()->con()->select($strReq);

        $now       = Dt::toUTC(time());
        $to_change = new ArrayObject();

        if ($rs->isEmpty()) {
            return;
        }

        while ($rs->fetch()) {
            # Now timestamp with post timezone
            $now_tz = $now + Dt::getTimeOffset($rs->post_tz, $now);

            # Post timestamp
            $post_ts = strtotime($rs->post_dt);

            # If now_tz >= post_ts, we publish the entry
            if ($now_tz >= $post_ts) {
                $to_change[] = (int) $rs->post_id;
            }
        }
        if (count($to_change)) {

            # --BEHAVIOR-- coreBeforeScheduledEntriesPublish, Dotclear\Core\Blog, array
            dotclear()->behavior()->call('coreBeforeScheduledEntriesPublish', $this, $to_change);

            $strReq = 'UPDATE ' . dotclear()->prefix . 'post SET ' .
            'post_status = 1 ' .
            "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            'AND post_id ' . dotclear()->con()->in((array) $to_change) . ' ';
            dotclear()->con()->execute($strReq);
            dotclear()->blog()->triggerBlog();

            # --BEHAVIOR-- coreAfterScheduledEntriesPublish, Dotclear\Core\Blog, array
            dotclear()->behavior()->call('coreAfterScheduledEntriesPublish', $this, $to_change);

            $this->firstPublicationEntries($to_change);
        }
    }

    /**
     * First publication mecanism (on post create, update, publish, status)
     *
     * @param      int|array|ArrayObject      $ids    The posts identifiers
     */
    public function firstPublicationEntries($ids): void
    {
        $posts = $this->getPosts([
            'post_id'       => Utils::cleanIds($ids),
            'post_status'   => 1,
            'post_firstpub' => 0,
        ]);

        $to_change = [];
        while ($posts->fetch()) {
            $to_change[] = $posts->post_id;
        }

        if (count($to_change)) {
            $strReq = 'UPDATE ' . dotclear()->prefix . 'post ' .
            'SET post_firstpub = 1 ' .
            "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            'AND post_id ' . dotclear()->con()->in((array) $to_change) . ' ';
            dotclear()->con()->execute($strReq);

            # --BEHAVIOR-- coreFirstPublicationEntries, Dotclear\Core\Blog, array
            dotclear()->behavior()->call('coreFirstPublicationEntries', $this, $to_change);
        }
    }

    /**
     * Retrieves all users having posts on current blog.
     *
     * @param    string     $post_type post_type filter (post)
     *
     * @return    Record
     */
    public function getPostsUsers(string $post_type = 'post'): Record
    {
        $strReq = 'SELECT P.user_id, user_name, user_firstname, ' .
        'user_displayname, user_email ' .
        'FROM ' . dotclear()->prefix . 'post P, ' . dotclear()->prefix . 'user U ' .
        'WHERE P.user_id = U.user_id ' .
        "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";

        if ($post_type) {
            $strReq .= "AND post_type = '" . dotclear()->con()->escape($post_type) . "' ";
        }

        $strReq .= 'GROUP BY P.user_id, user_name, user_firstname, user_displayname, user_email ';

        return dotclear()->con()->select($strReq);
    }

    private function getPostsCategoryFilter(array $arr, string $field = 'cat_id'): string
    {
        $field = $field == 'cat_id' ? 'cat_id' : 'cat_url';

        $sub     = [];
        $not     = [];
        $queries = [];

        foreach ($arr as $v) {
            $v    = trim($v);
            $args = preg_split('/\s*[?]\s*/', $v, -1, PREG_SPLIT_NO_EMPTY);
            $id   = array_shift($args);
            $args = array_flip($args);

            if (isset($args['not'])) {
                $not[$id] = 1;
            }
            if (isset($args['sub'])) {
                $sub[$id] = 1;
            }
            if ($field == 'cat_id') {
                if (preg_match('/^null$/i', $id)) {
                    $queries[$id] = 'P.cat_id IS NULL';
                } else {
                    $queries[$id] = 'P.cat_id = ' . (int) $id;
                }
            } else {
                $queries[$id] = "C.cat_url = '" . dotclear()->con()->escape($id) . "' ";
            }
        }

        if (!empty($sub)) {
            $rs = dotclear()->con()->select(
                'SELECT cat_id, cat_url, cat_lft, cat_rgt FROM ' . dotclear()->prefix . 'category ' .
                "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
                'AND ' . $field . ' ' . dotclear()->con()->in(array_keys($sub))
            );

            while ($rs->fetch()) {
                $queries[$rs->f($field)] = '(C.cat_lft BETWEEN ' . $rs->cat_lft . ' AND ' . $rs->cat_rgt . ')';
            }
        }

        # Create queries
        $sql = [
            0 => [], # wanted categories
            1 => [], # excluded categories
        ];

        foreach ($queries as $id => $q) {
            $sql[(int) isset($not[$id])][] = $q;
        }

        $sql[0] = implode(' OR ', $sql[0]);
        $sql[1] = implode(' OR ', $sql[1]);

        if ($sql[0]) {
            $sql[0] = '(' . $sql[0] . ')';
        } else {
            unset($sql[0]);
        }

        if ($sql[1]) {
            $sql[1] = '(P.cat_id IS NULL OR NOT(' . $sql[1] . '))';
        } else {
            unset($sql[1]);
        }

        return implode(' AND ', $sql);  // @phpstan-ignore-line
    }

    /**
     * Gets the post cursor.
     *
     * @param      Cursor      $cur      The post cursor
     * @param      int     $post_id  The post identifier
     *
     * @throws     CoreException
     */
    private function getPostCursor(Cursor $cur, int $post_id = null): void
    {
        if ($cur->post_title == '') {
            throw new CoreException(__('No entry title'));
        }

        if ($cur->post_content == '') {
            throw new CoreException(__('No entry content'));
        }

        if ($cur->post_password === '') {
            $cur->post_password = null;
        }

        if ($cur->post_dt == '') {
            $offset       = Dt::getTimeOffset(dotclear()->user()->getInfo('user_tz'));
            $now          = time() + $offset;
            $cur->post_dt = date('Y-m-d H:i:00', $now);
        }

        $post_id = is_int($post_id) ? $post_id : $cur->post_id;

        if ($cur->post_content_xhtml == '') {
            throw new CoreException(__('No entry content'));
        }

        # Words list
        if ($cur->post_title !== null && $cur->post_excerpt_xhtml !== null
            && $cur->post_content_xhtml !== null) {
            $words = $cur->post_title . ' ' .
            $cur->post_excerpt_xhtml . ' ' .
            $cur->post_content_xhtml;

            $cur->post_words = implode(' ', Text::splitWords($words));
        }

        if ($cur->isField('post_firstpub')) {
            $cur->unsetField('post_firstpub');
        }
    }

    /**
     * Gets the post content.
     *
     * @param      Cursor   $cur      The post cursor
     * @param      int      $post_id  The post identifier
     */
    private function getPostContent(Cursor $cur, int $post_id): void
    {
        $post_excerpt       = $cur->post_excerpt;
        $post_excerpt_xhtml = $cur->post_excerpt_xhtml;
        $post_content       = $cur->post_content;
        $post_content_xhtml = $cur->post_content_xhtml;

        $this->setPostContent(
            $post_id,
            $cur->post_format,
            $cur->post_lang,
            $post_excerpt,
            $post_excerpt_xhtml,
            $post_content,
            $post_content_xhtml
        );

        $cur->post_excerpt       = $post_excerpt;
        $cur->post_excerpt_xhtml = $post_excerpt_xhtml;
        $cur->post_content       = $post_content;
        $cur->post_content_xhtml = $post_content_xhtml;
    }

    /**
     * Creates post HTML content, taking format and lang into account.
     *
     * @param      int|null     $post_id        The post identifier
     * @param      string       $format         The format
     * @param      string       $lang           The language
     * @param      string|null  $excerpt        The excerpt
     * @param      string|null  $excerpt_xhtml  The excerpt xhtml
     * @param      string       $content        The content
     * @param      string       $content_xhtml  The content xhtml
     */
    public function setPostContent(?int $post_id, string $format, string $lang, ?string &$excerpt, ?string &$excerpt_xhtml, string &$content, string &$content_xhtml): void
    {
        if ($format == 'wiki') {
            dotclear()->wiki()->initWikiPost();
            dotclear()->wiki()->setOpt('note_prefix', 'pnote-' . ($post_id ?? ''));
            switch (dotclear()->blog()->settings()->system->note_title_tag) {
                case 1:
                    $tag = 'h3';

                    break;
                case 2:
                    $tag = 'p';

                    break;
                default:
                    $tag = 'h4';

                    break;
            }
            dotclear()->wiki()->setOpt('note_str', '<div class="footnotes"><' . $tag . ' class="footnotes-title">' .
                __('Notes') . '</' . $tag . '>%s</div>');
            dotclear()->wiki()->setOpt('note_str_single', '<div class="footnotes"><' . $tag . ' class="footnotes-title">' .
                __('Note') . '</' . $tag . '>%s</div>');
            if (strpos($lang, 'fr') === 0) {
                dotclear()->wiki()->setOpt('active_fr_syntax', 1);
            }
        }

        if ($excerpt) {
            $excerpt_xhtml = dotclear()->formater()->callEditorFormater('LegacyEditor', $format, $excerpt);
            $excerpt_xhtml = Html::filter($excerpt_xhtml);
        } else {
            $excerpt_xhtml = '';
        }

        if ($content) {
            $content_xhtml = dotclear()->formater()->callEditorFormater('LegacyEditor', $format, $content);
            $content_xhtml = Html::filter($content_xhtml);
        } else {
            $content_xhtml = '';
        }

        # --BEHAVIOR-- coreAfterPostContentFormat, array
        dotclear()->behavior()->call('coreAfterPostContentFormat', [
            'excerpt'       => &$excerpt,
            'content'       => &$content,
            'excerpt_xhtml' => &$excerpt_xhtml,
            'content_xhtml' => &$content_xhtml,
        ]);
    }

    /**
     * Returns URL for a post according to blog setting <var>post_url_format</var>.
     * It will try to guess URL and append some figures if needed.
     *
     * @param      string|null  $url         The url
     * @param      string|null  $post_dt     The post dt
     * @param      string|null  $post_title  The post title
     * @param      int|null     $post_id     The post identifier
     *
     * @return     string  The post url.
     */
    public function getPostURL(?string $url, ?string $post_dt, ?string $post_title, ?int $post_id): string
    {
        $url = trim((string) $url);

        $url_patterns = [
            '{y}'  => date('Y', strtotime((string) $post_dt)),
            '{m}'  => date('m', strtotime((string) $post_dt)),
            '{d}'  => date('d', strtotime((string) $post_dt)),
            '{t}'  => Text::tidyURL((string) $post_title),
            '{id}' => (int) $post_id,
        ];

        # If URL is empty, we create a new one
        if ($url == '') {
            # Transform with format
            $url = str_replace(
                array_keys($url_patterns),
                array_values($url_patterns),
                dotclear()->blog()->settings()->system->post_url_format
            );
        } else {
            $url = Text::tidyURL($url);
        }

        # Let's check if URL is taken...
        $strReq = 'SELECT post_url FROM ' . dotclear()->prefix . 'post ' .
        "WHERE post_url = '" . dotclear()->con()->escape($url) . "' " .
        'AND post_id <> ' . (int) $post_id . ' ' .
        "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            'ORDER BY post_url DESC';

        $rs = dotclear()->con()->select($strReq);

        if (!$rs->isEmpty()) {
            if (dotclear()->con()->syntax() == 'mysql') {
                $clause = "REGEXP '^" . dotclear()->con()->escape(preg_quote($url)) . "[0-9]+$'";
            } elseif (dotclear()->con()->driver() == 'pgsql') {
                $clause = "~ '^" . dotclear()->con()->escape(preg_quote($url)) . "[0-9]+$'";
            } else {
                $clause = "LIKE '" .
                dotclear()->con()->escape(preg_replace(['%', '_', '!'], ['!%', '!_', '!!'], $url)) . "%' ESCAPE '!'";  // @phpstan-ignore-line
            }
            $strReq = 'SELECT post_url FROM ' . dotclear()->prefix . 'post ' .
            'WHERE post_url ' . $clause . ' ' .
            'AND post_id <> ' . (int) $post_id . ' ' .
            "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
                'ORDER BY post_url DESC ';

            $rs = dotclear()->con()->select($strReq);
            $a  = [];
            while ($rs->fetch()) {
                $a[] = $rs->post_url;
            }

            natsort($a);
            $t_url = end($a);

            if (preg_match('/(.*?)([0-9]+)$/', $t_url, $m)) {
                $i   = (int) $m[2];
                $url = $m[1];
            } else {
                $i = 1;
            }

            return $url . ($i + 1);
        }

        # URL is empty?
        if ($url == '') {
            throw new CoreException(__('Empty entry URL'));
        }

        return $url;
    }
}
