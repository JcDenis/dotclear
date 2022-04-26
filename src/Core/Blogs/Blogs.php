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
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtBlog;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Html\Html;

/**
 * Blogs handling methods.
 *
 * @ingroup  Core Blog
 */
class Blogs
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
        'FROM ' . App::core()->prefix . 'user U ' .
        'JOIN ' . App::core()->prefix . 'permissions P ON U.user_id = P.user_id ' .
        "WHERE blog_id = '" . App::core()->con()->escape($blog_id) . "' ";

        if ($with_super) {
            $strReq .= 'UNION ' .
            'SELECT U.user_id AS user_id, user_super, user_name, user_firstname, ' .
            'user_displayname, user_email, NULL AS permissions ' .
            'FROM ' . App::core()->prefix . 'user U ' .
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
        $blog = $this->getBlogs(['blog_id' => $blog_id]);

        return $blog->isEmpty() ? null : $blog;
    }

    /**
     * Get a record of blogs.
     *
     * <b>$params</b> is an array with the following optionnal parameters:
     * - <var>blog_id</var>: Blog ID
     * - <var>q</var>: Search string on blog_id, blog_name and blog_url
     * - <var>limit</var>: limit results
     *
     * @param array|ArrayObject $params     The parameters
     * @param bool              $count_only Count only results
     *
     * @return Record The blogs
     */
    public function getBlogs(array|ArrayObject $params = [], bool $count_only = false): Record
    {
        $join  = ''; // %1$s
        $where = ''; // %2$s

        if ($count_only) {
            $strReq = 'SELECT count(B.blog_id) ' .
            'FROM ' . App::core()->prefix . 'blog B ' .
                '%1$s ' .
                'WHERE NULL IS NULL ' .
                '%2$s ';
        } else {
            $strReq = 'SELECT B.blog_id, blog_uid, blog_url, blog_name, blog_desc, blog_creadt, ' .
                'blog_upddt, blog_status ';
            if (!empty($params['columns'])) {
                $strReq .= ',';
                if (is_array($params['columns'])) {
                    $strReq .= implode(',', $params['columns']);
                } else {
                    $strReq .= $params['columns'];
                }
                $strReq .= ' ';
            }
            $strReq .= 'FROM ' . App::core()->prefix . 'blog B ' .
                '%1$s ' .
                'WHERE NULL IS NULL ' .
                '%2$s ';

            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . App::core()->con()->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY B.blog_id ASC ';
            }

            if (!empty($params['limit'])) {
                $strReq .= App::core()->con()->limit($params['limit']);
            }
        }

        if (App::core()->user()->userID() && !App::core()->user()->isSuperAdmin()) {
            $join  = 'INNER JOIN ' . App::core()->prefix . 'permissions PE ON B.blog_id = PE.blog_id ';
            $where = "AND PE.user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' " .
                "AND (permissions LIKE '%|usage|%' OR permissions LIKE '%|admin|%' OR permissions LIKE '%|contentadmin|%') " .
                'AND blog_status IN (1,0) ';
        } elseif (!App::core()->user()->userID()) {
            $where = 'AND blog_status IN (1,0) ';
        }

        if (isset($params['blog_status']) && '' !== $params['blog_status'] && App::core()->user()->isSuperAdmin()) {
            $where .= 'AND blog_status = ' . (int) $params['blog_status'] . ' ';
        }

        if (isset($params['blog_id']) && '' !== $params['blog_id']) {
            if (!is_array($params['blog_id'])) {
                $params['blog_id'] = [$params['blog_id']];
            }
            $where .= 'AND B.blog_id ' . App::core()->con()->in($params['blog_id']);
        }

        if (!empty($params['q'])) {
            $params['q'] = strtolower(str_replace('*', '%', $params['q']));
            $where .= 'AND (' .
            "LOWER(B.blog_id) LIKE '" . App::core()->con()->escape($params['q']) . "' " .
            "OR LOWER(B.blog_name) LIKE '" . App::core()->con()->escape($params['q']) . "' " .
            "OR LOWER(B.blog_url) LIKE '" . App::core()->con()->escape($params['q']) . "' " .
                ') ';
        }

        $strReq = sprintf($strReq, $join, $where);

        $rs = App::core()->con()->select($strReq);
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

        $cur->setField('blog_creadt', date('Y-m-d H:i:s'));
        $cur->setField('blog_upddt', date('Y-m-d H:i:s'));
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

        $cur->setField('blog_upddt', date('Y-m-d H:i:s'));

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

        $strReq = 'DELETE FROM ' . App::core()->prefix . 'blog ' .
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
        'FROM ' . App::core()->prefix . 'blog ' .
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
        'FROM ' . App::core()->prefix . 'post ' .
        "WHERE blog_id = '" . App::core()->con()->escape($blog_id) . "' ";

        if ($post_type) {
            $strReq .= "AND post_type = '" . App::core()->con()->escape($post_type) . "' ";
        }

        return App::core()->con()->select($strReq)->fInt();
    }
}
