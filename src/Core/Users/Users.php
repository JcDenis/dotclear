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
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\InsufficientPermissions;
use Dotclear\Exception\InvalidValueFormat;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Mapper\Strings;

/**
 * Users handling methods.
 *
 * @ingroup  Core User
 */
final class Users
{
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
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeCountUsers, Param, SelectStatement
        App::core()->behavior('coreBeforeCountUsers')->call(param: $params, sql: $query);

        $params->unset('order');
        $params->unset('limit');

        $query->column($query->count('U.user_id'));

        $record = $this->queryUsersTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterCountUsers, Record
        App::core()->behavior('coreAfterCountUsers')->call(record: $record);

        return $record->integer();
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
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeGetUsers, Param, SelectStatement
        App::core()->behavior('coreBeforeGetUsers')->call(param: $params, sql: $query);

        if (!empty($params->columns())) {
            $query->columns($params->columns());
        }

        $join = new JoinStatement();
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

        $record = $this->queryUsersTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterGetUsers, Record
        App::core()->behavior('coreAfterGetUsers')->call(record: $record);

        return $record;
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
     * Create a new user.
     *
     * Takes a cursor as input and returns the new user ID.
     *
     * @param Cursor $cursor The user cursor
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     *
     * @return string The user ID
     */
    public function createUser(Cursor $cursor): string
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        if ('' == $cursor->getField('user_id')) {
            throw new MissingOrEmptyValue(__('No user ID given'));
        }

        if ('' == $cursor->getField('user_pwd')) {
            throw new MissingOrEmptyValue(__('No password given'));
        }

        $this->getUserCursor(cursor: $cursor);

        if (null === $cursor->getField('user_creadt')) {
            $cursor->setField('user_creadt', Clock::database());
        }

        // --BEHAVIOR-- coreBeforeCreateUser, Cursor
        App::core()->behavior('coreBeforeCreateUser')->call(cursor: $cursor);

        $cursor->insert();

        App::core()->user()->afterCreateUser(cursor: $cursor);

        // --BEHAVIOR-- coreAfterCreateUser, Cursor
        App::core()->behavior('coreAfterCreateUser')->call(cursor: $cursor);

        return $cursor->getField('user_id');
    }

    /**
     * Update an existing user. Returns the user ID.
     *
     * @param string $id     The user ID
     * @param Cursor $cursor The cursor
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     *
     * @return string The user ID
     */
    public function updateUser(string $id, Cursor $cursor): string
    {
        $this->getUserCursor(cursor: $cursor);

        if ((null !== $cursor->getField('user_id') || App::core()->user()->userID() != $id) && !App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        if (empty($id)) {
            throw new MissingOrEmptyValue(__('No user ID given'));
        }

        // --BEHAVIOR-- coreBeforeUpdateUser, Cursor, int
        App::core()->behavior('coreBeforeUpdateUser')->call(cursor: $cursor, id: $id);

        $sql = new UpdateStatement();
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->update($cursor);

        App::core()->user()->afterUpdateUser(id: $id, cursor: $cursor);

        // --BEHAVIOR-- coreAfterUpdateUser, Cursor, int
        App::core()->behavior('coreAfterUpdateUser')->call(cursor: $cursor, id: $id);

        // If the user ID is changed
        if (null !== $cursor->getField('user_id')) {
            $id = $cursor->getField('user_id');
        }

        // Updating all user's blogs
        $sql = new SelectStatement();
        $sql->distinct();
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->from(App::core()->prefix() . 'post');

        $record = $sql->select();
        while ($record->fetch()) {
            $blog = new Blog($record->field('blog_id'));
            $blog->triggerBlog();
            unset($blog);
        }

        return $id;
    }

    /**
     * Delete users.
     *
     * @param Strings $ids The users IDs
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     */
    public function deleteUsers(Strings $ids): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No user ID given'));
        }

        // Do not delete current user
        if ($ids->exists(App::core()->user()->userID())) {
            $ids->remove(App::core()->user()->userID());
        }

        if ($ids->count()) {
            // --BEHAVIOR-- coreBeforeDeleteUsers, Strings
            App::core()->behavior('coreBeforeDeleteUsers')->call(ids: $ids);

            $sql = new DeleteStatement();
            $sql->from(App::core()->prefix() . 'user');
            $sql->where('user_id' . $sql->in($ids->dump()));
            $sql->delete();

            foreach ($ids->dump() as $id) {
                App::core()->user()->afterDeleteUser(id: $id);
            }
        }
    }

    /**
     * Determine if user exists.
     *
     * @param string $id The user ID
     *
     * @return bool true if user exists, False otherwise
     */
    public function hasUser(string $id): bool
    {
        $sql = new SelectStatement();
        $sql->column('user_id');
        $sql->where('user_id = ' . $sql->quote($id));
        $sql->from(App::core()->prefix() . 'user');

        $record = $sql->select();

        return !$record->isEmpty();
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
        $sql = new UpdateStatement();
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
     * @throws InvalidValueFormat
     */
    private function getUserCursor(Cursor $cursor): void
    {
        if ($cursor->isField('user_id')
            && !preg_match('/^[A-Za-z0-9@._-]{2,}$/', $cursor->getField('user_id'))) {
            throw new InvalidValueFormat(__('User ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (null !== $cursor->getField('user_url') && '' != $cursor->getField('user_url')) {
            if (!preg_match('|^http(s?)://|', $cursor->getField('user_url'))) {
                $cursor->setField('user_url', 'http://' . $cursor->getField('user_url'));
            }
        }

        if ($cursor->isField('user_pwd')) {
            if (6 > strlen($cursor->getField('user_pwd'))) {
                throw new InvalidValueFormat(__('Password must contain at least 6 characters.'));
            }
            $cursor->setField('user_pwd', App::core()->user()->crypt($cursor->getField('user_pwd')));
        }

        if (null !== $cursor->getField('user_lang') && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $cursor->getField('user_lang'))) {
            throw new InvalidValueFormat(__('Invalid user language code'));
        }

        if (null === $cursor->getField('user_upddt')) {
            $cursor->setField('user_upddt', Clock::database());
        }

        if (null !== $cursor->getField('user_options')) {
            $cursor->setField('user_options', serialize((array) $cursor->getField('user_options')));
        }
    }
}
