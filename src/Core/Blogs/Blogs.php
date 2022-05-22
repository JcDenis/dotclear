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
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;

/**
 * Blogs handling methods.
 *
 * @ingroup  Core Blog
 */
final class Blogs
{
    /**
     * Get all blog status.
     *
     * @return array<int, string> An array of available blog status codes and names
     */
    public function getAllBlogStatus(): array
    {
        return [
            1  => __('online'),
            0  => __('offline'),
            -1 => __('removed'),
        ];
    }

    /**
     * Get blog status.
     *
     * Returns a blog status name given to a code. This is intended to be
     * human-readable and will be translated, so never use it for tests.
     * If status code does not exist, returns <i>offline</i>.
     *
     * @param int $status_code Status code
     *
     * @return string the blog status name
     */
    public function getBlogStatus(int $status_code): string
    {
        $all = $this->getAllBlogStatus();

        return $all[$status_code] ?? $all[0];
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
     * @param string $blog_id    The blog identifier
     * @param bool   $with_super Includes super admins in result
     *
     * @return array<int, array> The blog permissions
     */
    public function getBlogPermissions(string $blog_id, bool $with_super = true): array
    {
        $strReq = 'SELECT U.user_id AS user_id, user_super, user_name, user_firstname, ' .
        'user_displayname, user_email, permissions ' .
        'FROM ' . App::core()->prefix() . 'user U ' .
        'JOIN ' . App::core()->prefix() . 'permissions P ON U.user_id = P.user_id ' .
        "WHERE blog_id = '" . App::core()->con()->escape($blog_id) . "' ";

        if ($with_super) {
            $strReq .= 'UNION ' .
            'SELECT U.user_id AS user_id, user_super, user_name, user_firstname, ' .
            'user_displayname, user_email, NULL AS permissions ' .
            'FROM ' . App::core()->prefix() . 'user U ' .
                'WHERE user_super = 1 ';
        }

        $rs = App::core()->con()->select($strReq);

        $res = [];

        while ($rs->fetch()) {
            $res[$rs->f('user_id')] = [
                'name'        => $rs->f('user_name'),
                'firstname'   => $rs->f('user_firstname'),
                'displayname' => $rs->f('user_displayname'),
                'email'       => $rs->f('user_email'),
                'super'       => (bool) $rs->f('user_super'),
                'p'           => App::core()->user()->parsePermissions($rs->f('permissions')),
            ];
        }

        return $res;
    }

    /**
     * Get the blog.
     *
     * @param string $blog_id The blog identifier
     *
     * @return null|Record The blog
     */
    public function getBlog(string $blog_id): ?Record
    {
        $param = new Param();
        $param->set('blog_id', $blog_id);

        $rs = $this->getBlogs(param: $param);

        return $rs->isEmpty() ? null : $rs;
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
        $param = new BlogsParam($param);
        $param->unset('order');
        $param->unset('limit');

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->column($query->count('B.blog_id'));

        return $this->queryBlogsTable(param: $param, sql: $query)->fInt();
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
        $param = new BlogsParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);

        if (!empty($param->columns())) {
            $query->columns($param->columns());
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
        $query->order($query->escape($param->order('B.blog_id ASC')));

        if (!empty($param->limit())) {
            $query->limit($param->limit());
        }

        return $this->queryBlogsTable(param: $param, sql: $query);
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
        $sql->from(App::core()->prefix() . 'blog B');
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

        $rs = $sql->select();
        $rs->extend(new RsExtBlog());

        return $rs;
    }

    /**
     * Add a new blog.
     *
     * @param cursor $cur The blog cursor
     *
     * @throws CoreException
     */
    public function addBlog(Cursor $cur): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $this->getBlogCursor($cur);

        $cur->setField('blog_creadt', Clock::database());
        $cur->setField('blog_upddt', Clock::database());
        $cur->setField('blog_uid', md5(uniqid()));

        $cur->insert();
    }

    /**
     * Update a given blog.
     *
     * @param string $blog_id The blog identifier
     * @param Cursor $cur     The cursor
     */
    public function updBlog(string $blog_id, Cursor $cur): void
    {
        $this->getBlogCursor($cur);

        $cur->setField('blog_upddt', Clock::database());

        $cur->update("WHERE blog_id = '" . App::core()->con()->escape($blog_id) . "'");
    }

    /**
     * Get the blog cursor.
     *
     * @param Cursor $cur The cursor
     *
     * @throws CoreException
     */
    private function getBlogCursor(Cursor $cur): void
    {
        if (null !== $cur->getField('blog_id') && !preg_match('/^[A-Za-z0-9._-]{2,}$/', (string) $cur->getField('blog_id')) || !$cur->getField('blog_id')) {
            throw new CoreException(__('Blog ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (null !== $cur->getField('blog_name') && '' == $cur->getField('blog_name') || !$cur->getField('blog_name')) {
            throw new CoreException(__('No blog name'));
        }

        if (null !== $cur->getField('blog_url') && '' == $cur->getField('blog_url') || !$cur->getField('blog_url')) {
            throw new CoreException(__('No blog URL'));
        }

        if (null !== $cur->getField('blog_desc')) {
            $cur->setField('blog_desc', Html::clean($cur->getField('blog_desc')));
        }
    }

    /**
     * Remove a given blog.
     *
     * @warning This will remove everything related to the blog (posts,
     * categories, comments, links...)
     *
     * @param string $blog_id The blog identifier
     *
     * @throws CoreException
     */
    public function delBlog(string $blog_id): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $strReq = 'DELETE FROM ' . App::core()->prefix() . 'blog ' .
        "WHERE blog_id = '" . App::core()->con()->escape($blog_id) . "' ";

        App::core()->con()->execute($strReq);
    }

    /**
     * Check if blog exists.
     *
     * @param string $blog_id The blog identifier
     *
     * @return bool True if blog exists, False otherwise
     */
    public function blogExists(string $blog_id): bool
    {
        $strReq = 'SELECT blog_id ' .
        'FROM ' . App::core()->prefix() . 'blog ' .
        "WHERE blog_id = '" . App::core()->con()->escape($blog_id) . "' ";

        $rs = App::core()->con()->select($strReq);

        return !$rs->isEmpty();
    }

    /**
     * Counts the number of blog posts.
     *
     * @param string      $blog_id   The blog identifier
     * @param null|string $post_type The post type
     *
     * @return int Number of blog posts
     */
    public function countBlogPosts(string $blog_id, ?string $post_type = null): int
    {
        $strReq = 'SELECT COUNT(post_id) ' .
        'FROM ' . App::core()->prefix() . 'post ' .
        "WHERE blog_id = '" . App::core()->con()->escape($blog_id) . "' ";

        if ($post_type) {
            $strReq .= "AND post_type = '" . App::core()->con()->escape($post_type) . "' ";
        }

        return App::core()->con()->select($strReq)->fInt();
    }
}
