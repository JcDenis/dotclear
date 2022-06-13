<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blogs;

// Dotclear\Core\Blogs\Blogs
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtBlog;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\InsufficientPermissions;
use Dotclear\Exception\InvalidValueFormat;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Helper\Status;
use Dotclear\Helper\Statuses;

/**
 * Blogs handling methods.
 *
 * @ingroup  Core Blog
 */
final class Blogs
{
    /**
     * @var Statuses $status
     *               The blogs status instance
     */
    private $status;

    /**
     * Get blogs status instance.
     *
     * Blogs status methods are accesible from App::core()->blogs()->status()
     *
     * @return Statuses The blogs status instance
     */
    public function status(): Statuses
    {
        if (!($this->status instanceof Statuses)) {
            $this->status = new Statuses(
                'blogs',
                new Status(
                    code: 1,
                    id: 'online',
                    icon: 'images/check-on.png',
                    state: __('online'),
                    action: __('Set online')
                ),
                new Status(
                    code: 0,
                    id: 'offline',
                    icon: 'images/check-off.png',
                    state: __('offline'),
                    action: __('Set offline')
                ),
                new Status(
                    code: -1,
                    id: 'removed',
                    icon: 'images/check-wrn.png',
                    state: __('removed'),
                    action: __('Set as removed')
                ),
            );
        }

        return $this->status;
    }

    /**
     * Retrieve blogs count.
     *
     * @see BlogsParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The blogs count
     */
    public function countBlogs(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $params = new BlogsParam($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeCountBlogs, Param, SelectStatement
        App::core()->behavior()->call('coreBeforeCountBlogs', param: $params, sql: $query);

        $params->unset('order');
        $params->unset('limit');

        $query->column($query->count('B.blog_id'));

        $record = $this->queryBlogsTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterCountBlogs, Record
        App::core()->behavior()->call('coreAfterCountBlogs', record: $record);

        return $record->integer();
    }

    /**
     * Retrieve blogs.
     *
     * @see BlogsParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return Record The blogs
     */
    public function getBlogs(?Param $param = null, ?SelectStatement $sql = null): Record
    {
        $params = new BlogsParam($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeGetBlogs, Param, SelectStatement
        App::core()->behavior()->call('coreBeforeGetBlogs', param: $params, sql: $query);

        if (!empty($params->columns())) {
            $query->columns($params->columns());
        }

        $query->columns([
            'B.blog_id',
            'blog_uid',
            'blog_url',
            'blog_name',
            'blog_desc',
            'blog_creadt',
            'blog_upddt',
            'blog_status',
        ]);
        $query->order($query->escape($params->order('B.blog_id ASC')));

        if (!empty($params->limit())) {
            $query->limit($params->limit());
        }

        $record = $this->queryBlogsTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterGetBlogs, Record
        App::core()->behavior()->call('coreAfterGetBlogs', record: $record);

        return $record;
    }

    /**
     * Query log table.
     *
     * @param BlogsParam      $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return Record The result
     */
    private function queryBlogsTable(BlogsParam $param, SelectStatement $sql): Record
    {
        $sql->from(App::core()->prefix() . 'blog B', false, true);
        $sql->where('NULL IS NULL');

        if (App::core()->user()->userID() && !App::core()->user()->isSuperAdmin()) {
            $join = new JoinStatement();
            $join->type('INNER');
            $join->from(App::core()->prefix() . 'permissions PE');
            $join->on('B.blog_id = PE.blog_id');

            $sql->join($join->statement());
            $sql->and($sql->orGroup([
                $sql->like('permissions', '%|usage|%'),
                $sql->like('permissions', '%|admin|%'),
                $sql->like('permissions', '%|contentadmin|%'),
            ]));
            $sql->and('blog_status IN (1,0)');
        } elseif (!App::core()->user()->userID()) {
            $sql->and('blog_status IN (1,0)');
        }

        if (null !== $param->blog_status() && App::core()->user()->isSuperAdmin()) {
            $sql->and('blog_status = ' . $param->blog_status());
        }

        if (!empty($param->blog_id())) {
            $sql->and('B.blog_id ' . $sql->in($param->blog_id()));
        }

        if (!empty($param->q())) {
            $q = str_replace('*', '%', strtolower($param->q()));
            $sql->and($sql->orGroup([
                $sql->like('LOWER(B.blog_id)', $q),
                $sql->like('LOWER(B.blog_name)', $q),
                $sql->like('LOWER(B.blog_url)', $q),
            ]));
        }

        $record = $sql->select();
        $record->extend(new RsExtBlog());

        return $record;
    }

    /**
     * Add a new blog.
     *
     * @param cursor $cursor The blog cursor
     *
     * @throws InsufficientPermissions
     */
    public function createBlog(Cursor $cursor): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        // --BEHAVIOR-- coreBeforeCreateBlog, Cursor
        App::core()->behavior()->call('coreBeforeCreateBlog', cursor: $cursor);

        $this->getBlogCursor(cursor: $cursor);

        $cursor->setField('blog_creadt', Clock::database());
        $cursor->setField('blog_upddt', Clock::database());
        $cursor->setField('blog_uid', md5(uniqid()));

        $cursor->insert();

        // --BEHAVIOR-- coreAfterCreateBlog, Cursor
        App::core()->behavior()->call('coreAfterCreateBlog', cursor: $cursor);
    }

    /**
     * Update a given blog.
     *
     * @param string $id     The blog ID
     * @param Cursor $cursor The blog cursor
     */
    public function updateBlog(string $id, Cursor $cursor): void
    {
        // --BEHAVIOR-- coreBeforeUpdateBlog, int, Cursor
        App::core()->behavior()->call('coreBeforeUpdateBlog', id: $id, cursor: $cursor);

        $this->getBlogCursor(cursor: $cursor);

        $cursor->setField('blog_upddt', Clock::database());

        $cursor->update("WHERE blog_id = '" . App::core()->con()->escape($id) . "'");

        // --BEHAVIOR-- coreAfterUpdateBlog, int, Cursor
        App::core()->behavior()->call('coreAfterUpdateBlog', id: $id, cursor: $cursor);
    }

    /**
     * Get the blog cursor.
     *
     * @param Cursor $cursor The cursor
     *
     * @throws InvalidValueFormat
     * @throws MissingOrEmptyValue
     */
    private function getBlogCursor(Cursor $cursor): void
    {
        if (null !== $cursor->getField('blog_id')
            && !preg_match('/^[A-Za-z0-9._-]{2,}$/', (string) $cursor->getField('blog_id'))
            || !$cursor->getField('blog_id')
        ) {
            throw new InvalidValueFormat(__('Blog ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (null !== $cursor->getField('blog_name')
            && '' == $cursor->getField('blog_name')
            || !$cursor->getField('blog_name')
        ) {
            throw new MissingOrEmptyValue(__('No blog name'));
        }

        if (null !== $cursor->getField('blog_url')
            && '' == $cursor->getField('blog_url')
            || !$cursor->getField('blog_url')
        ) {
            throw new MissingOrEmptyValue(__('No blog URL'));
        }

        if (null !== $cursor->getField('blog_desc')) {
            $cursor->setField('blog_desc', Html::clean($cursor->getField('blog_desc')));
        }
    }

    /**
     * Update blogs status.
     *
     * @param Strings $ids    The blogs IDs
     * @param int     $status The status
     *
     * @throws InsufficientPermissions
     */
    public function updateBlogsStatus(Strings $ids, int $status): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        // --BEHAVIOR-- coreBeforeUpdateBlogsStatus, Strings, int
        App::core()->behavior()->call('coreBeforeUpdateBlogsStatus', ids: $ids, status: $status);

        $sql = new UpdateStatement();
        $sql->from(App::core()->prefix() . 'blog');
        $sql->set('blog_status = ' . $status);
        // $sql->set('blog_upddt = ' . $sql->quote(Clock::database()));
        $sql->where('blog_id' . $sql->in($ids->dump()));
        $sql->update();
    }

    /**
     * Remove blogs.
     *
     * @warning This will remove everything related to the blog (posts,
     *
     * Current blog can not be deleted.
     * categories, comments, links...)
     *
     * @param Strings $ids The blogs IDs
     *
     * @throws InsufficientPermissions
     */
    public function deleteBlogs(Strings $ids): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        // Do not delete current blog
        if ($ids->exists(App::core()->blog()->id)) {
            $ids->remove(App::core()->blog()->id);
        }

        if ($ids->count()) {
            // --BEHAVIOR-- coreBeforeDeleteBlogs, Strings
            App::core()->behavior()->call('coreBeforeDeleteBlogs', ids: $ids);

            $sql = new DeleteStatement();
            $sql->from(App::core()->prefix() . 'blog');
            $sql->where('blog_id' . $sql->in($ids->dump()));
            $sql->delete();
        }
    }

    /**
     * Check if blog exists.
     *
     * @param string $id The blog ID
     *
     * @return bool True if blog exists, False otherwise
     */
    public function blogExists(string $id): bool
    {
        $sql = new SelectStatement();
        $sql->column('blog_id');
        $sql->from(App::core()->prefix() . 'blog');
        $sql->where('blog_id = ' . $sql->quote($id));
        $record = $sql->select();

        return !$record->isEmpty();
    }

    /**
     * Counts the number of blog posts.
     *
     * @param string      $id   The blog ID
     * @param null|string $type The post type
     *
     * @return int Number of blog posts
     */
    public function countBlogPosts(string $id, ?string $type = null): int
    {
        $sql = new SelectStatement();
        $sql->column($sql->count('post_id'));
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('blog_id = ' . $sql->quote($id));

        if ($type) {
            $sql->and('post_type = ' . $sql->quote($type));
        }

        return $sql->select()->integer();
    }
}
