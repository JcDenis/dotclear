<?php
/**
 * @class Dotclear\Core\Users\Users
 * @brief Dotclear core users managment class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Users;

use ArrayObject;

use Dotclear\Core\Blog\Blog;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Exception\CoreException;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Users
{
    /**
     * Gets the user by its ID.
     *
     * @param   string  $user_id    The identifier
     *
     * @return  Record  The user.
     */
    public function getUser(string $user_id): Record
    {
        return $this->getUsers(['user_id' => $user_id]);
    }

    /**
     * Get users
     *
     * Returns a users list. <b>$params</b> is an array with the following
     * optionnal parameters:
     *
     * - <var>q</var>: search string (on user_id, user_name, user_firstname)
     * - <var>user_id</var>: user ID
     * - <var>order</var>: ORDER BY clause (default: user_id ASC)
     * - <var>limit</var>: LIMIT clause (should be an array ![limit,offset])
     *
     * @param   array|ArrayObject   $params         The parameters
     * @param   bool                $count_only     Count only results
     *
     * @return  Record  The users
     */
    public function getUsers(array|ArrayObject $params = [], bool $count_only = false): Record
    {
        if ($count_only) {
            $strReq = 'SELECT count(U.user_id) ' .
            'FROM ' . dotclear()->prefix . 'user U ' .
                'WHERE NULL IS NULL ';
        } else {
            $strReq = 'SELECT U.user_id,user_super,user_status,user_pwd,user_change_pwd,' .
                'user_name,user_firstname,user_displayname,user_email,user_url,' .
                'user_desc, user_lang,user_tz, user_post_status,user_options, ' .
                'count(P.post_id) AS nb_post ';
            if (!empty($params['columns'])) {
                $strReq .= ',';
                if (is_array($params['columns'])) {
                    $strReq .= implode(',', $params['columns']);
                } else {
                    $strReq .= $params['columns'];
                }
                $strReq .= ' ';
            }
            $strReq .= 'FROM ' . dotclear()->prefix . 'user U ' .
            'LEFT JOIN ' . dotclear()->prefix . 'post P ON U.user_id = P.user_id ' .
                'WHERE NULL IS NULL ';
        }

        if (!empty($params['q'])) {
            $q = dotclear()->con()->escape(str_replace('*', '%', strtolower($params['q'])));
            $strReq .= 'AND (' .
                "LOWER(U.user_id) LIKE '" . $q . "' " .
                "OR LOWER(user_name) LIKE '" . $q . "' " .
                "OR LOWER(user_firstname) LIKE '" . $q . "' " .
                ') ';
        }

        if (!empty($params['user_id'])) {
            $strReq .= "AND U.user_id = '" . dotclear()->con()->escape($params['user_id']) . "' ";
        }

        if (!$count_only) {
            $strReq .= 'GROUP BY U.user_id,user_super,user_status,user_pwd,user_change_pwd,' .
                'user_name,user_firstname,user_displayname,user_email,user_url,' .
                'user_desc, user_lang,user_tz,user_post_status,user_options ';

            if (!empty($params['order'])) {
                if (preg_match('`^([^. ]+) (?:asc|desc)`i', $params['order'], $matches)) {
                    if (in_array($matches[1], ['user_id', 'user_name', 'user_firstname', 'user_displayname'])) {
                        $table_prefix = 'U.';
                    } else {
                        $table_prefix = ''; // order = nb_post (asc|desc)
                    }
                    $strReq .= 'ORDER BY ' . $table_prefix . dotclear()->con()->escape($params['order']) . ' ';
                } else {
                    $strReq .= 'ORDER BY ' . dotclear()->con()->escape($params['order']) . ' ';
                }
            } else {
                $strReq .= 'ORDER BY U.user_id ASC ';
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $strReq .= dotclear()->con()->limit($params['limit']);
        }
        $rs = dotclear()->con()->select($strReq);
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtUser');

        return $rs;
    }

    /**
     * Adds a new user. Takes a cursor as input and returns the new user ID.
     *
     * @param   Cursor  $cur    The user cursor
     *
     * @throws  CoreException
     *
     * @return  string
     */
    public function addUser(Cursor $cur): string
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        if ($cur->user_id == '') {
            throw new CoreException(__('No user ID given'));
        }

        if ($cur->user_pwd == '') {
            throw new CoreException(__('No password given'));
        }

        $this->getUserCursor($cur);

        if ($cur->user_creadt === null) {
            $cur->user_creadt = date('Y-m-d H:i:s');
        }

        $cur->insert();

        dotclear()->user()->afterAddUser($cur);

        return $cur->user_id;
    }

    /**
     * Updates an existing user. Returns the user ID.
     *
     * @param   string  $user_id    The user identifier
     * @param   Cursor  $cur        The cursor
     *
     * @throws  CoreException
     *
     * @return  string
     */
    public function updUser(string $user_id, Cursor $cur): string
    {
        $this->getUserCursor($cur);

        if (($cur->user_id !== null || $user_id != dotclear()->user()->userID()) && !dotclear()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $cur->update("WHERE user_id = '" . dotclear()->con()->escape($user_id) . "' ");

        dotclear()->user()->afterUpdUser($user_id, $cur);

        if ($cur->user_id !== null) {
            $user_id = $cur->user_id;
        }

        # Updating all user's blogs
        $rs = dotclear()->con()->select(
            'SELECT DISTINCT(blog_id) FROM ' . dotclear()->prefix . 'post ' .
            "WHERE user_id = '" . dotclear()->con()->escape($user_id) . "' "
        );

        while ($rs->fetch()) {
            $b = new Blog($rs->blog_id);
            $b->triggerBlog();
            unset($b);
        }

        return $user_id;
    }

    /**
     * Deletes a user.
     *
     * @param   string  $user_id    The user identifier
     *
     * @throws  CoreException
     */
    public function delUser(string $user_id): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        if ($user_id == dotclear()->user()->userID()) {
            return;
        }

        $rs = $this->getUser($user_id);

        if ($rs->nb_post > 0) {
            return;
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'user ' .
        "WHERE user_id = '" . dotclear()->con()->escape($user_id) . "' ";

        dotclear()->con()->execute($strReq);

        dotclear()->user()->afterDelUser($user_id);
    }

    /**
     * Determines if user exists.
     *
     * @param   string  $user_id    The identifier
     *
     * @return  bool  True if user exists, False otherwise.
     */
    public function userExists(string $user_id): bool
    {
        $strReq = 'SELECT user_id ' .
        'FROM ' . dotclear()->prefix . 'user ' .
        "WHERE user_id = '" . dotclear()->con()->escape($user_id) . "' ";

        $rs = dotclear()->con()->select($strReq);

        return !$rs->isEmpty();
    }

    /**
     * Returns all user permissions as an array which looks like:
     *
     * - [blog_id]
     * - [name] => Blog name
     * - [url] => Blog URL
     * - [p]
     * - [permission] => true
     * - ...
     *
     * @param   string  $user_id    The user identifier
     *
     * @return  array   The user permissions.
     */
    public function getUserPermissions(string $user_id): array
    {
        $strReq = 'SELECT B.blog_id, blog_name, blog_url, permissions ' .
        'FROM ' . dotclear()->prefix . 'permissions P ' .
        'INNER JOIN ' . dotclear()->prefix . 'blog B ON P.blog_id = B.blog_id ' .
        "WHERE user_id = '" . dotclear()->con()->escape($user_id) . "' ";

        $rs = dotclear()->con()->select($strReq);

        $res = [];

        while ($rs->fetch()) {
            $res[$rs->blog_id] = [
                'name' => $rs->blog_name,
                'url'  => $rs->blog_url,
                'p'    => dotclear()->user()->parsePermissions($rs->permissions)
            ];
        }

        return $res;
    }

    /**
     * Sets user permissions.
     *
     * The <var>$perms</var> array looks like:
     * - [blog_id] => '|perm1|perm2|'
     * - ...
     *
     * @param   string     $user_id     The user identifier
     * @param   array      $perms       The permissions
     *
     * @throws  CoreException
     */
    public function setUserPermissions(string $user_id, array $perms): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'permissions ' .
        "WHERE user_id = '" . dotclear()->con()->escape($user_id) . "' ";

        dotclear()->con()->execute($strReq);

        foreach ($perms as $blog_id => $p) {
            $this->setUserBlogPermissions($user_id, $blog_id, $p, false);
        }
    }

    /**
     * Sets the user blog permissions.
     *
     * @param   string      $user_id        The user identifier
     * @param   string      $blog_id        The blog identifier
     * @param   array       $perms          The permissions
     * @param   bool        $delete_first   Delete permissions first
     *
     * @throws  CoreException
     */
    public function setUserBlogPermissions(string $user_id, string $blog_id, array $perms, bool $delete_first = true): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $no_perm = empty($perms);

        $perms = '|' . implode('|', array_keys($perms)) . '|';

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'permissions');

        $cur->user_id     = $user_id;
        $cur->blog_id     = $blog_id;
        $cur->permissions = $perms;

        if ($delete_first || $no_perm) {
            $strReq = 'DELETE FROM ' . dotclear()->prefix . 'permissions ' .
            "WHERE blog_id = '" . dotclear()->con()->escape($blog_id) . "' " .
            "AND user_id = '" . dotclear()->con()->escape($user_id) . "' ";

            dotclear()->con()->execute($strReq);
        }

        if (!$no_perm) {
            $cur->insert();
        }
    }

    /**
     * Sets the user default blog.
     *
     * This blog will be selected when user log in.
     *
     * @param   string  $user_id    The user identifier
     * @param   string  $blog_id    The blog identifier
     */
    public function setUserDefaultBlog(string $user_id, string $blog_id): void
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'user');

        $cur->user_default_blog = $blog_id;

        $cur->update("WHERE user_id = '" . dotclear()->con()->escape($user_id) . "'");
    }

    /**
     * Gets the user cursor.
     *
     * @param   Cursor  $cur    The user cursor
     *
     * @throws  CoreException
     */
    private function getUserCursor(Cursor $cur): void
    {
        if ($cur->isField('user_id')
            && !preg_match('/^[A-Za-z0-9@._-]{2,}$/', $cur->user_id)) {
            throw new CoreException(__('User ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if ($cur->user_url !== null && $cur->user_url != '') {
            if (!preg_match('|^http(s?)://|', $cur->user_url)) {
                $cur->user_url = 'http://' . $cur->user_url;
            }
        }

        if ($cur->isField('user_pwd')) {
            if (strlen($cur->user_pwd) < 6) {
                throw new CoreException(__('Password must contain at least 6 characters.'));
            }
            $cur->user_pwd = dotclear()->user()->crypt($cur->user_pwd);
        }

        if ($cur->user_lang !== null && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $cur->user_lang)) {
            throw new CoreException(__('Invalid user language code'));
        }

        if ($cur->user_upddt === null) {
            $cur->user_upddt = date('Y-m-d H:i:s');
        }

        if ($cur->user_options !== null) {
            $cur->user_options = serialize((array) $cur->user_options);
        }
    }
}
