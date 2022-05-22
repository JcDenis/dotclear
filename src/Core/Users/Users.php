<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Users;

// Dotclear\Core\Users\Users
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtUser;
use Dotclear\Core\Blog\Blog;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Clock;

/**
 * Users handling methods.
 *
 * @ingroup  Core User
 */
final class Users
{
    /**
     * Gets the user by its ID.
     *
     * @param string $user_id The identifier
     *
     * @return Record the user
     */
    public function getUser(string $user_id): Record
    {
        $param = new Param();
        $param->set('user_id', $user_id);

        return $this->getUsers(param: $param);
    }

    /**
     * Retrieve Users count.
     *
     * @see UsersParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The users count
     */
    public function countUsers(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $param = new UsersParam($param);
        $param->unset('order');
        $param->unset('limit');

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->column($query->count('U.user_id'));

        return $this->queryUsersTable(param: $param, sql: $query)->fInt();
    }

    /**
     * Retrieve users.
     *
     * @see UsersParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return Record The users
     */
    public function getUsers(?Param $param = null, ?SelectStatement $sql = null): Record
    {
        $param = new UsersParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);

        if (!empty($param->columns())) {
            $query->columns($param->columns());
        }

        $join = new JoinStatement(__METHOD__);
        $join->type('LEFT');
        $join->from(App::core()->prefix() . 'post P');
        $join->on('U.user_id = P.user_id');
        $query->join($join->statement());

        $query->columns([
            'U.user_id',
            'user_super',
            'user_status',
            'user_pwd',
            'user_change_pwd',
            'user_name',
            'user_firstname',
            'user_displayname',
            'user_email',
            'user_url',
            'user_desc',
            'user_lang',
            'user_tz',
            'user_post_status',
            'user_options',
            $query->count('P.post_id', 'nb_post'),
        ]);
        $query->group([
            'U.user_id',
            'user_super',
            'user_status',
            'user_pwd',
            'user_change_pwd',
            'user_name',
            'user_firstname',
            'user_displayname',
            'user_email',
            'user_url',
            'user_desc',
            'user_lang',
            'user_tz',
            'user_post_status',
            'user_options',
        ]);

        if (!empty($param->order())) {
            if (preg_match('`^([^. ]+) (?:asc|desc)`i', $param->order(), $matches)) {
                if (in_array($matches[1], ['user_id', 'user_name', 'user_firstname', 'user_displayname'])) {
                    $table_prefix = 'U.';
                } else {
                    $table_prefix = ''; // order = nb_post (asc|desc)
                }
                $query->order($table_prefix . $query->escape($param->order()));
            } else {
                $query->order($query->escape($param->order()));
            }
        } else {
            $query->order('U.user_id ASC');
        }

        if (!empty($param->limit())) {
            $query->limit($param->limit());
        }

        return $this->queryUsersTable(param: $param, sql: $query);
    }

    /**
     * Query user table.
     *
     * @param UsersParam      $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return Record The result
     */
    private function queryUsersTable(UsersParam $param, SelectStatement $sql): Record
    {
        $sql->from(App::core()->prefix() . 'user U', false, true);

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
            $sql->where('NULL IS NULL');
        }

        if (!empty($param->q())) {
            $q = str_replace('*', '%', strtolower($param->q()));
            $sql->and($sql->orGroup([
                $sql->like('LOWER(U.user_id)', $q),
                $sql->like('LOWER(user_name)', $q),
                $sql->like('LOWER(user_firstname)', $q),
            ]));
        }

        if (!empty($param->user_id())) {
            $sql->and('U.user_id = ' . $sql->quote($param->user_id()));
        }

        $rs = $sql->select();
        $rs->extend(new RsExtUser());

        return $rs;
    }

    /**
     * Adds a new user. Takes a cursor as input and returns the new user ID.
     *
     * @param Cursor $cur The user cursor
     *
     * @throws CoreException
     */
    public function addUser(Cursor $cur): string
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        if ('' == $cur->getField('user_id')) {
            throw new CoreException(__('No user ID given'));
        }

        if ('' == $cur->getField('user_pwd')) {
            throw new CoreException(__('No password given'));
        }

        $this->getUserCursor($cur);

        if (null === $cur->getField('user_creadt')) {
            $cur->setField('user_creadt', Clock::database());
        }

        $cur->insert();

        App::core()->user()->afterAddUser($cur);

        return $cur->getField('user_id');
    }

    /**
     * Updates an existing user. Returns the user ID.
     *
     * @param string $user_id The user identifier
     * @param Cursor $cur     The cursor
     *
     * @throws CoreException
     */
    public function updUser(string $user_id, Cursor $cur): string
    {
        $this->getUserCursor($cur);

        if ((null !== $cur->getField('user_id') || App::core()->user()->userID() != $user_id) && !App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $sql = new UpdateStatement(__METHOD__);
        $sql->where('user_id = ' . $sql->quote($user_id));
        $sql->update($cur);

        App::core()->user()->afterUpdUser($user_id, $cur);

        if (null !== $cur->getField('user_id')) {
            $user_id = $cur->getField('user_id');
        }

        // Updating all user's blogs
        $sql = new SelectStatement(__METHOD__);
        $sql->distinct();
        $sql->where('user_id = ' . $sql->quote($user_id));
        $sql->from(App::core()->prefix() . 'post');
        $rs = $sql->select();

        while ($rs->fetch()) {
            $b = new Blog($rs->f('blog_id'));
            $b->triggerBlog();
            unset($b);
        }

        return $user_id;
    }

    /**
     * Deletes a user.
     *
     * @param string $user_id The user identifier
     *
     * @throws CoreException
     */
    public function delUser(string $user_id): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        if (App::core()->user()->userID() == $user_id) {
            return;
        }

        $rs = $this->getUser($user_id);

        if (0 < $rs->f('nb_post')) {
            return;
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->where('user_id = ' . $sql->quote($user_id));
        $sql->from(App::core()->prefix() . 'user');
        $sql->delete();

        App::core()->user()->afterDelUser($user_id);
    }

    /**
     * Determines if user exists.
     *
     * @param string $user_id The identifier
     *
     * @return bool true if user exists, False otherwise
     */
    public function userExists(string $user_id): bool
    {
        $sql = new SelectStatement(__METHOD__);
        $sql->column('user_id');
        $sql->where('user_id = ' . $sql->quote($user_id));
        $sql->from(App::core()->prefix() . 'user');
        $rs = $sql->select();

        return !$rs->isEmpty();
    }

    /**
     * Returns all user permissions as an array which looks like:.
     *
     * - [blog_id]
     * - [name] => Blog name
     * - [url] => Blog URL
     * - [p]
     * - [permission] => true
     * - ...
     *
     * @param string $user_id The user identifier
     *
     * @return array the user permissions
     */
    public function getUserPermissions(string $user_id): array
    {
        $join = new JoinStatement(__METHOD__);
        $join->type('INNER');
        $join->from(App::core()->prefix() . 'blog B');
        $join->on('P.blog_id = B.blog_id');

        $sql = new SelectStatement(__METHOD__);
        $sql->columns([
            'B.blog_id',
            'blog_name',
            'blog_url',
            'permissions',
        ]);
        $sql->from(App::core()->prefix() . 'permissions P');
        $sql->join($join->statement());
        $sql->where('user_id = ' . $sql->quote($user_id));
        $rs = $sql->select();

        $res = [];

        while ($rs->fetch()) {
            $res[$rs->f('blog_id')] = [
                'name' => $rs->f('blog_name'),
                'url'  => $rs->f('blog_url'),
                'p'    => App::core()->user()->parsePermissions($rs->f('permissions')),
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
     * @param string $user_id The user identifier
     * @param array  $perms   The permissions
     *
     * @throws CoreException
     */
    public function setUserPermissions(string $user_id, array $perms): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->where('user_id = ' . $sql->quote($user_id));
        $sql->from(App::core()->prefix() . 'permissions');
        $sql->delete();

        foreach ($perms as $blog_id => $p) {
            $this->setUserBlogPermissions($user_id, $blog_id, $p, false);
        }
    }

    /**
     * Sets the user blog permissions.
     *
     * @param string $user_id      The user identifier
     * @param string $blog_id      The blog identifier
     * @param array  $perms        The permissions
     * @param bool   $delete_first Delete permissions first
     *
     * @throws CoreException
     */
    public function setUserBlogPermissions(string $user_id, string $blog_id, array $perms, bool $delete_first = true): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $no_perm = empty($perms);

        if ($delete_first || $no_perm) {
            $sql = new DeleteStatement(__METHOD__);
            $sql->where('blog_id = ' . $sql->quote($blog_id));
            $sql->and('user_id = ' . $sql->quote($user_id));
            $sql->from(App::core()->prefix() . 'permissions');
            $sql->delete();
        }

        if (!$no_perm) {
            $sql = new InsertStatement(__METHOD__);
            $sql->columns([
                'user_id',
                'blog_id',
                'permissions',
            ]);
            $sql->line([[
                $sql->quote($user_id),
                $sql->quote($blog_id),
                $sql->quote('|' . implode('|', array_keys($perms)) . '|'),
            ]]);
            $sql->from(App::core()->prefix() . 'permissions');
            $sql->insert();
        }
    }

    /**
     * Sets the user default blog.
     *
     * This blog will be selected when user log in.
     *
     * @param string $user_id The user identifier
     * @param string $blog_id The blog identifier
     */
    public function setUserDefaultBlog(string $user_id, string $blog_id): void
    {
        $sql = new UpdateStatement(__METHOD__);
        $sql->set('user_default_blog = ' . $sql->quote($blog_id));
        $sql->from(App::core()->prefix() . 'user');
        $sql->update();
    }

    /**
     * Gets the user cursor.
     *
     * @param Cursor $cur The user cursor
     *
     * @throws CoreException
     */
    private function getUserCursor(Cursor $cur): void
    {
        if ($cur->isField('user_id')
            && !preg_match('/^[A-Za-z0-9@._-]{2,}$/', $cur->getField('user_id'))) {
            throw new CoreException(__('User ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (null !== $cur->getField('user_url') && '' != $cur->getField('user_url')) {
            if (!preg_match('|^http(s?)://|', $cur->getField('user_url'))) {
                $cur->setField('user_url', 'http://' . $cur->getField('user_url'));
            }
        }

        if ($cur->isField('user_pwd')) {
            if (6 > strlen($cur->getField('user_pwd'))) {
                throw new CoreException(__('Password must contain at least 6 characters.'));
            }
            $cur->setField('user_pwd', App::core()->user()->crypt($cur->getField('user_pwd')));
        }

        if (null !== $cur->getField('user_lang') && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $cur->getField('user_lang'))) {
            throw new CoreException(__('Invalid user language code'));
        }

        if (null === $cur->getField('user_upddt')) {
            $cur->setField('user_upddt', Clock::database());
        }

        if (null !== $cur->getField('user_options')) {
            $cur->setField('user_options', serialize((array) $cur->getField('user_options')));
        }
    }
}
