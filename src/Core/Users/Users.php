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
     * Get a user by its ID.
     *
     * @todo protect empty urser_id query
     *
     * @param string $id The user ID
     *
     * @return Record the user
     */
    public function getUser(string $id): Record
    {
        $param = new Param();
        $param->set('user_id', $id);

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
        $params = new UsersParam($param);
        $query  = $sql ? clone $sql : new SelectStatement(__METHOD__);

        $params->unset('order');
        $params->unset('limit');

        $query->column($query->count('U.user_id'));

        return $this->queryUsersTable(param: $params, sql: $query)->fInt();
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
        $params = new UsersParam($param);
        $query  = $sql ? clone $sql : new SelectStatement(__METHOD__);

        if (!empty($params->columns())) {
            $query->columns($params->columns());
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

        if (!empty($params->order())) {
            if (preg_match('`^([^. ]+) (?:asc|desc)`i', $params->order(), $matches)) {
                if (in_array($matches[1], ['user_id', 'user_name', 'user_firstname', 'user_displayname'])) {
                    $table_prefix = 'U.';
                } else {
                    $table_prefix = ''; // order = nb_post (asc|desc)
                }
                $query->order($table_prefix . $query->escape($params->order()));
            } else {
                $query->order($query->escape($params->order()));
            }
        } else {
            $query->order('U.user_id ASC');
        }

        if (!empty($params->limit())) {
            $query->limit($params->limit());
        }

        return $this->queryUsersTable(param: $params, sql: $query);
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

        $record = $sql->select();
        $record->extend(new RsExtUser());

        return $record;
    }

    /**
     * Add a new user. Takes a cursor as input and returns the new user ID.
     *
     * @param Cursor $cursor The user cursor
     *
     * @throws CoreException
     */
    public function addUser(Cursor $cursor): string
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        if ('' == $cursor->getField('user_id')) {
            throw new CoreException(__('No user ID given'));
        }

        if ('' == $cursor->getField('user_pwd')) {
            throw new CoreException(__('No password given'));
        }

        $this->getUserCursor(cursor: $cursor);

        if (null === $cursor->getField('user_creadt')) {
            $cursor->setField('user_creadt', Clock::database());
        }

        $cursor->insert();

        App::core()->user()->afterAddUser($cursor);

        return $cursor->getField('user_id');
    }

    /**
     * Update an existing user. Returns the user ID.
     *
     * @param string $id     The user ID
     * @param Cursor $cursor The cursor
     *
     * @throws CoreException
     */
    public function updUser(string $id, Cursor $cursor): string
    {
        $this->getUserCursor(cursor: $cursor);

        if ((null !== $cursor->getField('user_id') || App::core()->user()->userID() != $id) && !App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $sql = new UpdateStatement(__METHOD__);
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->update($cursor);

        App::core()->user()->afterUpdUser($id, $cursor);

        if (null !== $cursor->getField('user_id')) {
            $id = $cursor->getField('user_id');
        }

        // Updating all user's blogs
        $sql = new SelectStatement(__METHOD__);
        $sql->distinct();
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->from(App::core()->prefix() . 'post');

        $record = $sql->select();
        while ($record->fetch()) {
            $blog = new Blog($record->f('blog_id'));
            $blog->triggerBlog();
            unset($blog);
        }

        return $id;
    }

    /**
     * Delete a user.
     *
     * @param string $id The user ID
     *
     * @throws CoreException
     */
    public function delUser(string $id): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        if (App::core()->user()->userID() == $id) {
            return;
        }

        $record = $this->getUser(id: $id);
        if (0 < $record->f('nb_post')) {
            return;
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->from(App::core()->prefix() . 'user');
        $sql->delete();

        App::core()->user()->afterDelUser($id);
    }

    /**
     * Determine if user exists.
     *
     * @param string $id The user ID
     *
     * @return bool true if user exists, False otherwise
     */
    public function userExists(string $id): bool
    {
        $sql = new SelectStatement(__METHOD__);
        $sql->column('user_id');
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->from(App::core()->prefix() . 'user');

        $record = $sql->select();

        return !$record->isEmpty();
    }

    /**
     * Return all user permissions as an array which looks like:.
     *
     * - [blog_id]
     * - [name] => Blog name
     * - [url] => Blog URL
     * - [p]
     * - [permission] => true
     * - ...
     *
     * @param string $id The user ID
     *
     * @return array the user permissions
     */
    public function getUserPermissions(string $id): array
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
        $sql->where('user_id = ' . $sql->quote($id));

        $res    = [];
        $record = $sql->select();
        while ($record->fetch()) {
            $res[$record->f('blog_id')] = [
                'name' => $record->f('blog_name'),
                'url'  => $record->f('blog_url'),
                'p'    => App::core()->user()->parsePermissions($record->f('permissions')),
            ];
        }

        return $res;
    }

    /**
     * Set user permissions.
     *
     * The <var>$perms</var> array looks like:
     * - [blog_id] => '|perm1|perm2|'
     * - ...
     *
     * @param string $id          The user ID
     * @param array  $permissions The permissions
     *
     * @throws CoreException
     */
    public function setUserPermissions(string $id, array $permissions): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->from(App::core()->prefix() . 'permissions');
        $sql->delete();

        foreach ($permissions as $blog => $p) {
            $this->setUserBlogPermissions(id: $id, blog: $blog, permissions: $p, delete: false);
        }
    }

    /**
     * Set the user blog permissions.
     *
     * @param string $id          The user ID
     * @param string $blog        The blog ID
     * @param array  $permissions The permissions
     * @param bool   $delete      Delete permissions first
     *
     * @throws CoreException
     */
    public function setUserBlogPermissions(string $id, string $blog, array $permissions, bool $delete = true): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $no_perm = empty($permissions);

        if ($delete || $no_perm) {
            $sql = new DeleteStatement(__METHOD__);
            $sql->where('blog_id = ' . $sql->quote($blog));
            $sql->and('user_id = ' . $sql->quote($id));
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
                $sql->quote($id),
                $sql->quote($blog),
                $sql->quote('|' . implode('|', array_keys($permissions)) . '|'),
            ]]);
            $sql->from(App::core()->prefix() . 'permissions');
            $sql->insert();
        }
    }

    /**
     * Set the user default blog.
     *
     * This blog will be selected when user log in.
     *
     * @param string $id   The user ID
     * @param string $blog The blog ID
     */
    public function setUserDefaultBlog(string $id, string $blog): void
    {
        $sql = new UpdateStatement(__METHOD__);
        $sql->set('user_default_blog = ' . $sql->quote($blog));
        $sql->from(App::core()->prefix() . 'user');
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->update();
    }

    /**
     * Get the user cursor.
     *
     * @param Cursor $cursor The user cursor
     *
     * @throws CoreException
     */
    private function getUserCursor(Cursor $cursor): void
    {
        if ($cursor->isField('user_id')
            && !preg_match('/^[A-Za-z0-9@._-]{2,}$/', $cursor->getField('user_id'))) {
            throw new CoreException(__('User ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (null !== $cursor->getField('user_url') && '' != $cursor->getField('user_url')) {
            if (!preg_match('|^http(s?)://|', $cursor->getField('user_url'))) {
                $cursor->setField('user_url', 'http://' . $cursor->getField('user_url'));
            }
        }

        if ($cursor->isField('user_pwd')) {
            if (6 > strlen($cursor->getField('user_pwd'))) {
                throw new CoreException(__('Password must contain at least 6 characters.'));
            }
            $cursor->setField('user_pwd', App::core()->user()->crypt($cursor->getField('user_pwd')));
        }

        if (null !== $cursor->getField('user_lang') && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $cursor->getField('user_lang'))) {
            throw new CoreException(__('Invalid user language code'));
        }

        if (null === $cursor->getField('user_upddt')) {
            $cursor->setField('user_upddt', Clock::database());
        }

        if (null !== $cursor->getField('user_options')) {
            $cursor->setField('user_options', serialize((array) $cursor->getField('user_options')));
        }
    }
}
