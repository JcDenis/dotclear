<?php
/**
 * @class Dotclear\Core\Blog\Comments\Comments
 * @brief Dotclear core blog Comments class
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Comments;

use ArrayObject;

use Dotclear\Core\RsExt\RsExtComment;
use Dotclear\Database\Record;
use Dotclear\Database\Cursor;
use Dotclear\Exception\CoreException;
use Dotclear\Exception\DeprecatedException;
use Dotclear\Network\Http;
use Dotclear\Utils\Dt;
use Dotclear\Utils\Text;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Comments
{
    /**
     * Retrieves comments. <b>$params</b> is an array taking the following
     * optionnal parameters:
     *
     * - no_content: Don't retrieve comment content
     * - post_type: Get only entries with given type (default no type, array for many types)
     * - post_id: (integer) Get comments belonging to given post_id
     * - cat_id: (integer or array) Get comments belonging to entries of given category ID
     * - comment_id: (integer or array) Get comment with given ID (or IDs)
     * - comment_site: (string) Get comments with given comment_site
     * - comment_status: (integer) Get comments with given comment_status
     * - comment_trackback: (integer) Get only comments (0) or trackbacks (1)
     * - comment_ip: (string) Get comments with given IP address
     * - post_url: Get entry with given post_url field
     * - user_id: (integer) Get entries belonging to given user ID
     * - q_author: Search comments by author
     * - sql: Append SQL string at the end of the query
     * - from: Append SQL string after "FROM" statement in query
     * - order: Order of results (default "ORDER BY comment_dt DES")
     * - limit: Limit parameter
     * - sql_only : return the sql request instead of results. Only ids are selected
     *
     * @since 3.0 remove sql_only param: reimplement something later
     *
     * @param    array      $params        Parameters
     * @param    bool       $count_only    Only counts results
     *
     * @return   Record      A record with some more capabilities
     */
    public function getComments(array $params = [], bool $count_only = false): Record
    {
        if ($count_only) {
            $strReq = 'SELECT count(comment_id) ';
        } elseif (!empty($params['sql_only'])) {
            DeprecatedException::throw();
            $strReq = 'SELECT P.post_id ';
        } else {
            if (!empty($params['no_content'])) {
                $content_req = '';
            } else {
                $content_req = 'comment_content, ';
            }

            if (!empty($params['columns']) && is_array($params['columns'])) {
                $content_req .= implode(', ', $params['columns']) . ', ';
            }

            $strReq = 'SELECT C.comment_id, comment_dt, comment_tz, comment_upddt, ' .
                'comment_author, comment_email, comment_site, ' .
                $content_req . ' comment_trackback, comment_status, ' .
                'comment_ip, ' .
                'P.post_title, P.post_url, P.post_id, P.post_password, P.post_type, ' .
                'P.post_dt, P.user_id, U.user_email, U.user_url ';
        }

        $strReq .= 'FROM ' . dotclear()->prefix . 'comment C ' .
        'INNER JOIN ' . dotclear()->prefix . 'post P ON C.post_id = P.post_id ' .
        'INNER JOIN ' . dotclear()->prefix . 'user U ON P.user_id = U.user_id ';

        if (!empty($params['from'])) {
            $strReq .= $params['from'] . ' ';
        }

        $strReq .= "WHERE P.blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";

        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= 'AND ((comment_status = 1 AND P.post_status = 1 ';

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
            $strReq .= 'AND post_type ' . dotclear()->con()->in($params['post_type']);
        }

        if (isset($params['post_id']) && $params['post_id'] !== '') {
            $strReq .= 'AND P.post_id = ' . (int) $params['post_id'] . ' ';
        }

        if (isset($params['cat_id']) && $params['cat_id'] !== '') {
            $strReq .= 'AND P.cat_id = ' . (int) $params['cat_id'] . ' ';
        }

        if (isset($params['comment_id']) && $params['comment_id'] !== '') {
            if (is_array($params['comment_id'])) {
                array_walk($params['comment_id'], function (&$v, $k) { if ($v !== null) {$v = (int) $v;}});
            } else {
                $params['comment_id'] = [(int) $params['comment_id']];
            }
            $strReq .= 'AND comment_id ' . dotclear()->con()->in($params['comment_id']);
        }

        if (isset($params['comment_email'])) {
            $comment_email = dotclear()->con()->escape(str_replace('*', '%', $params['comment_email']));
            $strReq .= "AND comment_email LIKE '" . $comment_email . "' ";
        }

        if (isset($params['comment_site'])) {
            $comment_site = dotclear()->con()->escape(str_replace('*', '%', $params['comment_site']));
            $strReq .= "AND comment_site LIKE '" . $comment_site . "' ";
        }

        if (isset($params['comment_status'])) {
            $strReq .= 'AND comment_status = ' . (int) $params['comment_status'] . ' ';
        }

        if (!empty($params['comment_status_not'])) {
            $strReq .= 'AND comment_status <> ' . (int) $params['comment_status_not'] . ' ';
        }

        if (isset($params['comment_trackback'])) {
            $strReq .= 'AND comment_trackback = ' . (int) (bool) $params['comment_trackback'] . ' ';
        }

        if (isset($params['comment_ip'])) {
            $comment_ip = dotclear()->con()->escape(str_replace('*', '%', $params['comment_ip']));
            $strReq .= "AND comment_ip LIKE '" . $comment_ip . "' ";
        }

        if (isset($params['q_author'])) {
            $q_author = dotclear()->con()->escape(str_replace('*', '%', strtolower($params['q_author'])));
            $strReq .= "AND LOWER(comment_author) LIKE '" . $q_author . "' ";
        }

        if (!empty($params['search'])) {
            $words = Text::splitWords($params['search']);

            if (!empty($words)) {
                if (dotclear()->behavior()->has('coreCommentSearch')) {

                    # --BEHAVIOR coreCommentSearch, array
                    dotclear()->behavior()->call('coreCommentSearchs', [&$words, &$strReq, &$params]);
                }

                foreach ($words as $i => $w) {
                    $words[$i] = "comment_words LIKE '%" . dotclear()->con()->escape($w) . "%'";
                }
                $strReq .= 'AND ' . implode(' AND ', $words) . ' ';
            }
        }

        if (!empty($params['sql'])) {
            $strReq .= $params['sql'] . ' ';
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . dotclear()->con()->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY comment_dt DESC ';
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $strReq .= dotclear()->con()->limit($params['limit']);
        }

        $rs = dotclear()->con()->select($strReq);
        $rs->extend(new RsExtComment());

        # --BEHAVIOR-- coreBlogGetComments, Dotclear\Database\Record
        dotclear()->behavior()->call('coreBlogGetComments', $rs);

        return $rs;
    }

    /**
     * Creates a new comment. Takes a cursor as input and returns the new comment ID.
     *
     * @param      Cursor  $cur    The comment cursor
     *
     * @return     int
     */
    public function addComment(Cursor $cur): int
    {
        dotclear()->con()->writeLock(dotclear()->prefix . 'comment');

        try {
            # Get ID
            $rs = dotclear()->con()->select(
                'SELECT MAX(comment_id) ' .
                'FROM ' . dotclear()->prefix . 'comment '
            );

            $cur->comment_id    = (int) $rs->f(0) + 1;
            $cur->comment_upddt = date('Y-m-d H:i:s');

            $offset          = Dt::getTimeOffset(dotclear()->blog()->settings()->system->blog_timezone);
            $cur->comment_dt = date('Y-m-d H:i:s', time() + $offset);
            $cur->comment_tz = dotclear()->blog()->settings()->system->blog_timezone;

            $this->getCommentCursor($cur);

            if ($cur->comment_ip === null) {
                $cur->comment_ip = Http::realIP();
            }

            # --BEHAVIOR-- coreBeforeCommentCreate, Dotclear\Core\Blog, Dotclear\Database\Record
            dotclear()->behavior()->call('coreBeforeCommentCreate', $this, $cur);

            $cur->insert();
            dotclear()->con()->unlock();
        } catch (\Exception $e) {
            dotclear()->con()->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterCommentCreate, Dotclear\Core\Blog, Dotclear\Database\Record
        dotclear()->behavior()->call('coreAfterCommentCreate', $this, $cur);

        dotclear()->blog()->triggerComment($cur->comment_id);
        if ($cur->comment_status != -2) {
            dotclear()->blog()->triggerBlog();
        }

        return (int) $cur->comment_id;
    }

    /**
     * Updates an existing comment.
     *
     * @param      int      $id     The comment identifier
     * @param      Cursor   $cur    The comment cursor
     *
     * @throws     CoreException
     */
    public function updComment(int $id, Cursor $cur): void
    {
        if (!dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to update comments'));
        }

        $id = (int) $id;

        if (empty($id)) {
            throw new CoreException(__('No such comment ID'));
        }

        $rs = $this->getComments(['comment_id' => $id]);

        if ($rs->isEmpty()) {
            throw new CoreException(__('No such comment ID'));
        }

        #If user is only usage, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            if ($rs->user_id != dotclear()->user()->userID()) {
                throw new CoreException(__('You are not allowed to update this comment'));
            }
        }

        $this->getCommentCursor($cur);

        $cur->comment_upddt = date('Y-m-d H:i:s');

        if (!dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            $cur->unsetField('comment_status');
        }

        # --BEHAVIOR-- coreBeforeCommentUpdate, Dotclear\Core\Blog, Dotclear\Database\Record
        dotclear()->behavior()->call('coreBeforeCommentUpdate', $this, $cur, $rs);

        $cur->update('WHERE comment_id = ' . $id . ' ');

        # --BEHAVIOR-- coreAfterCommentUpdate, Dotclear\Core\Blog, Dotclear\Database\Record
        dotclear()->behavior()->call('coreAfterCommentUpdate', $this, $cur, $rs);

        dotclear()->blog()->triggerComment($id);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Updates comment status.
     *
     * @param      int      $id      The comment identifier
     * @param      int      $status  The comment status
     */
    public function updCommentStatus(int $id, int $status): void
    {
        $this->updCommentsStatus($id, $status);
    }

    /**
     * Updates comments status.
     *
     * @param      int|array|ArrayObject    $ids     The identifiers
     * @param      int                      $status  The status
     *
     * @throws     CoreException
     */
    public function updCommentsStatus($ids, int $status): void
    {
        if (!dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__("You are not allowed to change this comment's status"));
        }

        $co_ids = dotclear()->blog()->cleanIds($ids);
        $status = (int) $status;

        $strReq = 'UPDATE ' . dotclear()->prefix . 'comment ' .
            'SET comment_status = ' . $status . ' ';
        $strReq .= 'WHERE comment_id' . dotclear()->con()->in($co_ids) .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . dotclear()->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= "AND user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";
        }
        $strReq .= ')';
        dotclear()->con()->execute($strReq);
        dotclear()->blog()->triggerComments($co_ids);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Delete a comment.
     *
     * @param      int  $id     The comment identifier
     */
    public function delComment(int $id): void
    {
        $this->delComments($id);
    }

    /**
     * Delete comments.
     *
     * @param      int|array|ArrayObject    $ids    The comments identifiers
     *
     * @throws     CoreException
     */
    public function delComments($ids): void
    {
        if (!dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $co_ids = dotclear()->blog()->cleanIds($ids);

        if (empty($co_ids)) {
            throw new CoreException(__('No such comment ID'));
        }

        # Retrieve posts affected by comments edition
        $affected_posts = [];
        $strReq         = 'SELECT post_id ' .
        'FROM ' . dotclear()->prefix . 'comment ' .
        'WHERE comment_id' . dotclear()->con()->in($co_ids) .
            'GROUP BY post_id';

        $rs = dotclear()->con()->select($strReq);

        while ($rs->fetch()) {
            $affected_posts[] = (int) $rs->post_id;
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'comment ' .
        'WHERE comment_id' . dotclear()->con()->in($co_ids) . ' ' .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . dotclear()->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";
        #If user can only delete, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= "AND tp.user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";
        }
        $strReq .= ')';
        dotclear()->con()->execute($strReq);
        dotclear()->blog()->triggerComments($co_ids, true, $affected_posts);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Delete Junk comments
     *
     * @throws     CoreException  (description)
     */
    public function delJunkComments():void
    {
        if (!dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'comment ' .
        'WHERE comment_status = -2 ' .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . dotclear()->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";
        #If user can only delete, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $strReq .= "AND tp.user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";
        }
        $strReq .= ')';
        dotclear()->con()->execute($strReq);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Gets the comment cursor.
     *
     * @param      Cursor     $cur    The comment cursor
     *
     * @throws     CoreException
     */
    private function getCommentCursor(Cursor $cur): void
    {
        if ($cur->comment_content !== null && $cur->comment_content == '') {
            throw new CoreException(__('You must provide a comment'));
        }

        if ($cur->comment_author !== null && $cur->comment_author == '') {
            throw new CoreException(__('You must provide an author name'));
        }

        if ($cur->comment_email != '' && !Text::isEmail($cur->comment_email)) {
            throw new CoreException(__('Email address is not valid.'));
        }

        if ($cur->comment_site !== null && $cur->comment_site != '') {
            if (!preg_match('|^http(s?)://|i', $cur->comment_site, $matches)) {
                $cur->comment_site = 'http://' . $cur->comment_site;
            } else {
                $cur->comment_site = strtolower($matches[0]) . substr($cur->comment_site, strlen($matches[0]));
            }
        }

        if ($cur->comment_status === null) {
            $cur->comment_status = (int) dotclear()->blog()->settings()->system->comments_pub;
        }

        # Words list
        if ($cur->comment_content !== null) {
            $cur->comment_words = implode(' ', Text::splitWords($cur->comment_content));
        }
    }
}
