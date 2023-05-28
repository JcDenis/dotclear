<?php
/**
 * @brief Blogs core class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use ArrayObject;
use dcAuth;
use dcBlog;
use dcCore;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Exception;

class Blogs
{
    /**
     * Gets the blog.
     *
     * @param   string  $id     The blog identifier
     *
     * @return  MetaRecord  The blog.
     */
    public function get(string $id): MetaRecord
    {
        return $this->search(['blog_id' => $id]);
    }

    /**
     * Returns a MetaRecord of blogs.
     *
     * <b>$params</b> is an array with the following optionnal parameters:
     *
     * - <var>blog_id</var>: Blog ID
     * - <var>q</var>: Search string on blog_id, blog_name and blog_url
     * - <var>limit</var>: limit results
     * 
     * @todo    Use sqlStatement
     *
     * @param   array<string,mixed>|ArrayObject     $params         The parameters
     * @param   bool                                $count_only     Count only results
     *
     * @return  MetaRecord  The blogs.
     */
    public function search($params = [], bool $count_only = false): MetaRecord
    {
        $join  = ''; // %1$s
        $where = ''; // %2$s

        if ($count_only) {
            $strReq = 'SELECT count(B.blog_id) ' .
            'FROM ' . dcCore::app()->prefix . dcBlog::BLOG_TABLE_NAME . ' B ' .
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
            $strReq .= 'FROM ' . dcCore::app()->prefix . dcBlog::BLOG_TABLE_NAME . ' B ' .
                '%1$s ' .
                'WHERE NULL IS NULL ' .
                '%2$s ';

            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . dcCore::app()->con->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY B.blog_id ASC ';
            }

            if (!empty($params['limit'])) {
                $strReq .= dcCore::app()->con->limit($params['limit']);
            }
        }

        if (dcCore::app()->auth->userID() && !dcCore::app()->auth->isSuperAdmin()) {
            $join  = 'INNER JOIN ' . dcCore::app()->prefix . dcAuth::PERMISSIONS_TABLE_NAME . ' PE ON B.blog_id = PE.blog_id ';
            $where = "AND PE.user_id = '" . dcCore::app()->con->escape(dcCore::app()->auth->userID()) . "' " .
                "AND (permissions LIKE '%|usage|%' OR permissions LIKE '%|admin|%' OR permissions LIKE '%|contentadmin|%') " .
                'AND blog_status IN (' . (string) dcBlog::BLOG_ONLINE . ',' . (string) dcBlog::BLOG_OFFLINE . ') ';
        } elseif (!dcCore::app()->auth->userID()) {
            $where = 'AND blog_status IN (' . (string) dcBlog::BLOG_ONLINE . ',' . (string) dcBlog::BLOG_OFFLINE . ') ';
        }

        if (isset($params['blog_status']) && $params['blog_status'] !== '' && dcCore::app()->auth->isSuperAdmin()) {
            $where .= 'AND blog_status = ' . (int) $params['blog_status'] . ' ';
        }

        if (isset($params['blog_id']) && $params['blog_id'] !== '') {
            if (!is_array($params['blog_id'])) {
                $params['blog_id'] = [$params['blog_id']];
            }
            $where .= 'AND B.blog_id ' . dcCore::app()->con->in($params['blog_id']);
        }

        if (!empty($params['q'])) {
            $params['q'] = strtolower(str_replace('*', '%', $params['q']));
            $where .= 'AND (' .
            "LOWER(B.blog_id) LIKE '" . dcCore::app()->con->escape($params['q']) . "' " .
            "OR LOWER(B.blog_name) LIKE '" . dcCore::app()->con->escape($params['q']) . "' " .
            "OR LOWER(B.blog_url) LIKE '" . dcCore::app()->con->escape($params['q']) . "' " .
                ') ';
        }

        $strReq = sprintf($strReq, $join, $where);

        return new MetaRecord(dcCore::app()->con->select($strReq));
    }

    /**
     * Adds a new blog.
     *
     * @param   Cursor  $cur    The blog Cursor
     *
     * @throws  Exception
     */
    public function add(Cursor $cur): void
    {
        if (!dcCore::app()->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        dcCore::app()->fillBlogCursor($cur);

        $cur->setField('blog_creadt', date('Y-m-d H:i:s'));
        $cur->setField('blog_upddt', date('Y-m-d H:i:s'));
        $cur->setField('blog_uid', md5(uniqid()));

        $cur->insert();
    }

    /**
     * Updates a given blog.
     *
     * @param   string  $id     The blog identifier
     * @param   Cursor  $cur    The Cursor
     */
    public function update(string $id, Cursor $cur): void
    {
        $this->fillBlogCursor($cur);

        $cur->setField('blog_upddt', date('Y-m-d H:i:s'));

        $cur->update("WHERE blog_id = '" . dcCore::app()->con->escape($id) . "'");
    }

    /**
     * Fills the blog Cursor.
     *
     * @param   Cursor  $cur    The Cursor
     *
     * @throws  Exception
     */
    private function fillBlogCursor(Cursor $cur): void
    {
        if ((is_string($cur->getField('blog_id')) && !preg_match('/^[A-Za-z0-9._-]{2,}$/', $cur->getField('blog_id')))
            || empty($cur->getField('blog_id'))
        ) {
            throw new Exception(__('Blog ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (($cur->getField('blog_name') !== null && $cur->getField('blog_name') == '') || (!$cur->getField('blog_name'))) {
            throw new Exception(__('No blog name'));
        }

        if (($cur->getField('blog_url') !== null && $cur->getField('blog_url') == '') || (!$cur->getField('blog_url'))) {
            throw new Exception(__('No blog URL'));
        }
    }

    /**
     * Removes a given blog.
     *
     * @warning This will remove everything related to the blog (posts,
     * categories, comments, links...)
     *
     * @param   string  $id     The blog identifier
     *
     * @throws  Exception
     */
    public function delete(string $id): void
    {
        if (!dcCore::app()->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        $sql = new DeleteStatement();
        $sql
            ->from(dcCore::app()->prefix . dcBlog::BLOG_TABLE_NAME)
            ->where('blog_id = ' . $sql->quote($id))
            ->delete();
    }

    /**
     * Determines if blog exists.
     *
     * @param   string  $id     The blog identifier
     *
     * @return  bool    True if blog exists, False otherwise.
     */
    public function has(string $id): bool
    {
        $sql = new SelectStatement();
        $rs = $sql
            ->from(dcCore::app()->prefix . dcBlog::BLOG_TABLE_NAME)
            ->where('blog_id = ' . $sql->quote($id))
            ->select();

        return is_null($rs) || !$rs->isEmpty();
    }

    /**
     * Counts the number of blog posts.
     *
     * @param   string  $id     The blog identifier
     * @param   string  $type   The post type
     *
     * @return  int     Number of blog posts.
     */
    public function countPosts(string $id, string $type = null): int
    {
        $sql = new SelectStatement();
        $rs = $sql
            ->from(dcCore::app()->prefix . dcBlog::BLOG_TABLE_NAME)
            ->column($sql->count('post_id'))
            ->where('blog_id = ' . $sql->quote($id));

        if ($type) {
            $sql->and('post_type = ' . $sql->quote($type));
        }

        $rs = $sql->select();

        $res = is_null($rs) ? 0 : $rs->f(0);

        return is_numeric($res) ? (int) $res : 0;
    }

    /**
     * Returns all blog permissions.
     *
     * @param   string  $id             The blog identifier
     * @param   bool    $with_super     Includes super admins in result
     *
     * @return  BlogUsersPermissions    The blog users permissions.
     */
    public function getBlogPermissions(string $id, bool $with_super = true): BlogUsersPermissions
    {
        $sql = new SelectStatement();
        $sql
            ->columns([
                'U.user_id as user_id',
                'user_super',
                'user_name',
                'user_firstname',
                'user_displayname',
                'user_email',
                'permissions',
            ])
            ->from($sql->as(dcCore::app()->prefix . dcAuth::USER_TABLE_NAME, 'U'))
            ->join((new JoinStatement())
                ->from($sql->as(dcCore::app()->prefix . dcAuth::PERMISSIONS_TABLE_NAME, 'P'))
                ->on('U.user_id = P.user_id')
                ->statement())
            ->where('blog_id = ' . $sql->quote($id));

        if ($with_super) {
            $sql->union(
                (new SelectStatement())
                ->columns([
                    'U.user_id as user_id',
                    'user_super',
                    'user_name',
                    'user_firstname',
                    'user_displayname',
                    'user_email',
                    'NULL AS permissions',
                ])
                ->from($sql->as(dcCore::app()->prefix . dcAuth::USER_TABLE_NAME, 'U'))
                ->where('user_super = 1')
                ->statement()
            );
        }

        $rs = $sql->select();

        $res = new BlogUsersPermissions();

        if (!is_null($rs)) {
            while ($rs->fetch()) {
                if (is_string($rs->f('user_id'))
                    && is_string($rs->f('user_name'))
                    && is_string($rs->f('user_firstname'))
                    && is_string($rs->f('user_displayname'))
                    && is_string($rs->f('user_email'))
                    && is_string($rs->f('permissions'))
                ) {
                    $res->add(new BlogUserPermissions(
                        id:          $rs->f('user_id'),
                        name:        $rs->f('user_name'),
                        firstname:   $rs->f('user_firstname'),
                        displayname: $rs->f('user_displayname'),
                        email:       $rs->f('user_email'),
                        super:       !empty($rs->f('user_super')),
                        p:           dcCore::app()->auth->parsePermissions($rs->f('permissions')),
                    ));
                }
            }
        }

        return $res;
    }
}