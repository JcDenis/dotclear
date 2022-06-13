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
use Dotclear\Exception\InsufficientPermissions;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Exception\InvalidValueReference;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Helper\Status;
use Dotclear\Helper\Statuses;
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
     * @var Statuses $status
     *               The posts status instance
     */
    private $status;

    /**
     * Get posts statuses instance.
     *
     * Posts status methods are accesible from App::core()->posts()->status()
     *
     * @return Statuses The posts statuses instance
     */
    public function status(): Statuses
    {
        if (!($this->status instanceof Statuses)) {
            $this->status = new Statuses(
                'posts',
                new Status(
                    code: 1,
                    id: 'publish',
                    icon: 'images/check-on.png',
                    state: __('published'),
                    action: __('Publish')
                ),
                new Status(
                    code: 0,
                    id: 'unpublish',
                    icon: 'images/check-off.png',
                    state: __('unpublished'),
                    action: __('Unpublish')
                ),
                new Status(
                    code: -1,
                    id: 'schedule',
                    icon: 'images/scheduled.png',
                    state: __('scheduled'),
                    action: __('Schedule')
                ),
                new Status(
                    code: -2,
                    id: 'pending',
                    icon: 'images/check-wrn.png',
                    state: __('pending'),
                    action: __('Mark as pending')
                ),
            );
        }

        return $this->status;
    }

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
        $params = new PostsParam($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeCountPosts, Param, SelectStatement
        App::core()->behavior()->call('coreBeforeCountPosts', param: $params, sql: $query);

        $params->unset('order');
        $params->unset('limit');

        $query->column($query->count($query->unique('P.post_id')));

        $record = $this->queryPostsTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterCountPosts, Record
        App::core()->behavior()->call('coreAfterCountPosts', record: $record);

        return $record->fInt();
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
        $params = new PostsParam($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeGetPosts, Param, SelectStatement
        App::core()->behavior()->call('coreBeforeGetPosts', param: $params, sql: $query);

        if (false === $params->no_content()) {
            $query->columns([
                'post_excerpt',
                'post_excerpt_xhtml',
                'post_content',
                'post_content_xhtml',
                'post_notes',
            ]);
        }
        if (!empty($params->columns())) {
            $query->columns($params->columns());
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
        $query->order($query->escape($params->order('post_dt DESC')));

        if (!empty($params->limit())) {
            $query->limit($params->limit());
        }

        $record = $this->queryPostsTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterGetPosts, Record
        App::core()->behavior()->call('coreAfterGetPosts', record: $record);

        return $record;
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
        $join_user = new JoinStatement();
        $join_user->type('INNER');
        $join_user->from(App::core()->prefix() . 'user U');
        $join_user->on('U.user_id = P.user_id');

        $join_cat = new JoinStatement();
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

        $this->setUserPermissionStatement(sql: $sql);

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
                if (App::core()->behavior()->has('coreBeforeSearchPosts')) {
                    // --BEHAVIOR coreBeforeSearchPosts, Param, SelectStatement
                    App::core()->behavior()->call('coreBeforeSearchPosts', param: $param, sql: $sql);
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
            $sql_media = new SelectStatement();
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

        $record = $sql->select();
        $record->extend(new RsExtPost());

        return $record;
    }

    /**
     * Return next post.
     *
     * @param Record $record               The current post Record
     * @param bool   $restrict_to_category Restrict to same category
     * @param bool   $restrict_to_lang     Restrict to same language
     *
     * @return null|Record The next post
     */
    public function getNextPost(Record $record, bool $restrict_to_category = false, bool $restrict_to_lang = false): ?Record
    {
        $param = new Param();
        $param->set('order', 'post_dt ASC, P.post_id ASC');
        $param->set('sign', '>');
        $param->set('restrict_to_category', $restrict_to_category);
        $param->set('restrict_to_lang', $restrict_to_lang);

        return $this->getNeighborPost(record: $record, param: $param);
    }

    /**
     * Return previous post.
     *
     * @param Record $record               The current post Record
     * @param bool   $restrict_to_category Restrict to same category
     * @param bool   $restrict_to_lang     Restrict to same language
     *
     * @return null|Record The previous post
     */
    public function getPreviousPost(Record $record, bool $restrict_to_category = false, bool $restrict_to_lang = false): ?Record
    {
        $param = new Param();
        $param->set('order', 'post_dt DESC, P.post_id DESC');
        $param->set('sign', '<');
        $param->set('restrict_to_category', $restrict_to_category);
        $param->set('restrict_to_lang', $restrict_to_lang);

        return $this->getNeighborPost(record: $record, param: $param);
    }

    /**
     * Get neightbor post.
     *
     * @param Record $record The current post Record
     * @param Param  $param  The query parameters
     *
     * @return null|Record The post record
     */
    private function getNeighborPost(Record $record, Param $param): ?Record
    {
        $param->set('limit', 1);

        // limit to the same post type
        $param->set('post_type', $record->f('post_type'));

        // next or previous post by date or if same date by id
        $param->push('sql', 'AND ( ' .
            "(post_dt = '" . App::core()->con()->escape($record->f('post_dt')) . "' AND P.post_id " . $param->get('sign') . ' ' . $record->fInt('post_id') . ') ' .
            'OR post_dt ' . $param->get('sign') . " '" . App::core()->con()->escape($record->f('post_dt')) . "' " .
        ') ');

        // limit to the same category
        if ($param->get('restrict_to_category')) {
            if ($record->f('cat_id')) {
                $param->set('cat_id', [$record->f('cat_id')]);
            } else {
                $param->push('sql', 'AND P.cat_id IS NULL ');
            }
        }

        // limit to the same language
        if ($param->get('restrict_to_lang')) {
            if ($record->f('post_lang')) {
                $param->set('post_lang', $record->f('post_lang'));
            } else {
                $param->push('sql', 'AND post_lang IS NULL ');
            }
        }

        $record = $this->getPosts(param: $param);

        return $record->isEmpty() ? null : $record;
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
        $params = new LangsParam($param);

        $sql = new SelectStatement();
        $sql->columns([
            $sql->count('post_id', 'nb_post'),
            'post_lang',
        ]);
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and("post_lang <> ''");
        $sql->and('post_lang IS NOT NULL');
        $sql->group('post_lang');
        $sql->order('post_lang ' . $params->order('desc'));

        $this->setUserPermissionStatement(sql: $sql);

        if (!empty($params->post_type())) {
            $sql->and('post_type' . $sql->in($params->post_type()));
        }

        if (null !== $params->post_lang()) {
            $sql->and('post_lang = ' . $sql->quote($params->post_lang()));
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
        $params = new DatesParam($param);

        $sql = new SelectStatement();

        $dt_f  = '%Y-%m-%d';
        $dt_fc = '%Y%m%d';

        if ('year' == $params->type()) {
            $dt_f  = '%Y-01-01';
            $dt_fc = '%Y0101';
        } elseif ('month' == $params->type()) {
            $dt_f  = '%Y-%m-01';
            $dt_fc = '%Y%m01';
        }

        $dt_f  .= ' 00:00:00';
        $dt_fc .= '000000';

        $join = new JoinStatement();
        $join->type('LEFT');
        $join->from(App::core()->prefix() . 'category C');
        $join->on('P.cat_id = C.cat_id');

        $sql->column('DISTINCT(' . App::core()->con()->dateFormat('post_dt', $dt_f) . ') AS dt');
        $sql->column($sql->count('P.post_id', 'nb_post'));
        $sql->from(App::core()->prefix() . 'post P');
        $sql->join($join->statement());
        $sql->where('P.blog_id = ' . $sql->quote(App::core()->blog()->id));

        if (null !== $params->cat_id()) {
            $sql->and('P.cat_id = ' . $params->cat_id());
            $sql->column('C.cat_url');
            $sql->group('C.cat_url');
        } elseif (null !== $params->cat_url()) {
            $sql->and('C.cat_url = ' . $sql->quote($params->cat_url()));
            $sql->column('C.cat_url');
            $sql->group('C.cat_url');
        }

        $this->setUserPermissionStatement(sql: $sql);

        if (!empty($params->post_type())) {
            $sql->and('post_type' . $sql->in($params->post_type()));
        }

        if (null !== $params->post_lang()) {
            $sql->and('post_lang = ' . $sql->quote($params->post_lang()));
        }

        if (null !== $params->year()) {
            $sql->and(App::core()->con()->dateFormat('post_dt', '%Y') . ' = ' . $sql->quote(sprintf('%04d', $params->year())));
        }

        if (null !== $params->month()) {
            $sql->and(App::core()->con()->dateFormat('post_dt', '%m') . ' = ' . $sql->quote(sprintf('%02d', $params->month())));
        }

        if (null !== $params->day()) {
            $sql->and(App::core()->con()->dateFormat('post_dt', '%d') . ' = ' . $sql->quote(sprintf('%02d', $params->day())));
        }

        // Get next or previous date
        if (null !== $params->next() || null !== $params->previous()) {
            if (null !== $params->next()) {
                $pdir = ' > ';
                $dt   = $params->next();
                $params->set('order', 'asc');
            } else {
                $pdir = ' < ';
                $dt   = $params->previous();
                $params->set('order', 'desc');
            }

            $dt = Clock::format(format: 'YmdHis', date: $dt);

            $sql->and(App::core()->con()->dateFormat('post_dt', $dt_fc) . $pdir . "'" . $dt . "' ");
            $sql->limit(1);
        }

        $sql->group('dt');
        $sql->order('dt ' . $params->order('desc'));

        $record = $sql->select();
        $record->extend(new RsExtDate());

        return $record;
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
            if (App::core()->blog()->isWithoutPassword()) {
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
     * @param Cursor $cursor The post cursor
     *
     * @throws InsufficientPermissions
     */
    public function createPost(Cursor $cursor): int
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to create an entry'));
        }

        App::core()->con()->writeLock(App::core()->prefix() . 'post');

        try {
            // Get ID
            $sql = new SelectStatement();
            $sql->column($sql->max('post_id'));
            $sql->from(App::core()->prefix() . 'post');
            $record = $sql->select();

            $cursor->setField('post_id', $record->fInt() + 1);
            $cursor->setField('blog_id', (string) App::core()->blog()->id);
            $cursor->setField('post_creadt', Clock::database());
            $cursor->setField('post_upddt', Clock::database());

            // Post excerpt and content
            $this->getPostCursor(cursor: $cursor);

            $cursor->setField('post_url', $this->getPostURL(
                url: $cursor->getField('post_url'),
                date: $cursor->getField('post_dt'),
                title: $cursor->getField('post_title'),
                id: $cursor->getField('post_id')
            ));

            if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
                $cursor->setField('post_status', -2);
            }

            // --BEHAVIOR-- coreBeforeCreatePost, Cursor
            App::core()->behavior()->call('coreBeforeCreatePost', cursor: $cursor);

            $cursor->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterCreatePost, Cursor
        App::core()->behavior()->call('coreAfterCreatePost', cursor: $cursor);

        App::core()->blog()->triggerBlog();

        $this->firstPublicationPosts(ids: new Integers($cursor->getField('post_id')));

        return $cursor->getField('post_id');
    }

    /**
     * Update an existing post.
     *
     * @param int    $id     The post ID
     * @param Cursor $cursor The post cursor
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     */
    public function updatePost(int $id, Cursor $cursor): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to update entries'));
        }

        if (empty($id)) {
            throw new MissingOrEmptyValue(__('No such entry ID'));
        }

        $cursor->setField('post_id', $id);

        // Post excerpt and content
        $this->getPostCursor(cursor: $cursor);

        if (null !== $cursor->getField('post_url')) {
            $cursor->setField('post_url', $this->getPostURL(
                url: $cursor->getField('post_url'),
                date: $cursor->getField('post_dt'),
                title: $cursor->getField('post_title'),
                id: $id
            ));
        }

        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            $cursor->unsetField('post_status');
        }

        $cursor->setField('post_upddt', Clock::database());

        // If user is only "usage", we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $sql = new SelectStatement();
            $sql->from(App::core()->prefix() . 'post');
            $sql->where('post_id = ' . $id);
            $sql->and('user_id = ' . $sql->quote(App::core()->user()->userID()));

            $record = $sql->select();
            if ($record->isEmpty()) {
                throw new InsufficientPermissions(__('You are not allowed to edit this entry'));
            }
        }

        // --BEHAVIOR-- coreBeforeUpdatePost, Cursor, int
        App::core()->behavior()->call('coreBeforeUpdatePost', cursor: $cursor, id: $id);

        $cursor->update('WHERE post_id = ' . $id . ' ');

        // --BEHAVIOR-- coreAfterUpdatePost, Cursor, int
        App::core()->behavior()->call('coreAfterUpdatePost', cursor: $cursor, id: $id);

        App::core()->blog()->triggerBlog();

        $this->firstPublicationPosts(ids: new Integers($id));
    }

    /**
     * Update posts status.
     *
     * @param Integers $ids    The posts IDs
     * @param int      $status The status
     *
     * @throws InsufficientPermissions
     */
    public function updatePostsStatus(Integers $ids, int $status): void
    {
        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to change entries status'));
        }

        // --BEHAVIOR-- coreBeforeUpdatePostsStatus, Integers, int
        App::core()->behavior()->call('coreBeforeUpdatePostsStatus', ids: $ids, status: $status);

        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No such entry ID'));
        }

        $sql = new UpdateStatement();
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('post_id' . $sql->in($ids->dump()));
        $sql->from(App::core()->prefix() . 'post');

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            ${$sql}->and('user_id = ' . $sql->quote(App::core()->user()->userID()));
        }

        $sql->set('post_status = ' . $status);
        $sql->set('post_upddt = ' . $sql->quote(Clock::database()));

        $sql->update();
        App::core()->blog()->triggerBlog();

        $this->firstPublicationPosts(ids: $ids);
    }

    /**
     * Update posts selection.
     *
     * @param Integers $ids      The posts IDs
     * @param bool     $selected The selected flag
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     */
    public function updatePostsSelected(Integers $ids, bool $selected): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to change entries selected flag'));
        }

        // --BEHAVIOR-- coreBeforeUpdatePostsAuthor, Integers, bool
        App::core()->behavior()->call('coreBeforeUpdatePostsSelected', ids: $ids, selected: $selected);

        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No such entry ID'));
        }

        $sql = new UpdateStatement();
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('post_id' . $sql->in($ids->dump()));
        $sql->from(App::core()->prefix() . 'post');

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            ${$sql}->and('user_id = ' . $sql->quote(App::core()->user()->userID()));
        }

        $sql->set('post_selected = ' . (int) $selected);
        $sql->set('post_upddt = ' . $sql->quote(Clock::database()));

        $sql->update();
        App::core()->blog()->triggerBlog();
    }

    /**
     * Update posts category.
     *
     * @param Integers $ids    The posts IDs
     * @param string   $author The author ID
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     * @throws InvalidValueReference
     */
    public function updatePostsAuthor(Integers $ids, string $author): void
    {
        if (!App::core()->user()->check('admin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to change entries author'));
        }

        // --BEHAVIOR-- coreBeforeUpdatePostsAuthor, Integers, string
        App::core()->behavior()->call('coreBeforeUpdatePostsAuthor', ids: $ids, author: $author);

        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No such entry ID'));
        }

        $param = new Param();
        $param->set('user_id', $author);

        $record = App::core()->users()->getUsers(param: $param);

        if (!$author || $record->isEmpty()) {
            throw new InvalidValueReference(__('This user does not exist'));
        }
        unset($param, $record);

        $sql = new UpdateStatement();
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('post_id' . $sql->in($ids->dump()));
        $sql->from(App::core()->prefix() . 'post');

        $sql->set('user_id = ' . $sql->quote($author));
        $sql->set('post_upddt = ' . $sql->quote(Clock::database()));

        $sql->update();
        App::core()->blog()->triggerBlog();
    }

    /**
     * Update posts lang.
     *
     * @param Integers $ids  The posts IDs
     * @param string   $lang The lang code
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     */
    public function updatePostsLang(Integers $ids, string $lang): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to change entries lang'));
        }

        // --BEHAVIOR-- coreBeforeUpdatePostsLang, Integers, string
        App::core()->behavior()->call('coreBeforeUpdatePostsLang', ids: $ids, lang: $lang);

        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No such entry ID'));
        }

        $sql = new UpdateStatement();
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('post_id' . $sql->in($ids->dump()));
        $sql->from(App::core()->prefix() . 'post');

        $sql->set('post_lang = ' . $sql->quote($lang));
        $sql->set('post_upddt = ' . $sql->quote(Clock::database()));

        $sql->update();
        App::core()->blog()->triggerBlog();
    }

    /**
     * Update posts category.
     *
     * Based on old posts IDs.
     * <b>$category</b> can be null.
     *
     * @param Integers $ids      The posts IDs
     * @param null|int $category The category ID
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     */
    public function updatePostsCategory(Integers $ids, int|null $category): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to change entries category'));
        }

        // --BEHAVIOR-- coreBeforeUpdatePostsCategory, Integers, ?int
        App::core()->behavior()->call('coreBeforeUpdatePostsCategory', ids: $ids, category: $category);

        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No such entry ID'));
        }

        $sql = new UpdateStatement();
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('post_id' . $sql->in($ids->dump()));
        $sql->from(App::core()->prefix() . 'post');

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $sql->and('user_id = ' . $sql->quote(App::core()->user()->userID()));
        }

        $sql->set('cat_id = ' . (!$category ? 'NULL' : $category));
        $sql->set('post_upddt = ' . $sql->quote(Clock::database()));

        $sql->update();
        App::core()->blog()->triggerBlog();
    }

    /**
     * Change posts category.
     *
     * Based on old category ID.
     * <b>$new_cat_id</b> can be null.
     *
     * @param int      $from The old category ID
     * @param null|int $to   The new category ID
     *
     * @throws InsufficientPermissions
     */
    public function changePostsCategory(int $from, ?int $to): void
    {
        if (!App::core()->user()->check('contentadmin,categories', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to change entries category'));
        }

        // --BEHAVIOR-- coreBeforeChangePostsCategory, int, ?int
        App::core()->behavior()->call('coreBeforeChangePostsCategory', from: $from, to: $to);

        $sql = new UpdateStatement();
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('cat_id = ' . $from);
        $sql->from(App::core()->prefix() . 'post');

        $sql->set('cat_id =' . (!$to ? 'NULL' : $to));
        $sql->set('post_upddt = ' . $sql->quote(Clock::database()));

        $sql->update();
        App::core()->blog()->triggerBlog();
    }

    /**
     * Delete multiple posts.
     *
     * @param Integers $ids The posts IDs
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     */
    public function deletePosts(Integers $ids): void
    {
        if (!App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to delete entries'));
        }

        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No such entry ID'));
        }

        // --BEHAVIOR-- coreBeforeDeletePosts, Integers
        App::core()->behavior()->call('coreBeforeDeletePosts', ids: $ids);

        $sql = new DeleteStatement();
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('post_id' . $sql->in($ids->dump()));

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
    public function publishScheduledPosts(): void
    {
        $sql = new SelectStatement();
        $sql->columns(['post_id', 'post_dt']);
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('post_status = -1');
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));

        $record = $sql->select();
        if ($record->isEmpty()) {
            return;
        }

        $posts = new Integers();
        while ($record->fetch()) {
            if (Clock::ts() >= Clock::ts(date: $record->f('post_dt'))) {
                $posts->add($record->fInt('post_id'));
            }
        }
        if ($posts->count()) {
            // --BEHAVIOR-- coreBeforePublishScheduledPosts, Integers
            App::core()->behavior()->call('coreBeforePublishScheduledPosts', ids: $posts);

            $sql = new UpdateStatement();
            $sql->from(App::core()->prefix() . 'post');
            $sql->set('post_status = 1');
            $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->and('post_id' . $sql->in($posts->dump()));
            $sql->update();

            App::core()->blog()->triggerBlog();

            // --BEHAVIOR-- coreAfterPublishScheduledPosts, Integers
            App::core()->behavior()->call('coreAfterPublishScheduledPosts', ids: $posts);

            $this->firstPublicationPosts(ids: $posts);
        }
    }

    /**
     * First publication mecanism (on post create, update, publish, status).
     *
     * @param Integers $ids The posts IDs
     */
    public function firstPublicationPosts(Integers $ids): void
    {
        $param = new Param();
        $param->set('post_id', $ids->dump());
        $param->set('post_status', 1);
        $param->set('post_firstpub', false);

        $record = $this->getPosts(param: $param);

        $posts = new Integers();
        while ($record->fetch()) {
            $posts->add($record->fInt('post_id'));
        }

        if ($posts->count()) {
            $sql = new UpdateStatement();
            $sql->from(App::core()->prefix() . 'post');
            $sql->set('post_firstpub = 1');
            $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->and('post_id' . $sql->in($posts->dump()));
            $sql->update();

            // --BEHAVIOR-- coreAfterFirstPublicationPosts, Integers
            App::core()->behavior()->call('coreAfterFirstPublicationPosts', ids: $posts);
        }
    }

    /**
     * Retrieve all users having posts on current blog.
     *
     * @param string $post_type post_type filter (post)
     */
    public function getPostsUsers(string $post_type = 'post'): Record
    {
        $sql = new SelectStatement();
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
            $sql = new SelectStatement();
            $sql->columns([
                'cat_id',
                'cat_url',
                'cat_lft',
                'cat_rgt',
            ]);
            $sql->from(App::core()->prefix() . 'category');
            $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->and($field . $sql->in(array_keys($sub)));

            $record = $sql->select();
            while ($record->fetch()) {
                $queries[$record->f($field)] = '(C.cat_lft BETWEEN ' . $record->fInt('cat_lft') . ' AND ' . $record->fInt('cat_rgt') . ')';
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
     * @param Cursor $cursor The post cursor
     *
     * @throws MissingOrEmptyValue
     */
    private function getPostCursor(Cursor $cursor): void
    {
        if ('' == $cursor->getField('post_title')) {
            throw new MissingOrEmptyValue(__('No entry title'));
        }

        if ('' == $cursor->getField('post_content')) {
            throw new MissingOrEmptyValue(__('No entry content'));
        }

        if ('' === $cursor->getField('post_password')) {
            $cursor->setField('post_password', null);
        }

        if ('' == $cursor->getField('post_dt')) {
            $cursor->setField('post_dt', Clock::database());
        }

        if ('' == $cursor->getField('post_content_xhtml')) {
            throw new MissingOrEmptyValue(__('No entry content'));
        }

        // Words list
        if (null !== $cursor->getField('post_title')
            && null !== $cursor->getField('post_excerpt_xhtml')
            && null !== $cursor->getField('post_content_xhtml')
        ) {
            $words = $cursor->getField('post_title') . ' ' .
            $cursor->getfield('post_excerpt_xhtml') . ' ' .
            $cursor->getField('post_content_xhtml');

            $cursor->setField('post_words', implode(' ', Text::splitWords($words)));
        }

        if ($cursor->isField('post_firstpub')) {
            $cursor->unsetField('post_firstpub');
        }

        $post_excerpt       = $cursor->getField('post_excerpt');
        $post_excerpt_xhtml = $cursor->getField('post_excerpt_xhtml');
        $post_content       = $cursor->getField('post_content');
        $post_content_xhtml = $cursor->getField('post_content_xhtml');

        $this->formatPostContent(
            id: $cursor->getField('post_id'),
            format: $cursor->getField('post_format'),
            lang: $cursor->getField('post_lang'),
            excerpt: $post_excerpt,
            excerpt_xhtml: $post_excerpt_xhtml,
            content: $post_content,
            content_xhtml: $post_content_xhtml
        );

        $cursor->setField('post_excerpt', $post_excerpt);
        $cursor->setField('post_excerpt_xhtml', $post_excerpt_xhtml);
        $cursor->setField('post_content', $post_content);
        $cursor->setField('post_content_xhtml', $post_content_xhtml);
    }

    /**
     * Create post HTML content, taking format and lang into account.
     *
     * @param null|int    $id            The post ID
     * @param string      $format        The format
     * @param string      $lang          The language
     * @param null|string $excerpt       The excerpt
     * @param null|string $excerpt_xhtml The excerpt xhtml
     * @param string      $content       The content
     * @param string      $content_xhtml The content xhtml
     */
    public function formatPostContent(?int $id, string $format, string $lang, ?string &$excerpt, ?string &$excerpt_xhtml, string &$content, string &$content_xhtml): void
    {
        if ('wiki' == $format) {
            App::core()->wiki()->initWikiPost();
            App::core()->wiki()->setOpt('note_prefix', 'pnote-' . ($id ?? ''));
            $tag = match (App::core()->blog()->settings()->getGroup('system')->getSetting('note_title_tag')) {
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

        // --BEHAVIOR-- coreAfterFormatPostContent, array
        App::core()->behavior()->call('coreAfterFormatPostContent', content: [
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
     * @param null|string $url   The url
     * @param null|string $date  The post date
     * @param null|string $title The post title
     * @param null|int    $id    The post ID
     *
     * @throws MissingOrEmptyValue
     *
     * @return string The post url
     */
    public function getPostURL(?string $url = null, ?string $date = null, ?string $title = null, ?int $id = null): string
    {
        $url = trim((string) $url);

        // Date on post URLs always use core timezone (should not changed if blog timezone changed)
        $url_patterns = [
            '{y}'  => Clock::format(format: 'Y', date: $date),
            '{m}'  => Clock::format(format: 'm', date: $date),
            '{d}'  => Clock::format(format: 'd', date: $date),
            '{t}'  => Text::tidyURL((string) $title),
            '{id}' => (int) $id,
        ];

        // If URL is empty, we create a new one
        if ('' == $url) {
            // Transform with format
            $url = str_replace(
                array_keys($url_patterns),
                array_values($url_patterns),
                App::core()->blog()->settings()->getGroup('system')->getSetting('post_url_format')
            );
        } else {
            $url = Text::tidyURL($url);
        }

        // Let's check if URL is taken...
        $sql = new SelectStatement();
        $sql->column('post_url');
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('post_url = ' . $sql->quote($url));
        $sql->and('post_id <> ' . (int) $id);
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->order('post_url DESC');

        $record = $sql->select();
        if (!$record->isEmpty()) {
            $sql = new SelectStatement();

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
            $sql->and('post_id <> ' . (int) $id);
            $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->order('post_url DESC');

            $record = $sql->select();
            $a      = [];
            while ($record->fetch()) {
                $a[] = $record->f('post_url');
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
            throw new MissingOrEmptyValue(__('Empty entry URL'));
        }

        return $url;
    }
}
