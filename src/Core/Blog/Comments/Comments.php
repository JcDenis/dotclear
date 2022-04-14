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
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;
use Dotclear\Exception\DeprecatedException;
use Dotclear\Helper\Dt;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;

class Comments
{
    /**
     * Retrieves comments. <b>$params</b> is an array taking the following
     * optionnal parameters:
     *
     * - no_content: Don't retrieve comment content
     * - post_type: Get only entries with given type (default no type, array for many types)
     * - post_id: (int) Get comments belonging to given post_id
     * - cat_id: (integer or array) Get comments belonging to entries of given category ID
     * - comment_id: (integer or array) Get comment with given ID (or IDs)
     * - comment_site: (string) Get comments with given comment_site
     * - comment_status: (int) Get comments with given comment_status
     * - comment_trackback: (int) Get only comments (0) or trackbacks (1)
     * - comment_ip: (string) Get comments with given IP address
     * - post_url: Get entry with given post_url field
     * - user_id: (int) Get entries belonging to given user ID
     * - q_author: Search comments by author
     * - sql: Append SQL string at the end of the query
     * - from: Append SQL string after "FROM" statement in query
     * - order: Order of results (default "ORDER BY comment_dt DES")
     * - limit: Limit parameter
     * - sql_only : return the sql request instead of results. Only ids are selected
     *
     * @param   array                   $params         Parameters
     * @param   bool                    $count_only     Only counts results
     * @param   SelectStatement|null    $sql            previous sql statement
     *
     * @return   string|Record                          A record with some more capabilities (or sql statement)
     */
    public function getComments(array $params = [], bool $count_only = false, ?SelectStatement $sql = null): string|Record
    {
        if (!$sql) {
            $sql = new SelectStatement(__METHOD__);
        }

        if ($count_only) {
            $sql->column($sql->count('comment_id'));
        } elseif (!empty($params['sql_only'])) {
            $sql->column('P.post_id');
        } else {
            if (!empty($params['no_content'])) {
                $content_req = '';
            } else {
                $sql->column('comment_content');

                $content_req = 'comment_content, ';
            }

            if (!empty($params['columns']) && is_array($params['columns'])) {
                $sql->columns($params['columns']);

                $content_req .= implode(', ', $params['columns']) . ', ';
            }

            $sql->columns([
                'C.comment_id',
                'comment_dt',
                'comment_tz',
                'comment_upddt',
                'comment_author',
                'comment_email',
                'comment_site',
                'comment_trackback',
                'comment_status',
                'comment_spam_status',
                'comment_spam_filter',
                'comment_ip',
                'P.post_title',
                'P.post_url',
                'P.post_id',
                'P.post_password',
                'P.post_type',
                'P.post_dt',
                'P.user_id',
                'U.user_email',
                'U.user_url',
            ]);
        }

        $sql
            ->from(dotclear()->prefix . 'comment C')
            ->join(
                JoinStatement::init(__METHOD__)
                    ->type('INNER')
                    ->from(dotclear()->prefix . 'post P')
                    ->on('C.post_id = P.post_id')
                    ->statement()
            )
            ->join(
                JoinStatement::init(__METHOD__)
                    ->type('INNER')
                    ->from(dotclear()->prefix . 'user U')
                    ->on('P.user_id = U.user_id')
                    ->statement()
            );

        if (!empty($params['from'])) {
            $sql->from($params['from']);
        }

        if (!empty($params['where'])) {
            // Cope with legacy code
            $sql->where($params['where']);
        } else {
            $sql->where('P.blog_id = ' . $sql->quote(dotclear()->blog()->id, true));
        }

        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $user_id = dotclear()->user()->userID();

            $and = [
                'comment_status = 1',
                'P.post_status = 1',
            ];

            if (dotclear()->blog()->withoutPassword()) {
                $and[] = 'post_password IS NULL';
            }

            $or = [$sql->andGroup($and)];
            if ($user_id) {
                $or[] = 'P.user_id = ' . $sql->quote($user_id, true);
            }
            $sql->and($sql->orGroup($or));
        }

        if (!empty($params['post_type'])) {
            $sql->and('post_type' . $sql->in($params['post_type']));
        }

        if (isset($params['post_id']) && '' !== $params['post_id']) {
            $sql->and('P.post_id = ' . (int) $params['post_id']);
        }

        if (isset($params['cat_id']) && '' !== $params['cat_id']) {
            $sql->and('P.cat_id = ' . (int) $params['cat_id']);
        }

        if (isset($params['comment_id']) && '' !== $params['comment_id']) {
            if (is_array($params['comment_id'])) {
                array_walk($params['comment_id'], function (&$v, $k) { if (null !== $v) {$v = (int) $v;}});
            } else {
                $params['comment_id'] = [(int) $params['comment_id']];
            }
            $sql->and('comment_id' . $sql->in($params['comment_id']));
        }

        if (isset($params['comment_email'])) {
            $comment_email = $sql->escape(str_replace('*', '%', $params['comment_email']));
            $sql->and($sql->like('comment_email', $comment_email));
        }

        if (isset($params['comment_site'])) {
            $comment_site = $sql->escape(str_replace('*', '%', $params['comment_site']));
            $sql->and($sql->like('comment_site', $comment_site));
        }

        if (isset($params['comment_status'])) {
            $sql->and('comment_status = ' . (int) $params['comment_status']);
        }

        if (!empty($params['comment_status_not'])) {
            $sql->and('comment_status <> ' . (int) $params['comment_status_not']);
        }

        if (isset($params['comment_trackback'])) {
            $sql->and('comment_trackback = ' . (int) (bool) $params['comment_trackback']);
        }

        if (isset($params['comment_ip'])) {
            $comment_ip = $sql->escape(str_replace('*', '%', $params['comment_ip']));
            $sql->and($sql->like('comment_ip', $comment_ip));
        }

        if (isset($params['q_author'])) {
            $q_author = $sql->escape(str_replace('*', '%', strtolower($params['q_author'])));
            $sql->and($sql->like('LOWER(comment_author)', $q_author));
        }

        if (!empty($params['search'])) {
            $words = text::splitWords($params['search']);

            if (!empty($words)) {
                # --BEHAVIOR coreCommentSearch
                if (dotclear()->behavior()->has('coreCommentSearch')) {
                    dotclear()->behavior()->call('coreCommentSearch', [&$words, &$sql, &$params]);
                }

                foreach ($words as $i => $w) {
                    $words[$i] = "comment_words LIKE '%" . $sql->escape($w) . "%'";
                }
                $sql->and($words);
            }
        }

        if (!empty($params['sql'])) {
            $sql->sql($params['sql']);
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $sql->order($sql->escape($params['order']));
            } else {
                $sql->order('comment_dt DESC');
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        if (!empty($params['sql_only'])) {
            return $sql->statement();
        }

        $rs = $sql->select();
        $rs->extend(new RsExtComment());

        # --BEHAVIOR-- coreBlogGetComments, Dotclear\Database\Record
        dotclear()->behavior()->call('coreBlogGetComments', $rs);

        return $rs;
    }

    /**
     * Creates a new comment. Takes a cursor as input and returns the new comment ID.
     *
     * @param   Cursor  $cur    The comment cursor
     *
     * @return  int
     */
    public function addComment(Cursor $cur): int
    {
        dotclear()->con()->writeLock(dotclear()->prefix . 'comment');

        try {
            # Get ID
            $sql = new SelectStatement(__METHOD__);
            $id = $sql
                ->column($sql->max('comment_id'))
                ->from(dotclear()->prefix . 'comment')
                ->select()
                ->fInt();

            $cur->setField('comment_id', $id + 1);
            $cur->setField('comment_upddt', date('Y-m-d H:i:s'));

            $offset = Dt::getTimeOffset(dotclear()->blog()->settings()->get('system')->get('blog_timezone'));
            $cur->setField('comment_dt', date('Y-m-d H:i:s', time() + $offset));
            $cur->setField('comment_tz', dotclear()->blog()->settings()->get('system')->get('blog_timezone'));

            $this->getCommentCursor($cur);

            if (null === $cur->getField('comment_ip')) {
                $cur->setField('comment_ip', Http::realIP());
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

        dotclear()->blog()->triggerComment($cur->getField('comment_id'));
        if (-2 != $cur->getField('comment_status')) {
            dotclear()->blog()->triggerBlog();
        }

        return $cur->getField('comment_id');
    }

    /**
     * Updates an existing comment.
     *
     * @param   int     $id     The comment identifier
     * @param   Cursor  $cur    The comment cursor
     *
     * @throws  CoreException
     */
    public function updComment(int $id, Cursor $cur): void
    {
        if (!dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to update comments'));
        }

        if (empty($id)) {
            throw new CoreException(__('No such comment ID'));
        }

        $rs = $this->getComments(['comment_id' => $id]);

        if ($rs->isEmpty()) {
            throw new CoreException(__('No such comment ID'));
        }

        #If user is only usage, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            if ($rs->f('user_id') != dotclear()->user()->userID()) {
                throw new CoreException(__('You are not allowed to update this comment'));
            }
        }

        $this->getCommentCursor($cur);

        $cur->setField('comment_upddt', date('Y-m-d H:i:s'));

        if (!dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            $cur->unsetField('comment_status');
        }

        # --BEHAVIOR-- coreBeforeCommentUpdate, Dotclear\Database\Cursor, Dotclear\Database\Record
        dotclear()->behavior()->call('coreBeforeCommentUpdate', $cur, $rs);

        $cur->update('WHERE comment_id = ' . $id . ' ');

        # --BEHAVIOR-- coreAfterCommentUpdate, Dotclear\Database\Cursor, Dotclear\Database\Record
        dotclear()->behavior()->call('coreAfterCommentUpdate', $cur, $rs);

        dotclear()->blog()->triggerComment($id);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Updates comment status.
     *
     * @param   int     $id         The comment identifier
     * @param   int     $status     The comment status
     */
    public function updCommentStatus(int $id, int $status): void
    {
        $this->updCommentsStatus([$id], $status);
    }

    /**
     * Updates comments status.
     *
     * @param   array|ArrayObject   $ids        The identifiers
     * @param   int                 $status     The status
     *
     * @throws  CoreException
     */
    public function updCommentsStatus(array|ArrayObject $ids, int $status): void
    {
        if (!dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__("You are not allowed to change this comment's status"));
        }

        $co_ids = dotclear()->blog()->cleanIds($ids);

        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->set('comment_status = ' . $status)
            ->where('comment_id' . $sql->in($co_ids))
            ->and('post_id IN (' . $this->getPostOwnerStatement() . ')')
            ->from(dotclear()->prefix . 'comment')
            ->update();

        dotclear()->blog()->triggerComments($co_ids);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Delete a comment.
     *
     * @param   int     $id     The comment identifier
     */
    public function delComment(int $id): void
    {
        $this->delComments([$id]);
    }

    /**
     * Delete comments.
     *
     * @param   array|ArrayObject   $ids    The comments identifiers
     *
     * @throws  CoreException
     */
    public function delComments(array|ArrayObject $ids): void
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
        $sql = new SelectStatement(__METHOD__);
        $rs = $sql
            ->column('post_id')
            ->where('comment_id' . $sql->in($co_ids))
            ->group('post_id')
            ->from(dotclear()->prefix . 'comment')
            ->select();

        while ($rs->fetch()) {
            $affected_posts[] = $rs->fInt('post_id');
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where('comment_id' . $sql->in($co_ids))
            ->and('post_id ' . $this->getPostOwnerStatement())
            ->from(dotclear()->prefix . 'comment')
            ->delete();

        dotclear()->blog()->triggerComments($co_ids, true, $affected_posts);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Delete Junk comments
     *
     * @throws  CoreException  (description)
     */
    public function delJunkComments():void
    {
        if (!dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where('comment_status = -2')
            ->and('post_id ' . $this->getPostOwnerStatement())
            ->from(dotclear()->prefix . 'comment')
            ->delete();

        dotclear()->blog()->triggerBlog();
    }

    private function getPostOwnerStatement()
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->column('tp.post_id')
            ->where('tp.blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->from(dotclear()->prefix . 'post tp');

        #If user can only delete, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $sql->and('tp.user_id = ' . $sql->quote(dotclear()->user()->userID()));
        }

        return $sql->statement();
    }

    /**
     * Gets the comment cursor.
     *
     * @param   Cursor  $cur    The comment cursor
     *
     * @throws  CoreException
     */
    private function getCommentCursor(Cursor $cur): void
    {
        if (null !== $cur->getField('comment_content') && '' == $cur->getField('comment_content')) {
            throw new CoreException(__('You must provide a comment'));
        }

        if (null !== $cur->getField('comment_author') && '' == $cur->getField('comment_author')) {
            throw new CoreException(__('You must provide an author name'));
        }

        if ('' != $cur->getField('comment_email') && !Text::isEmail($cur->getField('comment_email'))) {
            throw new CoreException(__('Email address is not valid.'));
        }

        if (null !== $cur->getField('comment_site') && '' != $cur->getField('comment_site')) {
            if (!preg_match('|^http(s?)://|i', $cur->getField('comment_site'), $matches)) {
                $cur->setField('comment_site', 'http://' . $cur->getField('comment_site'));
            } else {
                $cur->setField('comment_site', strtolower($matches[0]) . substr($cur->getField('comment_site'), strlen($matches[0])));
            }
        }

        if (null === $cur->getField('comment_status')) {
            $cur->setField('comment_status', (int) dotclear()->blog()->settings()->get('system')->get('comments_pub'));
        }

        # Words list
        if (null !== $cur->getField('comment_content')) {
            $cur->setField('comment_words', implode(' ', Text::splitWords($cur->getField('comment_content'))));
        }
    }
}
