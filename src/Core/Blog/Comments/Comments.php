<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Comments;

// Dotclear\Core\Blog\Comments\Comments
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtComment;
use Dotclear\Database\Record;
use Dotclear\Database\Cursor;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;
use Exception;

/**
 * Comments handling methods.
 *
 * @ingroup  Core Comment
 */
class Comments
{
    /**
     * Retrieve comments.
     *
     * <b>$params</b> is an array taking the following optionnal parameters:
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
     * @param array                $params     Parameters
     * @param bool                 $count_only Only counts results
     * @param null|SelectStatement $sql        previous sql statement
     *
     * @return Record|string A record with some more capabilities (or sql statement)
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
            ->from(App::core()->prefix() . 'comment C')
            ->join(
                JoinStatement::init(__METHOD__)
                    ->type('INNER')
                    ->from(App::core()->prefix() . 'post P')
                    ->on('C.post_id = P.post_id')
                    ->statement()
            )
            ->join(
                JoinStatement::init(__METHOD__)
                    ->type('INNER')
                    ->from(App::core()->prefix() . 'user U')
                    ->on('P.user_id = U.user_id')
                    ->statement()
            )
        ;

        if (!empty($params['from'])) {
            $sql->from($params['from']);
        }

        if (!empty($params['where'])) {
            // Cope with legacy code
            $sql->where($params['where']);
        } else {
            $sql->where('P.blog_id = ' . $sql->quote(App::core()->blog()->id, true));
        }

        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $user_id = App::core()->user()->userID();

            $and = [
                'comment_status = 1',
                'P.post_status = 1',
            ];

            if (App::core()->blog()->withoutPassword()) {
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
                array_walk($params['comment_id'], function (&$v, $k) {
                    if (null !== $v) {
                        $v = (int) $v;
                    }
                });
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
                // --BEHAVIOR coreCommentSearch
                if (App::core()->behavior()->has('coreCommentSearch')) {
                    App::core()->behavior()->call('coreCommentSearch', [&$words, &$sql, &$params]);
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

        // --BEHAVIOR-- coreBlogGetComments, Dotclear\Database\Record
        App::core()->behavior()->call('coreBlogGetComments', $rs);

        return $rs;
    }

    /**
     * Create a new comment.
     *
     * Takes a cursor as input and returns the new comment ID.
     *
     * @param Cursor $cur The comment cursor
     *
     * @return int The comment id
     */
    public function addComment(Cursor $cur): int
    {
        App::core()->con()->writeLock(App::core()->prefix() . 'comment');

        try {
            // Get ID
            $sql = new SelectStatement(__METHOD__);
            $id  = $sql
                ->column($sql->max('comment_id'))
                ->from(App::core()->prefix() . 'comment')
                ->select()
                ->fInt()
            ;

            $cur->setField('comment_id', $id + 1);
            $cur->setField('comment_upddt', Clock::database());
            $cur->setField('comment_dt', Clock::database());

            $this->getCommentCursor($cur);

            if (null === $cur->getField('comment_ip')) {
                $cur->setField('comment_ip', Http::realIP());
            }

            // --BEHAVIOR-- coreBeforeCommentCreate, Dotclear\Core\Blog, Dotclear\Database\Record
            App::core()->behavior()->call('coreBeforeCommentCreate', $this, $cur);

            $cur->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterCommentCreate, Dotclear\Core\Blog, Dotclear\Database\Record
        App::core()->behavior()->call('coreAfterCommentCreate', $this, $cur);

        App::core()->blog()->triggerComment($cur->getField('comment_id'));
        if (-2 != $cur->getField('comment_status')) {
            App::core()->blog()->triggerBlog();
        }

        return $cur->getField('comment_id');
    }

    /**
     * Update an existing comment.
     *
     * @param int    $id  The comment identifier
     * @param Cursor $cur The comment cursor
     *
     * @throws CoreException
     */
    public function updComment(int $id, Cursor $cur): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to update comments'));
        }

        if (empty($id)) {
            throw new CoreException(__('No such comment ID'));
        }

        $rs = $this->getComments(['comment_id' => $id]);

        if ($rs->isEmpty()) {
            throw new CoreException(__('No such comment ID'));
        }

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            if ($rs->f('user_id') != App::core()->user()->userID()) {
                throw new CoreException(__('You are not allowed to update this comment'));
            }
        }

        $this->getCommentCursor($cur);

        $cur->setField('comment_upddt', Clock::database());

        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            $cur->unsetField('comment_status');
        }

        // --BEHAVIOR-- coreBeforeCommentUpdate, Dotclear\Database\Cursor, Dotclear\Database\Record
        App::core()->behavior()->call('coreBeforeCommentUpdate', $cur, $rs);

        $cur->update('WHERE comment_id = ' . $id . ' ');

        // --BEHAVIOR-- coreAfterCommentUpdate, Dotclear\Database\Cursor, Dotclear\Database\Record
        App::core()->behavior()->call('coreAfterCommentUpdate', $cur, $rs);

        App::core()->blog()->triggerComment($id);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Update comment status.
     *
     * @param int $id     The comment identifier
     * @param int $status The comment status
     */
    public function updCommentStatus(int $id, int $status): void
    {
        $this->updCommentsStatus([$id], $status);
    }

    /**
     * Updates comments status.
     *
     * @param array|ArrayObject $ids    The identifiers
     * @param int               $status The status
     *
     * @throws CoreException
     */
    public function updCommentsStatus(array|ArrayObject $ids, int $status): void
    {
        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__("You are not allowed to change this comment's status"));
        }

        $co_ids = App::core()->blog()->cleanIds($ids);

        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->set('comment_status = ' . $status)
            ->where('comment_id' . $sql->in($co_ids))
            ->and('post_id IN (' . $this->getPostOwnerStatement() . ')')
            ->from(App::core()->prefix() . 'comment')
            ->update()
        ;

        App::core()->blog()->triggerComments($co_ids);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Delete a comment.
     *
     * @param int $id The comment identifier
     */
    public function delComment(int $id): void
    {
        $this->delComments([$id]);
    }

    /**
     * Delete comments.
     *
     * @param array|ArrayObject $ids The comments identifiers
     *
     * @throws CoreException
     */
    public function delComments(array|ArrayObject $ids): void
    {
        if (!App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $co_ids = App::core()->blog()->cleanIds($ids);

        if (empty($co_ids)) {
            throw new CoreException(__('No such comment ID'));
        }

        // Retrieve posts affected by comments edition
        $affected_posts = [];
        $sql            = new SelectStatement(__METHOD__);
        $rs             = $sql
            ->column('post_id')
            ->where('comment_id' . $sql->in($co_ids))
            ->group('post_id')
            ->from(App::core()->prefix() . 'comment')
            ->select()
        ;

        while ($rs->fetch()) {
            $affected_posts[] = $rs->fInt('post_id');
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where('comment_id' . $sql->in($co_ids))
            ->and('post_id ' . $this->getPostOwnerStatement())
            ->from(App::core()->prefix() . 'comment')
            ->delete()
        ;

        App::core()->blog()->triggerComments($co_ids, true, $affected_posts);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Delete Junk comments.
     *
     * @throws CoreException (description)
     */
    public function delJunkComments(): void
    {
        if (!App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where('comment_status = -2')
            ->and('post_id ' . $this->getPostOwnerStatement())
            ->from(App::core()->prefix() . 'comment')
            ->delete()
        ;

        App::core()->blog()->triggerBlog();
    }

    /**
     * Build post owner SQL statement.
     *
     * @return string the partial SQL statement
     */
    private function getPostOwnerStatement(): string
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->column('tp.post_id')
            ->where('tp.blog_id = ' . $sql->quote(App::core()->blog()->id))
            ->from(App::core()->prefix() . 'post tp')
        ;

        // If user can only delete, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $sql->and('tp.user_id = ' . $sql->quote(App::core()->user()->userID()));
        }

        return $sql->statement();
    }

    /**
     * Get the comment cursor.
     *
     * @param Cursor $cur The comment cursor
     *
     * @throws CoreException
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
            $cur->setField('comment_status', (int) App::core()->blog()->settings()->get('system')->get('comments_pub'));
        }

        // Words list
        if (null !== $cur->getField('comment_content')) {
            $cur->setField('comment_words', implode(' ', Text::splitWords($cur->getField('comment_content'))));
        }
    }
}
