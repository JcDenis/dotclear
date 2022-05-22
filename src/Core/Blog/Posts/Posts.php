<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Posts;

// Dotclear\Core\Blog\Posts\Posts
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtDate;
use Dotclear\Core\RsExt\RsExtPost;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Text;
use Exception;

/**
 * Posts handling methods.
 *
 * @ingroup  Core Post
 */
final class Posts
{
    /**
     * Retrieve blogs count.
     *
     * @see PostsParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The posts count
     */
    public function countPosts(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $param = new PostsParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);

        // --BEHAVIOR-- coreBlogBeforeCountPosts, Param, SelectStatement
        App::core()->behavior()->call('coreBlogBeforeCountPosts', $param, $query);

        $param->unset('order');
        $param->unset('limit');

        $query->column($query->count($query->unique('P.post_id')));

        $rs = $this->queryPostsTable(param: $param, sql: $query);

        // --BEHAVIOR-- coreBlogAfterCountPosts, Record, Param, SelectStatement
        App::core()->behavior()->call('coreBlogAfterCountPosts', $rs, $param, $query);

        return $rs->fInt();
    }

    /**
     * Retrieve posts.
     *
     * @see PostsParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return Record The posts
     */
    public function getPosts(?Param $param = null, ?SelectStatement $sql = null): Record
    {
        $param = new PostsParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);

        // --BEHAVIOR-- coreBlogBeforeGetPosts, Param, SelectStatement
        App::core()->behavior()->call('coreBlogBeforeGetPosts', $param, $query);

        if (false === $param->no_content()) {
            $query->columns([
                'post_excerpt',
                'post_excerpt_xhtml',
                'post_content',
                'post_content_xhtml',
                'post_notes',
            ]);
        }
        if (!empty($param->columns())) {
            $query->columns($param->columns());
        }
        $query->columns([
            'P.post_id',
            'P.blog_id',
            'P.user_id',
            'P.cat_id',
            'post_dt',
            'post_creadt',
            'post_upddt',
            'post_format',
            'post_password',
            'post_url',
            'post_lang',
            'post_title',
            'post_type',
            'post_meta',
            'post_status',
            'post_firstpub',
            'post_selected',
            'post_position',
            'post_open_comment',
            'post_open_tb',
            'nb_comment',
            'nb_trackback',
            'U.user_name',
            'U.user_firstname',
            'U.user_displayname',
            'U.user_email',
            'U.user_url',
            'C.cat_title',
            'C.cat_url',
            'C.cat_desc',
        ]);
        $query->order($query->escape($param->order('post_dt DESC')));

        if (!empty($param->limit())) {
            $query->limit($param->limit());
        }

        $rs = $this->queryPostsTable(param: $param, sql: $query);

        // --BEHAVIOR-- coreBlogAfterGetPosts, Record, Param, SelectStatement
        App::core()->behavior()->call('coreBlogAfterGetPosts', $rs, $param, $query);

        return $rs;
    }

    /**
     * Query post table.
     *
     * @param PostsParam      $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return Record The result
     */
    private function queryPostsTable(PostsParam $param, SelectStatement $sql): Record
    {
        $join_user = new JoinStatement(__METHOD__);
        $join_user->type('INNER');
        $join_user->from(App::core()->prefix() . 'user U');
        $join_user->on('U.user_id = P.user_id');

        $join_cat = new JoinStatement(__METHOD__);
        $join_cat->type('LEFT OUTER');
        $join_cat->from(App::core()->prefix() . 'category C');
        $join_cat->on('P.cat_id = C.cat_id');

        $sql->from(App::core()->prefix() . 'post P', false, true);
        $sql->join($join_user->statement());
        $sql->join($join_cat->statement());

        if (!empty($param->join())) {
            $sql->join($param->join());
        }

        if (!empty($param->from())) {
            $sql->from($param->from());
        }

        if (!empty($param->where())) {
            // Cope with legacy code
            $sql->where($param->where());
        } else {
            $sql->where('P.blog_id = ' . $sql->quote(App::core()->blog()->id));
        }

        $this->setUserPermissionStatement($sql);

        // Adding parameters
        if (!empty($param->post_type())) {
            $sql->and('post_type' . $sql->in($param->post_type()));
        }

        if (!empty($param->post_id())) {
            $sql->and('P.post_id' . $sql->in($param->post_id()));
        }

        if (!empty($param->exclude_post_id())) {
            $sql->and('P.post_id NOT' . $sql->in($param->exclude_post_id()));
        }

        if (null !== $param->post_url()) {
            $sql->and('post_url = ' . $sql->quote($param->post_url()));
        }

        if (null !== $param->user_id()) {
            $sql->and('U.user_id = ' . $sql->quote($param->user_id()));
        }

        if (!empty($param->cat_id())) {
            $sql->and($this->getPostsCategoryFilter($param->cat_id(), 'cat_id'));
        } elseif (!empty($param->cat_url())) {
            $sql->and($this->getPostsCategoryFilter($param->cat_url(), 'cat_url'));
        }

        // Other filters
        if (null !== $param->post_status()) {
            $sql->and('post_status = ' . $param->post_status());
        }

        if (null !== $param->post_firstpub()) {
            $sql->and('post_firstpub = ' . (int) $param->post_firstpub());
        }

        if (null !== $param->post_selected()) {
            $sql->and('post_selected = ' . (int) $param->post_selected());
        }

        if (null !== $param->post_year()) {
            $sql->and($sql->dateFormat('post_dt', '%Y') . ' = ' . $sql->quote(sprintf('%04d', $param->post_year())));
        }

        if (null !== $param->post_month()) {
            $sql->and($sql->dateFormat('post_dt', '%m') . ' = ' . $sql->quote(sprintf('%02d', $param->post_month())));
        }

        if (null !== $param->post_day()) {
            $sql->and($sql->dateFormat('post_dt', '%d') . ' = ' . $sql->quote(sprintf('%02d', $param->post_day())));
        }

        if (null !== $param->post_lang()) {
            $sql->and('post_lang = ' . $sql->quote($param->post_lang()));
        }

        if (null !== $param->search()) {
            $words = text::splitWords($param->search());

            if (!empty($words)) {
                $param->set('words', $words);
                if (App::core()->behavior()->has('corePostSearch')) {
                    // --BEHAVIOR corePostSearch, Param, SelectStatement
                    App::core()->behavior()->call('corePostSearch', $param, $sql);
                }

                $w = [];
                foreach ($param->get('words') as $word) {
                    $w[] = $sql->like('post_words', '%' . $sql->escape($word) . '%');
                }
                if (!empty($w)) {
                    $sql->and($w);
                }
                $param->unset('words');
            }
        }

        if ($param->isset('media')) {
            $sql_media = new SelectStatement(__METHOD__);
            $sql_media->from(App::core()->prefix() . 'post_media M');
            $sql_media->column('M.post_id');
            $sql_media->where('M.post_id = P.post_id');

            if ($param->isset('link_type')) {
                $sql_media->and('M.link_type' . $sql_media->in($param->get('link_type')));
            }

            $sql->and(('0' == $param->get('media') ? 'NOT ' : '') . 'EXISTS (' . $sql_media->statement() . ')');
        }

        if (!empty($param->sql())) {
            $sql->sql($param->sql());
        }

        $rs = $sql->select();
        $rs->extend(new RsExtPost());

        return $rs;
    }

    /**
     * Return next or previous post.
     *
     * This returns a record with post id, title and date
     * for next or previous post according to the post ID.
     * $dir could be 1 (next post) or -1 (previous post).
     *
     * @param Record $post                 The post Record
     * @param int    $dir                  The search direction
     * @param bool   $restrict_to_category Restrict to same category
     * @param bool   $restrict_to_lang     Restrict to same language
     *
     * @return null|Record The next or previous post
     */
    public function getNextPost(Record $post, int $dir, bool $restrict_to_category = false, bool $restrict_to_lang = false): ?Record
    {
        $dt      = $post->f('post_dt');
        $post_id = $post->fInt('post_id');

        if (0 < $dir) {
            $sign  = '>';
            $order = 'ASC';
        } else {
            $sign  = '<';
            $order = 'DESC';
        }

        $param = new Param();
        $param->set('post_type', $post->f('post_type'));
        $param->set('limit', 1);
        $param->set('order', 'post_dt ' . $order . ', P.post_id ' . $order);
        $param->push('sql', 'AND ( ' .
            "(post_dt = '" . App::core()->con()->escape($dt) . "' AND P.post_id " . $sign . ' ' . $post_id . ') ' .
            'OR post_dt ' . $sign . " '" . App::core()->con()->escape($dt) . "' " .
        ') ');

        if ($restrict_to_category) {
            $param->push('sql', $post->f('cat_id') ? 'AND P.cat_id = ' . $post->fInt('cat_id') . ' ' : 'AND P.cat_id IS NULL ');
        }

        if ($restrict_to_lang) {
            $param->push('sql', $post->f('post_lang') ? 'AND post_lang = \'' . App::core()->con()->escape($post->f('post_lang')) . '\' ' : 'AND post_lang IS NULL ');
        }

        $rs = $this->getPosts(param: $param);

        return $rs->isEmpty() ? null : $rs;
    }

    /**
     * Retrieves different languages and post count on blog, based on post_lang field.
     *
     * <b>$params</b> is an array taking the following optionnal parameters:
     *
     * - post_type: Get only entries with given type (default "post", '' for no type)
     * - lang: retrieve post count for selected lang
     * - order: order statement (default post_lang DESC)
     *
     * @param Param $param The parameters
     *
     * @return Record The langs
     */
    public function getLangs(Param $param = null): Record
    {
        $param = new LangsParam($param);

        $sql = new SelectStatement(__METHOD__);
        $sql->columns([
            $sql->count('post_id', 'nb_post'),
            'post_lang',
        ]);
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and("post_lang <> ''");
        $sql->and('post_lang IS NOT NULL');
        $sql->group('post_lang');
        $sql->order('post_lang ' . $param->order('desc'));

        $this->setUserPermissionStatement($sql);

        if (!empty($param->post_type())) {
            $sql->and('post_type' . $sql->in($param->post_type()));
        }

        if (null !== $param->post_lang()) {
            $sql->and('post_lang = ' . $sql->quote($param->post_lang()));
        }

        return $sql->select();
    }

    /**
     * Return a record with all distinct blog dates and post count.
     *
     * @see DatesParam for optionnal parameters
     *
     * @param Param $param The parameters
     *
     * @return Record The dates
     */
    public function getDates(Param $param = null): Record
    {
        $param = new DatesParam($param);

        $sql = new SelectStatement(__METHOD__);

        $dt_f  = '%Y-%m-%d';
        $dt_fc = '%Y%m%d';

        if ('year' == $param->type()) {
            $dt_f  = '%Y-01-01';
            $dt_fc = '%Y0101';
        } elseif ('month' == $param->type()) {
            $dt_f  = '%Y-%m-01';
            $dt_fc = '%Y%m01';
        }

        $dt_f  .= ' 00:00:00';
        $dt_fc .= '000000';

        $join = new JoinStatement(__METHOD__);
        $join->type('LEFT');
        $join->from(App::core()->prefix() . 'category C');
        $join->on('P.cat_id = C.cat_id');

        $sql->column('DISTINCT(' . App::core()->con()->dateFormat('post_dt', $dt_f) . ') AS dt');
        $sql->column($sql->count('P.post_id', 'nb_post'));
        $sql->from(App::core()->prefix() . 'post P');
        $sql->join($join->statement());
        $sql->where('P.blog_id = ' . $sql->quote(App::core()->blog()->id));

        if (null !== $param->cat_id()) {
            $sql->and('P.cat_id = ' . $param->cat_id());
            $sql->column('C.cat_url');
            $sql->group('C.cat_url');
        } elseif (null !== $param->cat_url()) {
            $sql->and('C.cat_url = ' . $sql->quote($param->cat_url()));
            $sql->column('C.cat_url');
            $sql->group('C.cat_url');
        }

        $this->setUserPermissionStatement($sql);

        if (!empty($param->post_type())) {
            $sql->and('post_type' . $sql->in($param->post_type()));
        }

        if (null !== $param->post_lang()) {
            $sql->and('post_lang = ' . $sql->quote($param->post_lang()));
        }

        if (null !== $param->year()) {
            $sql->and(App::core()->con()->dateFormat('post_dt', '%Y') . ' = ' . $sql->quote(sprintf('%04d', $param->year())));
        }

        if (null !== $param->month()) {
            $sql->and(App::core()->con()->dateFormat('post_dt', '%m') . ' = ' . $sql->quote(sprintf('%02d', $param->month())));
        }

        if (null !== $param->day()) {
            $sql->and(App::core()->con()->dateFormat('post_dt', '%d') . ' = ' . $sql->quote(sprintf('%02d', $param->day())));
        }

        // Get next or previous date
        if (null !== $param->next() || null !== $param->previous()) {
            if (null !== $param->next()) {
                $pdir = ' > ';
                $dt   = $param->next();
                $param->set('order', 'asc');
            } else {
                $pdir = ' < ';
                $dt   = $param->previous();
                $param->set('order', 'desc');
            }

            $dt = Clock::format(format: 'YmdHis', date: $dt);

            $sql->and(App::core()->con()->dateFormat('post_dt', $dt_fc) . $pdir . "'" . $dt . "' ");
            $sql->limit(1);
        }

        $sql->group('dt');
        $sql->order('dt ' . $param->order('desc'));

        $rs = $sql->select();
        $rs->extend(new RsExtDate());

        return $rs;
    }

    /**
     * Set user permissions SQL statement.
     *
     * @param SelectStatement $sql The SQL statement
     */
    private function setUserPermissionStatement(SelectStatement $sql): void
    {
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $user_id = App::core()->user()->userID();

            $and = ['post_status = 1'];
            if (App::core()->blog()->withoutPassword()) {
                $and[] = 'post_password IS NULL';
            }
            $or = [$sql->andGroup($and)];
            if ($user_id) {
                $or[] = 'P.user_id = ' . $sql->quote($user_id);
            }
            $sql->and($sql->orGroup($or));
        }
    }

    /**
     * Create a new entry.
     *
     * Takes a cursor as input and returns the new entry ID.
     *
     * @param Cursor $cur The post cursor
     *
     * @throws CoreException
     */
    public function addPost(Cursor $cur): int
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to create an entry'));
        }

        App::core()->con()->writeLock(App::core()->prefix() . 'post');

        try {
            // Get ID
            $sql = new SelectStatement(__METHOD__);
            $sql->column($sql->max('post_id'));
            $sql->from(App::core()->prefix() . 'post');
            $rs = $sql->select();

            $cur->setField('post_id', $rs->fInt() + 1);
            $cur->setField('blog_id', (string) App::core()->blog()->id);
            $cur->setField('post_creadt', Clock::database());
            $cur->setField('post_upddt', Clock::database());

            // Post excerpt and content
            $this->getPostContent($cur, $cur->getField('post_id'));
            $this->getPostCursor($cur);

            $cur->setField('post_url', $this->getPostURL($cur->getField('post_url'), $cur->getField('post_dt'), $cur->getField('post_title'), $cur->getField('post_id')));

            if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
                $cur->setField('post_status', -2);
            }

            // --BEHAVIOR-- coreBeforePostCreate, Cursor
            App::core()->behavior()->call('coreBeforePostCreate', $cur);

            $cur->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterPostCreate, Cursor
        App::core()->behavior()->call('coreAfterPostCreate', $cur);

        App::core()->blog()->triggerBlog();

        $this->firstPublicationEntries([$cur->getField('post_id')]);

        return $cur->getField('post_id');
    }

    /**
     * Update an existing post.
     *
     * @param int    $id  The post identifier
     * @param Cursor $cur The post cursor
     *
     * @throws CoreException
     */
    public function updPost(int $id, Cursor $cur): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to update entries'));
        }

        if (empty($id)) {
            throw new CoreException(__('No such entry ID'));
        }

        // Post excerpt and content
        $this->getPostContent($cur, $id);
        $this->getPostCursor($cur);

        if (null !== $cur->getField('post_url')) {
            $cur->setField('post_url', $this->getPostURL($cur->getField('post_url'), $cur->getField('post_dt'), $cur->getField('post_title'), $id));
        }

        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            $cur->unsetField('post_status');
        }

        $cur->setField('post_upddt', Clock::database());

        // If user is only "usage", we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $sql = new SelectStatement(__METHOD__);
            $sql->from(App::core()->prefix() . 'post');
            $sql->where('post_id = ' . $id);
            $sql->and('user_id = ' . $sql->quote(App::core()->user()->userID()));

            $rs = $sql->select();
            if ($rs->isEmpty()) {
                throw new CoreException(__('You are not allowed to edit this entry'));
            }
        }

        // --BEHAVIOR-- coreBeforePostUpdate, Cursor
        App::core()->behavior()->call('coreBeforePostUpdate', $cur);

        $cur->update('WHERE post_id = ' . $id . ' ');

        // --BEHAVIOR-- coreAfterPostUpdate, Cursor
        App::core()->behavior()->call('coreAfterPostUpdate', $cur);

        App::core()->blog()->triggerBlog();

        $this->firstPublicationEntries([$id]);
    }

    /**
     * Update post status.
     *
     * @param int $id     The identifier
     * @param int $status The status
     */
    public function updPostStatus(int $id, int $status): void
    {
        $this->updPostsStatus([$id], $status);
    }

    /**
     * Update posts status.
     *
     * @param array|ArrayObject $ids    The identifiers
     * @param int               $status The status
     *
     * @throws CoreException
     */
    public function updPostsStatus(array|ArrayObject $ids, int $status): void
    {
        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry status'));
        }

        $posts_ids = App::core()->blog()->cleanIds($ids);

        $strReq = "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
        'AND post_id' . App::core()->con()->in($posts_ids);

        // If user can only publish, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= "AND user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' ";
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix() . 'post');

        $cur->setField('post_status', $status);
        $cur->setField('post_upddt', Clock::database());

        $cur->update($strReq);
        App::core()->blog()->triggerBlog();

        $this->firstPublicationEntries($posts_ids);
    }

    /**
     * Updates post selection.
     *
     * @param int  $id       The identifier
     * @param bool $selected The selected flag
     */
    public function updPostSelected(int $id, bool $selected): void
    {
        $this->updPostsSelected([$id], $selected);
    }

    /**
     * Update posts selection.
     *
     * @param array|ArrayObject $ids      The identifiers
     * @param bool              $selected The selected flag
     *
     * @throws CoreException
     */
    public function updPostsSelected(array|ArrayObject $ids, bool $selected): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry category'));
        }

        $posts_ids = App::core()->blog()->cleanIds($ids);

        $strReq = "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
        'AND post_id' . App::core()->con()->in($posts_ids);

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= "AND user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' ";
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix() . 'post');

        $cur->setField('post_selected', (int) $selected);
        $cur->setField('post_upddt', Clock::database());

        $cur->update($strReq);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Update post category.
     *
     * <b>$cat_id</b> can be null.
     *
     * @param int      $id     The identifier
     * @param null|int $cat_id The cat identifier
     */
    public function updPostCategory(int $id, int|null $cat_id): void
    {
        $this->updPostsCategory([$id], $cat_id);
    }

    /**
     * Update posts category.
     *
     * <b>$cat_id</b> can be null.
     *
     * @param array|ArrayObject $ids    The identifiers
     * @param null|int          $cat_id The cat identifier
     *
     * @throws CoreException
     */
    public function updPostsCategory(array|ArrayObject $ids, int|null $cat_id): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry category'));
        }

        $posts_ids = App::core()->blog()->cleanIds($ids);

        $strReq = "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
        'AND post_id' . App::core()->con()->in($posts_ids);

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= "AND user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' ";
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix() . 'post');

        $cur->setField('cat_id', $cat_id ?: null);
        $cur->setField('post_upddt', Clock::database());

        $cur->update($strReq);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Update posts category.
     *
     * <b>$new_cat_id</b> can be null.
     *
     * @param int      $old_cat_id The old cat identifier
     * @param null|int $new_cat_id The new cat identifier
     *
     * @throws CoreException
     */
    public function changePostsCategory(int $old_cat_id, ?int $new_cat_id): void
    {
        if (!App::core()->user()->check('contentadmin,categories', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change entries category'));
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix() . 'post');

        $cur->setField('cat_id', $new_cat_id ?: null);
        $cur->setField('post_upddt', Clock::database());

        $cur->update(
            'WHERE cat_id = ' . $old_cat_id . ' ' .
            "AND blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' "
        );
        App::core()->blog()->triggerBlog();
    }

    /**
     * Delete a post.
     *
     * @param int $id The post identifier
     */
    public function delPost(int $id): void
    {
        $this->delPosts([$id]);
    }

    /**
     * Delete multiple posts.
     *
     * @param array|ArrayObject $ids The posts identifiers
     *
     * @throws CoreException
     */
    public function delPosts(array|ArrayObject $ids): void
    {
        if (!App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete entries'));
        }

        $posts_ids = App::core()->blog()->cleanIds($ids);

        if (empty($posts_ids)) {
            throw new CoreException(__('No such entry ID'));
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('post_id' . $sql->in($posts_ids));

        // If user can only delete, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $sql->and('user_id = ' . $sql->quote(App::core()->user()->userID()));
        }

        $sql->delete();

        App::core()->blog()->triggerBlog();
    }

    /**
     * Publishe all entries flaged as "scheduled".
     */
    public function publishScheduledEntries(): void
    {
        $sql = new SelectStatement(__METHOD__);
        $sql->columns(['post_id', 'post_dt']);
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('post_status = -1');
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));

        $rs = $sql->select();
        if ($rs->isEmpty()) {
            return;
        }

        /** @var array<int, int> */
        $to_change = [];
        while ($rs->fetch()) {
            if (Clock::ts() >= Clock::ts(date: $rs->f('post_dt'))) {
                $to_change[] = $rs->fInt('post_id');
            }
        }
        if (count($to_change)) {
            // --BEHAVIOR-- coreBeforeScheduledEntriesPublish, array
            App::core()->behavior()->call('coreBeforeScheduledEntriesPublish', $to_change);

            $sql = new UpdateStatement(__METHOD__);
            $sql->from(App::core()->prefix() . 'post');
            $sql->set('post_status = 1');
            $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->and('post_id' . $sql->in($to_change));
            $sql->update();

            App::core()->blog()->triggerBlog();

            // --BEHAVIOR-- coreAfterScheduledEntriesPublish, array
            App::core()->behavior()->call('coreAfterScheduledEntriesPublish', $to_change);

            $this->firstPublicationEntries($to_change);
        }
    }

    /**
     * First publication mecanism (on post create, update, publish, status).
     *
     * @param array<int,int> $ids The posts identifiers
     */
    public function firstPublicationEntries(array $ids): void
    {
        $param = new Param();
        $param->set('post_id', App::core()->blog()->cleanIds($ids));
        $param->set('post_status', 1);
        $param->set('post_firstpub', false);

        $posts = $this->getPosts(param: $param);

        /** @var array<int, int> */
        $to_change = [];
        while ($posts->fetch()) {
            $to_change[] = $posts->fInt('post_id');
        }

        if (count($to_change)) {
            $sql = new UpdateStatement(__METHOD__);
            $sql->from(App::core()->prefix() . 'post');
            $sql->set('post_firstpub = 1');
            $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->and('post_id' . $sql->in($to_change));
            $sql->update();

            // --BEHAVIOR-- coreFirstPublicationEntries, array
            App::core()->behavior()->call('coreFirstPublicationEntries', $to_change);
        }
    }

    /**
     * Retrieve all users having posts on current blog.
     *
     * @param string $post_type post_type filter (post)
     */
    public function getPostsUsers(string $post_type = 'post'): Record
    {
        $sql = new SelectStatement(__METHOD__);
        $sql->columns([
            'P.user_id',
            'user_name',
            'user_firstname',
            'user_displayname',
            'user_email',
        ]);
        $sql->from([
            App::core()->prefix() . 'post P',
            App::core()->prefix() . 'user U',
        ]);
        $sql->where('P.user_id = U.user_id');
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->group([
            'P.user_id',
            'user_name',
            'user_firstname',
            'user_displayname',
            'user_email',
        ]);

        if ($post_type) {
            $sql->and('post_type = ' . $sql->quote($post_type));
        }

        return $sql->select();
    }

    /**
     * Parse category query part.
     *
     * @return string The category query part
     */
    private function getPostsCategoryFilter(array $arr, string $field = 'cat_id'): string
    {
        $field = 'cat_id' == $field ? 'cat_id' : 'cat_url';

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
            if ('cat_id' == $field) {
                if (preg_match('/^null$/i', $id)) {
                    $queries[$id] = 'P.cat_id IS NULL';
                } else {
                    $queries[$id] = 'P.cat_id = ' . (int) $id;
                }
            } else {
                $queries[$id] = "C.cat_url = '" . App::core()->con()->escape($id) . "' ";
            }
        }

        if (!empty($sub)) {
            $sql = new SelectStatement(__METHOD__);
            $sql->columns([
                'cat_id',
                'cat_url',
                'cat_lft',
                'cat_rgt',
            ]);
            $sql->from(App::core()->prefix() . 'category');
            $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->and($field . $sql->in(array_keys($sub)));

            $rs = $sql->select();
            while ($rs->fetch()) {
                $queries[$rs->f($field)] = '(C.cat_lft BETWEEN ' . $rs->fInt('cat_lft') . ' AND ' . $rs->fInt('cat_rgt') . ')';
            }
        }

        // Create queries
        $sql = [
            0 => [], // wanted categories
            1 => [], // excluded categories
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

        return implode(' AND ', $sql);
    }

    /**
     * Get the post cursor.
     *
     * @param Cursor $cur     The post cursor
     * @param int    $post_id The post identifier
     *
     * @throws CoreException
     */
    private function getPostCursor(Cursor $cur, int $post_id = null): void
    {
        if ('' == $cur->getField('post_title')) {
            throw new CoreException(__('No entry title'));
        }

        if ('' == $cur->getField('post_content')) {
            throw new CoreException(__('No entry content'));
        }

        if ('' === $cur->getField('post_password')) {
            $cur->setField('post_password', null);
        }

        if ('' == $cur->getField('post_dt')) {
            $cur->setField('post_dt', Clock::database());
        }

        $post_id = is_int($post_id) ? $post_id : $cur->getField('post_id');

        if ('' == $cur->getField('post_content_xhtml')) {
            throw new CoreException(__('No entry content'));
        }

        // Words list
        if (null !== $cur->getField('post_title')
            && null !== $cur->getField('post_excerpt_xhtml')
            && null !== $cur->getField('post_content_xhtml')
        ) {
            $words = $cur->getField('post_title') . ' ' .
            $cur->getfield('post_excerpt_xhtml') . ' ' .
            $cur->getField('post_content_xhtml');

            $cur->setField('post_words', implode(' ', Text::splitWords($words)));
        }

        if ($cur->isField('post_firstpub')) {
            $cur->unsetField('post_firstpub');
        }
    }

    /**
     * Get the post content.
     *
     * @param Cursor $cur     The post cursor
     * @param int    $post_id The post identifier
     */
    private function getPostContent(Cursor $cur, int $post_id): void
    {
        $post_excerpt       = $cur->getfield('post_excerpt');
        $post_excerpt_xhtml = $cur->getfield('post_excerpt_xhtml');
        $post_content       = $cur->getfield('post_content');
        $post_content_xhtml = $cur->getfield('post_content_xhtml');

        $this->setPostContent(
            $post_id,
            $cur->getfield('post_format'),
            $cur->getfield('post_lang'),
            $post_excerpt,
            $post_excerpt_xhtml,
            $post_content,
            $post_content_xhtml
        );

        $cur->setfield('post_excerpt', $post_excerpt);
        $cur->setfield('post_excerpt_xhtml', $post_excerpt_xhtml);
        $cur->setfield('post_content', $post_content);
        $cur->setfield('post_content_xhtml', $post_content_xhtml);
    }

    /**
     * Create post HTML content, taking format and lang into account.
     *
     * @param null|int    $post_id       The post identifier
     * @param string      $format        The format
     * @param string      $lang          The language
     * @param null|string $excerpt       The excerpt
     * @param null|string $excerpt_xhtml The excerpt xhtml
     * @param string      $content       The content
     * @param string      $content_xhtml The content xhtml
     */
    public function setPostContent(?int $post_id, string $format, string $lang, ?string &$excerpt, ?string &$excerpt_xhtml, string &$content, string &$content_xhtml): void
    {
        if ('wiki' == $format) {
            App::core()->wiki()->initWikiPost();
            App::core()->wiki()->setOpt('note_prefix', 'pnote-' . ($post_id ?? ''));
            $tag = match (App::core()->blog()->settings()->get('system')->get('note_title_tag')) {
                1       => 'h3',
                2       => 'p',
                default => 'h4',
            };
            App::core()->wiki()->setOpt('note_str', '<div class="footnotes"><' . $tag . ' class="footnotes-title">' .
                __('Notes') . '</' . $tag . '>%s</div>');
            App::core()->wiki()->setOpt('note_str_single', '<div class="footnotes"><' . $tag . ' class="footnotes-title">' .
                __('Note') . '</' . $tag . '>%s</div>');
            if (str_starts_with($lang, 'fr')) {
                App::core()->wiki()->setOpt('active_fr_syntax', 1);
            }
        }

        if ($excerpt) {
            $excerpt_xhtml = App::core()->formater()->callEditorFormater('LegacyEditor', $format, $excerpt);
            $excerpt_xhtml = Html::filter($excerpt_xhtml);
        } else {
            $excerpt_xhtml = '';
        }

        if ($content) {
            $content_xhtml = App::core()->formater()->callEditorFormater('LegacyEditor', $format, $content);
            $content_xhtml = Html::filter($content_xhtml);
        } else {
            $content_xhtml = '';
        }

        // --BEHAVIOR-- coreAfterPostContentFormat, array
        App::core()->behavior()->call('coreAfterPostContentFormat', [
            'excerpt'       => &$excerpt,
            'content'       => &$content,
            'excerpt_xhtml' => &$excerpt_xhtml,
            'content_xhtml' => &$content_xhtml,
        ]);
    }

    /**
     * Return URL for a post according to blog setting <b>post_url_format</b>.
     *
     * It will try to guess URL and append some figures if needed.
     *
     * @param null|string $url        The url
     * @param null|string $post_dt    The post dt
     * @param null|string $post_title The post title
     * @param null|int    $post_id    The post identifier
     *
     * @return string The post url
     */
    public function getPostURL(?string $url, ?string $post_dt, ?string $post_title, ?int $post_id): string
    {
        $url = trim((string) $url);

        // Date on post URLs always use core timezone (should not changed if blog timezone changed)
        $url_patterns = [
            '{y}'  => Clock::format(format: 'Y', date: $post_dt),
            '{m}'  => Clock::format(format: 'm', date: $post_dt),
            '{d}'  => Clock::format(format: 'd', date: $post_dt),
            '{t}'  => Text::tidyURL((string) $post_title),
            '{id}' => (int) $post_id,
        ];

        // If URL is empty, we create a new one
        if ('' == $url) {
            // Transform with format
            $url = str_replace(
                array_keys($url_patterns),
                array_values($url_patterns),
                App::core()->blog()->settings()->get('system')->get('post_url_format')
            );
        } else {
            $url = Text::tidyURL($url);
        }

        // Let's check if URL is taken...
        $sql = new SelectStatement(__METHOD__);
        $sql->column('post_url');
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('post_url = ' . $sql->quote($url));
        $sql->and('post_id <> ' . (int) $post_id);
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->order('post_url DESC');

        $rs = $sql->select();
        if (!$rs->isEmpty()) {
            $sql = new SelectStatement(__METHOD__);

            if (App::core()->con()->syntax() == 'mysql') {
                $clause = "REGEXP '^" . App::core()->con()->escape(preg_quote($url)) . "[0-9]+$'";
            } elseif (App::core()->con()->driver() == 'pgsql') {
                $clause = "~ '^" . App::core()->con()->escape(preg_quote($url)) . "[0-9]+$'";
            } else {
                $clause = "LIKE '" .
                App::core()->con()->escape(preg_replace(['/%/', '/_/', '/!/'], ['!%', '!_', '!!'], $url)) . "%' ESCAPE '!'";
            }

            $sql->column('post_url');
            $sql->from(App::core()->prefix() . 'post');
            $sql->where('post_url ' . $clause);
            $sql->and('post_id <> ' . (int) $post_id);
            $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->order('post_url DESC');

            $rs = $sql->select();
            $a  = [];
            while ($rs->fetch()) {
                $a[] = $rs->f('post_url');
            }

            natsort($a);
            $t_url = end($a);

            if ($a && preg_match('/(.*?)([0-9]+)$/', $t_url, $m)) {
                $i   = (int) $m[2];
                $url = $m[1];
            } else {
                $i = 1;
            }

            return $url . ($i + 1);
        }

        // URL is empty?
        if ('' == $url) {
            throw new CoreException(__('Empty entry URL'));
        }

        return $url;
    }
}
