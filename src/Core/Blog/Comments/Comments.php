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
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
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
final class Comments
{
    /**
     * Retrieve logs count.
     *
     * @see CommentsParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The comments count
     */
    public function countComments(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $param = new CommentsParam($param);
        $param->unset('order');
        $param->unset('limit');

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->column($query->count('comment_id'));

        $rs = $this->queryCommentsTable(param: $param, sql: $query);

        // --BEHAVIOR-- coreBlogCountComments, Record
        App::core()->behavior()->call('coreBlogCountComments', $rs);

        return $rs->fInt();
    }

    /**
     * Retrieve comments.
     *
     * @see CommentsParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return Record The comments
     */
    public function getComments(?Param $param = null, ?SelectStatement $sql = null): Record
    {
        $param = new CommentsParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->order($query->escape($param->order('comment_dt DESC')));

        if (true == $param->no_content()) {
            $content_req = '';
        } else {
            $query->column('comment_content');

            $content_req = 'comment_content, ';
        }

        if (!empty($param->columns())) {
            $query->columns($param->columns());

            $content_req .= implode(', ', $param->columns()) . ', ';
        }

        $query->columns([
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

        if (!empty($param->limit())) {
            $query->limit($param->limit());
        }

        $rs = $this->queryCommentsTable(param: $param, sql: $query);

        // --BEHAVIOR-- coreBlogGetComments, Record
        App::core()->behavior()->call('coreBlogGetComments', $rs);

        return $rs;
    }

    /**
     * Retrieve comments.
     *
     * @param CommentsParam   $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return Record The result
     */
    public function queryCommentsTable(CommentsParam $param, SelectStatement $sql): Record
    {
        $post_join = new JoinStatement(__METHOD__);
        $post_join->type('INNER');
        $post_join->from(App::core()->prefix() . 'post P');
        $post_join->on('C.post_id = P.post_id');

        $user_join = new JoinStatement(__METHOD__);
        $user_join->type('INNER');
        $user_join->from(App::core()->prefix() . 'user U');
        $user_join->on('P.user_id = U.user_id');

        $sql->from(App::core()->prefix() . 'comment C');
        $sql->join($post_join->statement());
        $sql->join($user_join->statement());

        if (!empty($param->from())) {
            $sql->from($param->from());
        }

        if (!empty($param->where())) {
            // Cope with legacy code
            $sql->where($param->where());
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

        if (!empty($param->post_type())) {
            $sql->and('post_type' . $sql->in($param->post_type()));
        }

        if (null !== $param->post_id()) {
            $sql->and('P.post_id = ' . $param->post_id());
        }

        if (null !== $param->cat_id()) {
            $sql->and('P.cat_id = ' . $param->cat_id());
        }

        if (!empty($param->comment_id())) {
            $sql->and('comment_id' . $sql->in($param->comment_id()));
        }

        if (null !== $param->comment_email()) {
            $comment_email = $sql->escape(str_replace('*', '%', $param->comment_email()));
            $sql->and($sql->like('comment_email', $comment_email));
        }

        if (null !== $param->comment_site()) {
            $comment_site = $sql->escape(str_replace('*', '%', $param->comment_site()));
            $sql->and($sql->like('comment_site', $comment_site));
        }

        if (null !== $param->comment_status()) {
            $sql->and('comment_status = ' . $param->comment_status());
        }

        if (null !== $param->comment_status_not()) {
            $sql->and('comment_status <> ' . $param->comment_status_not());
        }

        if (null !== $param->comment_trackback()) {
            $sql->and('comment_trackback = ' . $param->comment_trackback());
        }

        if (null !== $param->comment_ip()) {
            $comment_ip = $sql->escape(str_replace('*', '%', $param->comment_ip()));
            $sql->and($sql->like('comment_ip', $comment_ip));
        }

        if (null !== $param->q_author()) {
            $q_author = $sql->escape(str_replace('*', '%', strtolower($param->q_author())));
            $sql->and($sql->like('LOWER(comment_author)', $q_author));
        }

        if (null !== $param->search()) {
            $words = text::splitWords($param->search());

            if (!empty($words)) {
                $param->set('words', $words);
                if (App::core()->behavior()->has('coreCommentSearch')) {
                    // --BEHAVIOR coreCommentSearch, Param, SelectStatement
                    App::core()->behavior()->call('coreCommentSearch', $param, $sql);
                }

                $w = [];
                foreach ($param->get('words') as $word) {
                    $w[] = $sql->like('comment_words', '%' . $sql->escape($word) . '%');
                }
                if (!empty($w)) {
                    $sql->and($w);
                }
                $param->unset('words');
            }
        }

        if (null !== $param->sql()) {
            $sql->sql($param->sql());
        }

        $rs = $sql->select();
        $rs->extend(new RsExtComment());

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
            $sql->column($sql->max('comment_id'));
            $sql->from(App::core()->prefix() . 'comment');
            $id = $sql->select()->fInt();

            $cur->setField('comment_id', $id + 1);
            $cur->setField('comment_upddt', Clock::database());
            $cur->setField('comment_dt', Clock::database());

            $this->getCommentCursor($cur);

            if (null === $cur->getField('comment_ip')) {
                $cur->setField('comment_ip', Http::realIP());
            }

            // --BEHAVIOR-- coreBeforeCommentCreate, Comments, Cursor
            App::core()->behavior()->call('coreBeforeCommentCreate', $this, $cur);

            $cur->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterCommentCreate, Comments, Cursor
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

        $param = new Param();
        $param->set('comment_id', $id);
        $rs = $this->getComments(param: $param);

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

        // --BEHAVIOR-- coreBeforeCommentUpdate, Cursor, Record
        App::core()->behavior()->call('coreBeforeCommentUpdate', $cur, $rs);

        $cur->update('WHERE comment_id = ' . $id . ' ');

        // --BEHAVIOR-- coreAfterCommentUpdate, Cursor, Record
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
        $sql->set('comment_status = ' . $status);
        $sql->where('comment_id' . $sql->in($co_ids));
        $sql->and('post_id IN (' . $this->getPostOwnerStatement() . ')');
        $sql->from(App::core()->prefix() . 'comment');
        $sql->update();

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
        $sql = new SelectStatement(__METHOD__);
        $sql->column('post_id');
        $sql->where('comment_id' . $sql->in($co_ids));
        $sql->group('post_id');
        $sql->from(App::core()->prefix() . 'comment');
        $rs = $sql->select();

        $affected_posts = [];
        while ($rs->fetch()) {
            $affected_posts[] = $rs->fInt('post_id');
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->where('comment_id' . $sql->in($co_ids));
        $sql->and('post_id ' . $this->getPostOwnerStatement());
        $sql->from(App::core()->prefix() . 'comment');
        $sql->delete();

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
        $sql->where('comment_status = -2');
        $sql->and('post_id ' . $this->getPostOwnerStatement());
        $sql->from(App::core()->prefix() . 'comment');
        $sql->delete();

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
        $sql->column('tp.post_id');
        $sql->where('tp.blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->from(App::core()->prefix() . 'post tp');

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
