<?php
/**
 * @brief Users core class
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

class Users
{
    /**
     * Returns user default options in an associative array with setting names in keys.
     *
     * @var  array{edit_size:int,enable_wysiwyg:bool,toolbar_bottom:bool,editor:array<string,string>,post_format:string}
     */
    public const USER_DEFAULT_OPTIONS = [
        'edit_size'      => 24,
        'enable_wysiwyg' => true,
        'toolbar_bottom' => false,
        'editor'         => ['xhtml' => 'dcCKEditor', 'wiki' => 'dcLegacyEditor'],
        'post_format'    => 'xhtml',
    ];

    /**
     * Gets a user.
     *
     * @param   string  $id     The identifier
     *
     * @return  MetaRecord  The user.
     */
    public function get(string $id): MetaRecord
    {
        $params['user_id'] = $id;

        return $this->search($params);
    }

    /**
     * Get users list.
     *
     * <b>$params</b> is an array with the following
     * optionnal parameters:
     *
     * - <var>q</var>: search string (on user_id, user_name, user_firstname)
     * - <var>user_id</var>: user ID
     * - <var>order</var>: ORDER BY clause (default: user_id ASC)
     * - <var>limit</var>: LIMIT clause (should be an array ![limit,offset])
     *
     * @param   array<string,mixed>|ArrayObject     $params         The parameters
     * @param   bool                                $count_only     Count only results
     *
     * @return  MetaRecord  The users.
     */
    public function search(array|ArrayObject $params = [], bool $count_only = false): MetaRecord
    {
        $sql = new SelectStatement();

        if ($count_only) {
            $sql
                ->column($sql->count('U.user_id'))
                ->from($sql->as(dcCore::app()->prefix . dcAuth::USER_TABLE_NAME, 'U'))
                ->where('NULL IS NULL');
        } else {
            $sql
                ->columns([
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
                ->from($sql->as(dcCore::app()->prefix . dcAuth::USER_TABLE_NAME, 'U'));

            if (!empty($params['columns'])) {
                $sql->columns($params['columns']);
            }
            $sql
                ->join(
                    (new JoinStatement())
                        ->left()
                        ->from($sql->as(dcCore::app()->prefix . dcBlog::POST_TABLE_NAME, 'P'))
                        ->on('U.user_id = P.user_id')
                        ->statement()
                )
                ->where('NULL IS NULL');
        }

        if (!empty($params['q'])) {
            $q = $sql->escape(str_replace('*', '%', strtolower($params['q'])));
            $sql->andGroup([
                $sql->or($sql->like('LOWER(U.user_id)', $q)),
                $sql->or($sql->like('LOWER(user_name)', $q)),
                $sql->or($sql->like('LOWER(user_firstname)', $q)),
            ]);
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

        // should never happend
        if (is_null($rs)) {
            $rs = MetaRecord::newFromArray([]);
        }

        $rs->extend('rsExtUser');

        return $rs;
    }

    /**
     * Adds a new user.
     *
     * Takes a Cursor as input and returns the new user ID.
     *
     * @param   Cursor  $cur    The user Cursor
     *
     * @throws  Exception
     *
     * @return  string
     */
    public function add(Cursor $cur): string
    {
        if (!dcCore::app()->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        if ($cur->getField('user_id') == '') {
            throw new Exception(__('No user ID given'));
        }

        if ($cur->getField('user_pwd') == '') {
            throw new Exception(__('No password given'));
        }

        $this->fillUserCursor($cur);

        if ($cur->getField('user_creadt') === null) {
            $cur->setField('user_creadt', date('Y-m-d H:i:s'));
        }

        $cur->insert();

        # --BEHAVIOR-- coreAfterAddUser -- Cursor
        dcCore::app()->behavior->call('coreAfterAddUser', $cur);

        return is_string($cur->getField('user_id')) ? $cur->getField('user_id') : '';
    }

    /**
     * Updates an existing user.
     *
     * @param   string  $id     The user identifier
     * @param   Cursor  $cur    The Cursor
     *
     * @throws  Exception
     *
     * @return  string  The user ID
     */
    public function update(string $id, Cursor $cur): string
    {
        $this->fillUserCursor($cur);

        if (($cur->getField('user_id') !== null || $id != dcCore::app()->auth->userID()) && !dcCore::app()->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        $sql = new UpdateStatement();
        $sql->where('user_id = ' . $sql->quote($id));

        $sql->update($cur);

        # --BEHAVIOR-- coreAfterUpdUser -- Cursor
        dcCore::app()->behavior->call('coreAfterUpdUser', $cur);

        if ($cur->getField('user_id') !== null) {
            $id = is_string($cur->getField('user_id')) ? $cur->getField('user_id') : '';
        }

        # Updating all user's blogs
        $sql = new SelectStatement();
        $sql
            ->distinct()
            ->column('blog_id')
            ->from(dcCore::app()->prefix . dcBlog::POST_TABLE_NAME)
            ->where('user_id = ' . $sql->quote($id));

        $rs = $sql->select();
        if (!is_null($rs)) {
            while ($rs->fetch()) {
                if (is_string($rs->f('blog_id'))) {
                    $b = new dcBlog($rs->f('blog_id'));
                    $b->triggerBlog();
                    unset($b);
                }
            }
        }

        return $id;
    }

    /**
     * Deletes a user.
     *
     * @param   string  $id     The user identifier
     *
     * @throws  Exception
     */
    public function delete(string $id): void
    {
        if (!dcCore::app()->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        if ($id == dcCore::app()->auth->userID()) {
            return;
        }

        $rs = $this->get($id);
        if (is_numeric($rs->f('nb_post')) && (int) $rs->f('nb_post') > 0) {
            return;
        }

        $sql = new DeleteStatement();
        $sql
            ->from(dcCore::app()->prefix . dcAuth::USER_TABLE_NAME)
            ->where('user_id = ' . $sql->quote($id));

        $sql->delete();

        # --BEHAVIOR-- coreAfterDelUser -- string
        dcCore::app()->behavior->call('coreAfterDelUser', $id);
    }

    /**
     * Determines if user exists.
     *
     * @param   string  $id       The identifier
     *
     * @return  bool    True if user exists, False otherwise.
     */
    public function has(string $id): bool
    {
        $sql = new SelectStatement();
        $sql
            ->column('user_id')
            ->from(dcCore::app()->prefix . dcAuth::USER_TABLE_NAME)
            ->where('user_id = ' . $sql->quote($id));

        $rs = $sql->select();

        return is_null($rs) || !$rs->isEmpty();
    }

    /**
     * Returns all user permissions.
     *
     * @param   string  $id     The user identifier
     *
     * @return  UserBlogsPermissions    The user permissions.
     */
    public function getUserPermissions(string $id): UserBlogsPermissions
    {
        $sql = new SelectStatement();
        $sql
            ->columns([
                'B.blog_id',
                'blog_name',
                'blog_url',
                'permissions',
            ])
            ->from($sql->as(dcCore::app()->prefix . dcAuth::PERMISSIONS_TABLE_NAME, 'P'))
            ->join(
                (new JoinStatement())
                ->inner()
                ->from($sql->as(dcCore::app()->prefix . dcBlog::BLOG_TABLE_NAME, 'B'))
                ->on('P.blog_id = B.blog_id')
                ->statement()
            )
            ->where('user_id = ' . $sql->quote($id));

        $rs = $sql->select();

        $res = new UserBlogsPermissions();

        if (!is_null($rs)) {
            while ($rs->fetch()) {
                if (is_string($rs->f('blog_id'))
                    && is_string($rs->f('blog_name'))
                    && is_string($rs->f('blog_url'))
                    && is_string($rs->f('permissions'))
                ) {
                    $res->add(new UserBlogPermissions(
                        id:   $rs->f('blog_id'),
                        name: $rs->f('blog_name'),
                        url:  $rs->f('blog_url'),
                        p:    dcCore::app()->auth->parsePermissions($rs->f('permissions')),
                    ));
                }
            }
        }

        return $res;
    }

    /**
     * Sets user permissions.
     *
     * The <var>$perms</var> array looks like:
     *
     * - [blog_id] => '|perm1|perm2|'
     * - ...
     *
     * @param   string                              $id     The user identifier
     * @param   array<string,array<string,bool>>    $perms  The permissions
     *
     * @throws  Exception
     */
    public function setUserPermissions(string $id, array $perms): void
    {
        if (!dcCore::app()->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        $sql = new DeleteStatement();
        $sql
            ->from(dcCore::app()->prefix . dcAuth::PERMISSIONS_TABLE_NAME)
            ->where('user_id = ' . $sql->quote($id));

        $sql->delete();

        foreach ($perms as $blog_id => $p) {
            $this->setUserBlogPermissions($id, $blog_id, $p, false);
        }
    }

    /**
     * Sets the user blog permissions.
     *
     * @param   string                  $id             The user identifier
     * @param   string                  $blog_id        The blog identifier
     * @param   array<string,mixed>     $perms          The permissions
     * @param   bool                    $delete_first   Delete permissions first
     *
     * @throws  Exception
     */
    public function setUserBlogPermissions(string $id, string $blog_id, array $perms, bool $delete_first = true): void
    {
        if (!dcCore::app()->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        $no_perm = empty($perms);

        $perms = '|' . implode('|', array_keys($perms)) . '|';

        $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . dcAuth::PERMISSIONS_TABLE_NAME);

        $cur->setField('user_id', $id);
        $cur->setField('blog_id', $blog_id);
        $cur->setField('permissions', $perms);

        if ($delete_first || $no_perm) {
            $sql = new DeleteStatement();
            $sql
                ->from(dcCore::app()->prefix . dcAuth::PERMISSIONS_TABLE_NAME)
                ->where('blog_id = ' . $sql->quote($blog_id))
                ->and('user_id = ' . $sql->quote($id));

            $sql->delete();
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
     * @param   string  $id         The user identifier
     * @param   string  $blog_id    The blog identifier
     */
    public function setUserDefaultBlog(string $id, string $blog_id): void
    {
        $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . dcAuth::USER_TABLE_NAME);

        $cur->setField('user_default_blog', $blog_id);

        $sql = new UpdateStatement();
        $sql->where('user_id = ' . $sql->quote($id));

        $sql->update($cur);
    }

    /**
     * Removes users default blogs.
     *
     * @param   array<int,string>   $ids    The blogs to remove
     */
    public function removeUsersDefaultBlogs(array $ids): void
    {
        $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . dcAuth::USER_TABLE_NAME);

        $cur->setField('user_default_blog', null);

        $sql = new UpdateStatement();
        $sql->where('user_default_blog' . $sql->in($ids));

        $sql->update($cur);
    }

    /**
     * Fills the user Cursor.
     *
     * @param   Cursor  $cur    The user Cursor
     *
     * @throws  Exception
     */
    private function fillUserCursor(Cursor $cur): void
    {
        if ($cur->isField('user_id') && is_string($cur->getField('user_id'))
            && !preg_match('/^[A-Za-z0-9@._-]{2,}$/', (string) $cur->getField('user_id'))) {
            throw new Exception(__('User ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (is_string($cur->getField('user_url')) && !empty($cur->getField('user_url'))) {
            if (!preg_match('|^https?://|', $cur->getField('user_url'))) {
                $cur->setField('user_url', 'http://' . $cur->getField('user_url'));
            }
        }

        if ($cur->isField('user_pwd') && is_string($cur->getField('user_pwd'))) {
            if (strlen($cur->getField('user_pwd')) < 6) {
                throw new Exception(__('Password must contain at least 6 characters.'));
            }
            $cur->setField('user_pwd', dcCore::app()->auth->crypt($cur->getField('user_pwd')));
        }

        if (is_string($cur->getField('user_lang')) && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $cur->getField('user_lang'))) {
            throw new Exception(__('Invalid user language code'));
        }

        if ($cur->getField('user_upddt') === null) {
            $cur->setField('user_upddt', date('Y-m-d H:i:s'));
        }

        if (is_array($cur->getField('user_options'))) { //ArrayObject?
            $cur->setField('user_options', serialize($cur->getField('user_options')));
        }
    }
}