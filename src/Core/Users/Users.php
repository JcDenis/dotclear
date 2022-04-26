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
use ArrayObject;
use Dotclear\Core\RsExt\RsExtUser;
use Dotclear\Core\Blog\Blog;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;

/**
 * Users handling methods.
 *
 * @ingroup  Core User
 */
class Users
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
        return $this->getUsers(['user_id' => $user_id]);
    }

    /**
     * Get users.
     *
     * Returns a users list. <b>$params</b> is an array with the following
     * optionnal parameters:
     *
     * - <var>q</var>: search string (on user_id, user_name, user_firstname)
     * - <var>user_id</var>: user ID
     * - <var>order</var>: ORDER BY clause (default: user_id ASC)
     * - <var>limit</var>: LIMIT clause (should be an array ![limit,offset])
     *
     * @param array|ArrayObject $params     The parameters
     * @param bool              $count_only Count only results
     *
     * @return Record The users
     */
    public function getUsers(array|ArrayObject $params = [], bool $count_only = false): Record
    {
        $sql = new SelectStatement(__METHOD__);

        if ($count_only) {
            $sql->column($sql->count('U.user_id'));
        } else {
            if (!empty($params['columns']) && is_array($params['columns'])) {
                $sql->columns($params['columns']);
            }
            $sql->columns([
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
                $sql->count('P.post_id', 'nb_post'),
            ])
                ->join(
                    JoinStatement::init(__METHOD__)
                        ->type('LEFT')
                        ->from(App::core()->prefix . 'post P')
                        ->on('U.user_id = P.user_id')
                        ->statement()
                )
            ;
        }

        $sql->from(App::core()->prefix . 'user U', false, true);

        if (!empty($params['join'])) {
            $sql->join($params['join']);
        }

        if (!empty($params['from'])) {
            $sql->from($params['from']);
        }

        if (!empty($params['where'])) {
            // Cope with legacy code
            $sql->where($params['where']);
        } else {
            $sql->where('NULL IS NULL');
        }

        if (!empty($params['q'])) {
            $q = str_replace('*', '%', strtolower($params['q']));
            $sql->and($sql->orGroup([
                $sql->like('LOWER(U.user_id)', $q),
                $sql->like('LOWER(user_name)', $q),
                $sql->like('LOWER(user_firstname)', $q),
            ]));
        }

        if (!empty($params['user_id'])) {
            $sql->and('U.user_id = ' . $sql->quote($params['user_id']));
        }

        if (!$count_only) {
            $sql->group([
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

            if (!empty($params['order'])) {
                if (preg_match('`^([^. ]+) (?:asc|desc)`i', $params['order'], $matches)) {
                    if (in_array($matches[1], ['user_id', 'user_name', 'user_firstname', 'user_displayname'])) {
                        $table_prefix = 'U.';
                    } else {
                        $table_prefix = ''; // order = nb_post (asc|desc)
                    }
                    $sql->order($table_prefix . $sql->escape($params['order']));
                } else {
                    $sql->order($sql->escape($params['order']));
                }
            } else {
                $sql->order('U.user_id ASC');
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $sql->limit($params['limit']);
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
            $cur->setField('user_creadt', date('Y-m-d H:i:s'));
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
        $sql
            ->where('user_id = ' . $sql->quote($user_id))
            ->update($cur)
        ;

        App::core()->user()->afterUpdUser($user_id, $cur);

        if (null !== $cur->getField('user_id')) {
            $user_id = $cur->getField('user_id');
        }

        // Updating all user's blogs
        $sql = new SelectStatement(__METHOD__);
        $rs  = $sql
            ->distinct()
            ->where('user_id = ' . $sql->quote($user_id))
            ->from(App::core()->prefix . 'post')
            ->select()
        ;

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
        $sql
            ->where('user_id = ' . $sql->quote($user_id))
            ->from(App::core()->prefix . 'user')
            ->delete()
        ;

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
        $rs  = $sql
            ->column('user_id')
            ->where('user_id = ' . $sql->quote($user_id))
            ->from(App::core()->prefix . 'user')
            ->select()
        ;

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
        $sql = new SelectStatement(__METHOD__);
        $rs  = $sql
            ->columns([
                'B.blog_id',
                'blog_name',
                'blog_url',
                'permissions',
            ])
            ->from(App::core()->prefix . 'permissions P')
            ->join(
                JoinStatement::init(__METHOD__)
                    ->type('INNER')
                    ->from(App::core()->prefix . 'blog B')
                    ->on('P.blog_id = B.blog_id')
                    ->statement()
            )
            ->where('user_id = ' . $sql->quote($user_id))
            ->select()
        ;

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
        $sql
            ->where('user_id = ' . $sql->quote($user_id))
            ->from(App::core()->prefix . 'permissions')
            ->delete()
        ;

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
            $sql
                ->where('blog_id = ' . $sql->quote($blog_id))
                ->and('user_id = ' . $sql->quote($user_id))
                ->from(App::core()->prefix . 'permissions')
                ->delete()
            ;
        }

        if (!$no_perm) {
            $sql = new InsertStatement(__METHOD__);
            $sql
                ->columns([
                    'user_id',
                    'blog_id',
                    'permissions',
                ])
                ->line([[
                    $sql->quote($user_id),
                    $sql->quote($blog_id),
                    $sql->quote('|' . implode('|', array_keys($perms)) . '|'),
                ]])
                ->from(App::core()->prefix . 'permissions')
                ->insert()
            ;
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
        $sql
            ->set('user_default_blog = ' . $sql->quote($blog_id))
            ->from(App::core()->prefix . 'user')
            ->update()
        ;
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
            $cur->setField('user_upddt', date('Y-m-d H:i:s'));
        }

        if (null !== $cur->getField('user_options')) {
            $cur->setField('user_options', serialize((array) $cur->getField('user_options')));
        }
    }
}
