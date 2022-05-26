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

/**
 * Blogs handling methods.
 *
 * @ingroup  Core Blog
 */
final class Blogs
{
    /**
     * Get blog status codes.
     *
     * Return an array of unstranslated name /code pair.
     *
     * @return array<string,int> All blog status code
     */
    public function getBlogsStatusCodes(): array
    {
        return [
            'online'  => 1,
            'offline' => 0,
            'removed' => -1,
        ];
    }

    /**
     * Get a blogs status code.
     *
     * Returns a blogs status code given to a unstranslated name.
     *
     * @param string $name    The blog status name
     * @param int    $default The value returned if name not exists
     *
     * @return null|int The blog status name
     */
    public function getBlogsStatusCode(string $name, int $default = null): ?int
    {
        return match ($name) {
            'online'  => 1,
            'offline' => 0,
            'remove'  => -1,
            default   => $default,
        };
    }

    /**
     * Get all blog status name.
     *
     * @return array<int,string> An array of available blog status codes and names
     */
    public function getBlogsStatusNames(): array
    {
        return [
            1  => __('online'),
            0  => __('offline'),
            -1 => __('removed'),
        ];
    }

    /**
     * Get a blogs status name.
     *
     * Returns a blogs status name given to a code. This is intended to be
     * human-readable and will be translated, so never use it for tests.
     *
     * @param int    $code    The blog status code
     * @param string $default The value returned if code not exists
     *
     * @return null|string The blog status name
     */
    public function getBlogsStatusName(int $code, string $default = null): ?string
    {
        return match ($code) {
            1       => __('online'),
            0       => __('offline'),
            -1      => __('removed'),
            default => $default,
        };
    }

    /**
     * Get all blog permissions (users) as an array which looks like:.
     *
     * - [user_id]
     * - [name] => User name
     * - [firstname] => User firstname
     * - [displayname] => User displayname
     * - [super] => (true|false) super admin
     * - [p]
     * - [permission] => true
     * - ...
     *
     * @param string $id    The blog ID
     * @param bool   $super Includes super admins in result
     *
     * @return array<int, array> The blog permissions
     */
    public function getBlogPermissions(string $id, bool $super = true): array
    {
        $join = new JoinStatement(__METHOD__);
        $join->from(App::core()->prefix() . 'permissions P');
        $join->on('U.user_id = P.user_id');
        $join->where('blog_id = ' . $join->quote($id));

        $sql = new SelectStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'user U');
        $sql->columns([
            'U.user_id AS user_id',
            'user_super',
            'user_name',
            'user_firstname',
            'user_displayname',
            'user_email',
            'permissions',
        ]);
        $sql->join($join->statement());

        if ($super) {
            $union = new SelectStatement(__METHOD__);
            $union->from(App::core()->prefix() . 'user U');
            $union->columns([
                'U.user_id AS user_id',
                'user_super',
                'user_name',
                'user_firstname',
                'user_displayname',
                'user_email',
                'NULL AS permissions',
            ]);
            $union->where('user_super = 1');

            $sql->sql('UNION ' . $union->statement());
        }

        $record = $sql->select();

        $res = [];

        while ($record->fetch()) {
            $res[$record->f('user_id')] = [
                'name'        => $record->f('user_name'),
                'firstname'   => $record->f('user_firstname'),
                'displayname' => $record->f('user_displayname'),
                'email'       => $record->f('user_email'),
                'super'       => (bool) $record->f('user_super'),
                'p'           => App::core()->user()->parsePermissions($record->f('permissions')),
            ];
        }

        return $res;
    }

    /**
     * Get the blog.
     *
     * @param string $id The blog ID
     *
     * @return null|Record The blog
     */
    public function getBlog(string $id): ?Record
    {
        $param = new Param();
        $param->set('blog_id', $id);

        $record = $this->getBlogs(param: $param);

        return $record->isEmpty() ? null : $record;
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
        $query  = $sql ? clone $sql : new SelectStatement(__METHOD__);

        // --BEHAVIOR-- coreBlogBeforeCountBlogs, Param, SelectStatement
        App::core()->behavior()->call('coreBlogBeforeCountBlogs', $params, $query);

        $params->unset('order');
        $params->unset('limit');

        $query->column($query->count('B.blog_id'));

        $record = $this->queryBlogsTable(param: $params, sql: $query)->fInt();

        // --BEHAVIOR-- coreBlogAfterCountBlogs, Record, Param, SelectStatement
        App::core()->behavior()->call('coreBlogAfterCountBlogs', $record, $params, $query);

        return $record;
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
        $query  = $sql ? clone $sql : new SelectStatement(__METHOD__);

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

        return $this->queryBlogsTable(param: $params, sql: $query);
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
            $join = new JoinStatement(__METHOD__);
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
    public function addBlog(Cursor $cursor): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        $this->getBlogCursor(cursor: $cursor);

        $cursor->setField('blog_creadt', Clock::database());
        $cursor->setField('blog_upddt', Clock::database());
        $cursor->setField('blog_uid', md5(uniqid()));

        $cursor->insert();
    }

    /**
     * Update a given blog.
     *
     * @param string $id     The blog ID
     * @param Cursor $cursor The blog cursor
     */
    public function updBlog(string $id, Cursor $cursor): void
    {
        $this->getBlogCursor(cursor: $cursor);

        $cursor->setField('blog_upddt', Clock::database());

        $cursor->update("WHERE blog_id = '" . App::core()->con()->escape($id) . "'");
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
        if (null !== $cursor->getField('blog_id') && !preg_match('/^[A-Za-z0-9._-]{2,}$/', (string) $cursor->getField('blog_id')) || !$cursor->getField('blog_id')) {
            throw new InvalidValueFormat(__('Blog ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (null !== $cursor->getField('blog_name') && '' == $cursor->getField('blog_name') || !$cursor->getField('blog_name')) {
            throw new MissingOrEmptyValue(__('No blog name'));
        }

        if (null !== $cursor->getField('blog_url') && '' == $cursor->getField('blog_url') || !$cursor->getField('blog_url')) {
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
    public function updBlogsStatus(Strings $ids, int $status): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        $sql = new UpdateStatement(__METHOD__);
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
    public function delBlogs(Strings $ids): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        // Do not delete current blog
        if ($ids->exists(App::core()->blog()->id)) {
            $ids->remove(App::core()->blog()->id);
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'blog');
        $sql->where('blog_id' . $sql->in($ids->dump()));
        $sql->delete();
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
        $sql = new SelectStatement(__METHOD__);
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
        $sql = new SelectStatement(__METHOD__);
        $sql->column($sql->count('post_id'));
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('blog_id = ' . $sql->quote($id));

        if ($type) {
            $sql->and('post_type = ' . $sql->quote($type));
        }

        return $sql->select()->fInt();
    }
}
