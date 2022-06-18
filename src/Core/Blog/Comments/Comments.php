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
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtComment;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\SqlStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\InsufficientPermissions;
use Dotclear\Exception\InvalidValueReference;
use Dotclear\Exception\InvalidValueFormat;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Helper\Status;
use Dotclear\Helper\Statuses;
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
     * @var Statuses $status
     *               The comments status instance
     */
    private $status;

    /**
     * Get comments statuses instance.
     *
     * Comments status methods are accesible from App::core()->comments()->status()
     *
     * @return Statuses The comments statuses instance
     */
    public function status(): Statuses
    {
        if (!($this->status instanceof Statuses)) {
            $this->status = new Statuses(
                'comments',
                new Status(
                    code: 1,
                    id: 'publish',
                    icon: 'images/check-on.png',
                    state: __('Published'),
                    action: __('Publish')
                ),
                new Status(
                    code: 0,
                    id: 'unpublish',
                    icon: 'images/check-off.png',
                    state: __('Unpublished'),
                    action: __('Unpublish')
                ),
                new Status(
                    code: -1,
                    id: 'pending',
                    icon: 'images/check-wrn.png',
                    state: __('Pending'),
                    action: __('Mark as pending')
                ),
                new Status(
                    code: -2,
                    id: 'junk',
                    icon: 'images/junk.png',
                    state: __('Junk'),
                    action: __('Mark as junk')
                ),
            );
        }

        return $this->status;
    }

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
        $params = new CommentsParam($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeCountComments, Param, SelectStatement
        App::core()->behavior('coreBeforeCountComments')->call(param: $params, sql: $query);

        $params->unset('order');
        $params->unset('limit');

        $query->column($query->count('comment_id'));

        $record = $this->queryCommentsTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterCountComments, Record
        App::core()->behavior('coreAfterCountComments')->call(record: $record);

        return $record->integer();
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
        $params = new CommentsParam($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeGetComments, Param, SelectStatement
        App::core()->behavior('coreBeforeGetComments')->call(param: $params, sql: $query);

        if (true !== $params->no_content()) {
            $query->column('comment_content');
        }

        if (!empty($params->columns())) {
            $query->columns($params->columns());
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
        $query->order($query->escape($params->order('comment_dt DESC')));

        if (!empty($params->limit())) {
            $query->limit($params->limit());
        }

        $record = $this->queryCommentsTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterGetComments, Record
        App::core()->behavior('coreAfterGetComments')->call(record: $record);

        return $record;
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
        $post_join = new JoinStatement();
        $post_join->type('INNER');
        $post_join->from(App::core()->prefix() . 'post P');
        $post_join->on('C.post_id = P.post_id');

        $user_join = new JoinStatement();
        $user_join->type('INNER');
        $user_join->from(App::core()->prefix() . 'user U');
        $user_join->on('P.user_id = U.user_id');

        $sql->from(App::core()->prefix() . 'comment C', false, true);
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

            if (App::core()->blog()->isWithoutPassword()) {
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
                if (App::core()->behavior('coreBeforeSearchComments')->count()) {
                    // --BEHAVIOR coreBeforeSearchComments, Param, SelectStatement
                    App::core()->behavior('coreBeforeSearchComments')->call(param: $param, sql: $sql);
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

        $record = $sql->select();
        $record->extend(new RsExtComment());

        return $record;
    }

    /**
     * Create a new comment.
     *
     * Takes a cursor as input and returns the new comment ID.
     *
     * @param Cursor $cursor The comment cursor
     *
     * @return int The comment id
     */
    public function createComment(Cursor $cursor): int
    {
        App::core()->con()->writeLock(App::core()->prefix() . 'comment');

        try {
            // Get ID
            $sql = new SelectStatement();
            $sql->column($sql->max('comment_id'));
            $sql->from(App::core()->prefix() . 'comment');
            $id = $sql->select()->integer();

            $cursor->setField('comment_id', $id + 1);
            $cursor->setField('comment_upddt', Clock::database());
            $cursor->setField('comment_dt', Clock::database());

            $this->getCommentCursor(cursor: $cursor);

            if (null === $cursor->getField('comment_ip')) {
                $cursor->setField('comment_ip', Http::realIP());
            }

            // --BEHAVIOR-- coreBeforeCreateComment, Comments, Cursor
            App::core()->behavior('coreBeforeCreateComment')->call(cursor: $cursor);

            $cursor->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterCreateComment, Comments, Cursor
        App::core()->behavior('coreAfterCreateComment')->call(cursor: $cursor);

        App::core()->blog()->triggerComments(ids: new Integers($cursor->getField('comment_id')));
        if (-2 != $cursor->getField('comment_status')) {
            App::core()->blog()->triggerBlog();
        }

        return $cursor->getField('comment_id');
    }

    /**
     * Update an existing comment.
     *
     * @param int    $id     The comment ID
     * @param Cursor $cursor The comment cursor
     *
     * @throws InsufficientPermissions
     * @throws InvalidValueReference
     */
    public function updateComment(int $id, Cursor $cursor): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to update comments'));
        }

        if (empty($id)) {
            throw new InvalidValueReference(__('No such comment ID'));
        }

        $param = new Param();
        $param->set('comment_id', $id);

        $record = $this->getComments(param: $param);
        if ($record->isEmpty()) {
            throw new InvalidValueReference(__('No such comment ID'));
        }

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            if ($record->field('user_id') != App::core()->user()->userID()) {
                throw new InsufficientPermissions(__('You are not allowed to update this comment'));
            }
        }

        $this->getCommentCursor(cursor: $cursor);

        $cursor->setField('comment_upddt', Clock::database());

        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            $cursor->unsetField('comment_status');
        }

        // --BEHAVIOR-- coreBeforeUpdateComment, Cursor, int
        App::core()->behavior('coreBeforeUpdateComment')->call(cursor: $cursor, id: $id);

        $cursor->update('WHERE comment_id = ' . $id . ' ');

        // --BEHAVIOR-- coreAfterUpdateComment, Cursor, int
        App::core()->behavior('coreAfterUpdateComment')->call(cursor: $cursor, id: $id);

        App::core()->blog()->triggerComments(ids: new Integers($id));
        App::core()->blog()->triggerBlog();
    }

    /**
     * Get the comment cursor.
     *
     * @param Cursor $cursor The comment cursor
     *
     * @throws MissingOrEmptyValue
     * @throws InvalidValueFormat
     */
    private function getCommentCursor(Cursor $cursor): void
    {
        if (null !== $cursor->getField('comment_content') && '' == $cursor->getField('comment_content')) {
            throw new MissingOrEmptyValue(__('You must provide a comment'));
        }

        if (null !== $cursor->getField('comment_author') && '' == $cursor->getField('comment_author')) {
            throw new MissingOrEmptyValue(__('You must provide an author name'));
        }

        if ('' != $cursor->getField('comment_email') && !Text::isEmail($cursor->getField('comment_email'))) {
            throw new InvalidValueFormat(__('Email address is not valid.'));
        }

        if (null !== $cursor->getField('comment_site') && '' != $cursor->getField('comment_site')) {
            if (!preg_match('|^http(s?)://|i', $cursor->getField('comment_site'), $matches)) {
                $cursor->setField('comment_site', 'http://' . $cursor->getField('comment_site'));
            } else {
                $cursor->setField('comment_site', strtolower($matches[0]) . substr($cursor->getField('comment_site'), strlen($matches[0])));
            }
        }

        if (null === $cursor->getField('comment_status')) {
            $cursor->setField('comment_status', (int) App::core()->blog()->settings()->getGroup('system')->getSetting('comments_pub'));
        }

        // Words list
        if (null !== $cursor->getField('comment_content')) {
            $cursor->setField('comment_words', implode(' ', Text::splitWords($cursor->getField('comment_content'))));
        }
    }

    /**
     * Updates comments status.
     *
     * @param Integers $ids    The comments IDs
     * @param int      $status The status
     *
     * @throws InsufficientPermissions
     */
    public function updateCommentsStatus(Integers $ids, int $status): void
    {
        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__("You are not allowed to change this comment's status"));
        }

        $sql = new UpdateStatement();
        $sql->set('comment_status = ' . $status);
        $sql->where('comment_id' . $sql->in($ids->dump()));
        $sql->from(App::core()->prefix() . 'comment');

        $this->setPostOwnerStatement(sql: $sql);

        $sql->update();

        App::core()->blog()->triggerComments(ids: $ids);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Delete comments.
     *
     * @param Integers $ids The comments IDs
     *
     * @throws InsufficientPermissions
     * @throws InvalidValueReference
     */
    public function deleteComments(Integers $ids): void
    {
        if (!App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to delete comments'));
        }

        if (!$ids->count()) {
            throw new InvalidValueReference(__('No such comment ID'));
        }

        // Retrieve posts affected by comments deletion
        $sql = new SelectStatement();
        $sql->column('post_id');
        $sql->where('comment_id' . $sql->in($ids->dump()));
        $sql->group('post_id');
        $sql->from(App::core()->prefix() . 'comment');

        $posts  = new Integers();
        $record = $sql->select();
        while ($record->fetch()) {
            $posts->add($record->integer('post_id'));
        }

        // --BEHAVIOR-- coreBeforeDeleteComments, Integers
        App::core()->behavior('coreBeforeDeleteComments')->call(ids: $ids);

        $sql = new DeleteStatement();
        $sql->where('comment_id' . $sql->in($ids->dump()));
        $sql->from(App::core()->prefix() . 'comment');

        $this->setPostOwnerStatement(sql: $sql);

        $sql->delete();

        App::core()->blog()->triggerComments(ids: $ids, posts: $posts);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Delete Junk comments.
     *
     * @throws InsufficientPermissions
     */
    public function deleteJunkComments(): void
    {
        if (!App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to delete comments'));
        }

        $sql = new DeleteStatement();
        $sql->where('comment_status = -2');
        $sql->from(App::core()->prefix() . 'comment');

        $this->setPostOwnerStatement(sql: $sql);

        $sql->delete();

        App::core()->blog()->triggerBlog();
    }

    /**
     * Build post owner SQL statement.
     *
     * @param SqlStatement $sql The SQL statement
     */
    private function setPostOwnerStatement(SqlStatement $sql): void
    {
        $in = new SelectStatement();
        $in->column('tp.post_id');
        $in->where('tp.blog_id = ' . $in->quote(App::core()->blog()->id));
        $in->from(App::core()->prefix() . 'post tp');

        // If user can only delete, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $in->and('tp.user_id = ' . $in->quote(App::core()->user()->userID()));
        }

        $sql->and('post_id IN (' . $in->statement() . ') ');
    }
}
