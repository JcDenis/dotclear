<?php
/**
 * @class Dotclear\Core\Instance\Blogs
 * @brief Dotclear core blogs managment class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use ArrayObject;

use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Exception\CoreException;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Blogs
{
    /**
     * Gets all blog status.
     *
     * @return  array   An array of available blog status codes and names.
     */
    public function getAllBlogStatus(): array
    {
        return [
            1  => __('online'),
            0  => __('offline'),
            -1 => __('removed')
        ];
    }

    /**
     * Get blog status
     *
     * Returns a blog status name given to a code. This is intended to be
     * human-readable and will be translated, so never use it for tests.
     * If status code does not exist, returns <i>offline</i>.
     *
     * @param   int     $status_code    Status code
     *
     * @return  string  The blog status name.
     */
    public function getBlogStatus(int $status_code): string
    {
        $all = $this->getAllBlogStatus();

        return isset($all[$status_code]) ? $all[$status_code] : $all[0];
    }

    /**
     * Returns all blog permissions (users) as an array which looks like:
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
     * @param   string  $blog_id        The blog identifier
     * @param   bool    $with_super     Includes super admins in result
     *
     * @return  array   The blog permissions.
     */
    public function getBlogPermissions(string $blog_id, bool $with_super = true): array
    {
        $strReq = 'SELECT U.user_id AS user_id, user_super, user_name, user_firstname, ' .
        'user_displayname, user_email, permissions ' .
        'FROM ' . dotclear()->prefix . 'user U ' .
        'JOIN ' . dotclear()->prefix . 'permissions P ON U.user_id = P.user_id ' .
        "WHERE blog_id = '" . dotclear()->con()->escape($blog_id) . "' ";

        if ($with_super) {
            $strReq .= 'UNION ' .
            'SELECT U.user_id AS user_id, user_super, user_name, user_firstname, ' .
            'user_displayname, user_email, NULL AS permissions ' .
            'FROM ' . dotclear()->prefix . 'user U ' .
                'WHERE user_super = 1 ';
        }

        $rs = dotclear()->con()->select($strReq);

        $res = [];

        while ($rs->fetch()) {
            $res[$rs->user_id] = [
                'name'        => $rs->user_name,
                'firstname'   => $rs->user_firstname,
                'displayname' => $rs->user_displayname,
                'email'       => $rs->user_email,
                'super'       => (bool) $rs->user_super,
                'p'           => dotclear()->user()->parsePermissions($rs->permissions)
            ];
        }

        return $res;
    }

    /**
     * Gets the blog.
     *
     * @param   string  $blog_id    The blog identifier
     *
     * @return  Record|null         The blog.
     */
    public function getBlog(string $blog_id): ?Record
    {
        $blog = $this->getBlogs(['blog_id' => $blog_id]);

        if ($blog->isEmpty()) {
            return null;
        }

        return $blog;
    }

    /**
     * Returns a record of blogs.
     *
     * <b>$params</b> is an array with the following optionnal parameters:
     * - <var>blog_id</var>: Blog ID
     * - <var>q</var>: Search string on blog_id, blog_name and blog_url
     * - <var>limit</var>: limit results
     *
     * @param   array|ArrayObject   $params         The parameters
     * @param   bool                $count_only     Count only results
     *
     * @return  Record  The blogs.
     */
    public function getBlogs(array|ArrayObject $params = [], bool $count_only = false): Record
    {
        $join  = ''; // %1$s
        $where = ''; // %2$s

        if ($count_only) {
            $strReq = 'SELECT count(B.blog_id) ' .
            'FROM ' . dotclear()->prefix . 'blog B ' .
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
            $strReq .= 'FROM ' . dotclear()->prefix . 'blog B ' .
                '%1$s ' .
                'WHERE NULL IS NULL ' .
                '%2$s ';

            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . dotclear()->con()->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY B.blog_id ASC ';
            }

            if (!empty($params['limit'])) {
                $strReq .= dotclear()->con()->limit($params['limit']);
            }
        }

        if (dotclear()->user()->userID() && !dotclear()->user()->isSuperAdmin()) {
            $join  = 'INNER JOIN ' . dotclear()->prefix . 'permissions PE ON B.blog_id = PE.blog_id ';
            $where = "AND PE.user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' " .
                "AND (permissions LIKE '%|usage|%' OR permissions LIKE '%|admin|%' OR permissions LIKE '%|contentadmin|%') " .
                'AND blog_status IN (1,0) ';
        } elseif (!dotclear()->user()->userID()) {
            $where = 'AND blog_status IN (1,0) ';
        }

        if (isset($params['blog_status']) && $params['blog_status'] !== '' && dotclear()->user()->isSuperAdmin()) {
            $where .= 'AND blog_status = ' . (int) $params['blog_status'] . ' ';
        }

        if (isset($params['blog_id']) && $params['blog_id'] !== '') {
            if (!is_array($params['blog_id'])) {
                $params['blog_id'] = [$params['blog_id']];
            }
            $where .= 'AND B.blog_id ' . dotclear()->con()->in($params['blog_id']);
        }

        if (!empty($params['q'])) {
            $params['q'] = strtolower(str_replace('*', '%', $params['q']));
            $where .= 'AND (' .
            "LOWER(B.blog_id) LIKE '" . dotclear()->con()->escape($params['q']) . "' " .
            "OR LOWER(B.blog_name) LIKE '" . dotclear()->con()->escape($params['q']) . "' " .
            "OR LOWER(B.blog_url) LIKE '" . dotclear()->con()->escape($params['q']) . "' " .
                ') ';
        }

        $strReq = sprintf($strReq, $join, $where);

        $rs = dotclear()->con()->select($strReq);
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtBlog');

        return $rs;
    }

    /**
     * Adds a new blog.
     *
     * @param   cursor  $cur    The blog cursor
     *
     * @throws  CoreException
     */
    public function addBlog(Cursor $cur): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $this->getBlogCursor($cur);

        $cur->blog_creadt = date('Y-m-d H:i:s');
        $cur->blog_upddt  = date('Y-m-d H:i:s');
        $cur->blog_uid    = md5(uniqid());

        $cur->insert();
    }

    /**
     * Updates a given blog.
     *
     * @param   string  $blog_id    The blog identifier
     * @param   Cursor  $cur        The cursor
     */
    public function updBlog(string $blog_id, Cursor $cur): void
    {
        $this->getBlogCursor($cur);

        $cur->blog_upddt = date('Y-m-d H:i:s');

        $cur->update("WHERE blog_id = '" . dotclear()->con()->escape($blog_id) . "'");
    }

    /**
     * Gets the blog cursor.
     *
     * @param   Cursor  $cur    The cursor
     *
     * @throws  CoreException
     */
    private function getBlogCursor(Cursor $cur): void
    {
        if (($cur->blog_id !== null
            && !preg_match('/^[A-Za-z0-9._-]{2,}$/', $cur->blog_id)) || (!$cur->blog_id)) {
            throw new CoreException(__('Blog ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (($cur->blog_name !== null && $cur->blog_name == '') || (!$cur->blog_name)) {
            throw new CoreException(__('No blog name'));
        }

        if (($cur->blog_url !== null && $cur->blog_url == '') || (!$cur->blog_url)) {
            throw new CoreException(__('No blog URL'));
        }

        if ($cur->blog_desc !== null) {
            $cur->blog_desc = Html::clean($cur->blog_desc);
        }
    }

    /**
     * Removes a given blog.
     * @warning This will remove everything related to the blog (posts,
     * categories, comments, links...)
     *
     * @param   string  $blog_id    The blog identifier
     *
     * @throws  CoreException
     */
    public function delBlog(string $blog_id): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'blog ' .
        "WHERE blog_id = '" . dotclear()->con()->escape($blog_id) . "' ";

        dotclear()->con()->execute($strReq);
    }

    /**
     * Determines if blog exists.
     *
     * @param   string  $blog_id    The blog identifier
     *
     * @return  bool    True if blog exists, False otherwise.
     */
    public function blogExists(string $blog_id): bool
    {
        $strReq = 'SELECT blog_id ' .
        'FROM ' . dotclear()->prefix . 'blog ' .
        "WHERE blog_id = '" . dotclear()->con()->escape($blog_id) . "' ";

        $rs = dotclear()->con()->select($strReq);

        return !$rs->isEmpty();
    }

    /**
     * Counts the number of blog posts.
     *
     * @param   string          $blog_id    The blog identifier
     * @param   string|null     $post_type  The post type
     *
     * @return  int     Number of blog posts.
     */
    public function countBlogPosts(string $blog_id, ?string $post_type = null): int
    {
        $strReq = 'SELECT COUNT(post_id) ' .
        'FROM ' . dotclear()->prefix . 'post ' .
        "WHERE blog_id = '" . dotclear()->con()->escape($blog_id) . "' ";

        if ($post_type) {
            $strReq .= "AND post_type = '" . dotclear()->con()->escape($post_type) . "' ";
        }

        return (int) dotclear()->con()->select($strReq)->f(0);
    }

}
