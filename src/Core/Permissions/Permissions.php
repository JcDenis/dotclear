<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Permissions;

// Dotclear\Core\Permissions\Permissions
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Exception\InsufficientPermissions;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\Mapper\Strings;

/**
 * Permissions handling.
 *
 * @ingroup  Core User Permission
 */
final class Permissions
{
    /**
     * @var array<string,PermissionDescriptor> $permissions
     *                                         The permissions descriptions
     */
    private $types = [];

    /**
     * @var array<string,array> $users
     *                          Loaded users blogs permissions
     */
    private $users = [];

    /**
     * @var array<string,array> $blogs
     *                          Loaded blogs users permissions
     */
    private $blogs = [];

    /**
     * Constructor.
     *
     * Add default permissions
     */
    public function __construct()
    {
        $this->addPermType(new PermissionDescriptor(
            type: 'admin',
            label: __('administrator')
        ));
        $this->addPermType(new PermissionDescriptor(
            type: 'contentadmin',
            label: __('manage all entries and comments')
        ));
        $this->addPermType(new PermissionDescriptor(
            type: 'usage',
            label: __('manage their own entries and comments')
        ));
        $this->addPermType(new PermissionDescriptor(
            type: 'publish',
            label: __('publish entries and comments')
        ));
        $this->addPermType(new PermissionDescriptor(
            type: 'delete',
            label: __('delete entries and comments')
        ));
        $this->addPermType(new PermissionDescriptor(
            type: 'categories',
            label: __('manage categories')
        ));
        $this->addPermType(new PermissionDescriptor(
            type: 'media_admin',
            label: __('manage all media items')
        ));
        $this->addPermType(new PermissionDescriptor(
            type: 'media',
            label: __('manage their own media items')
        ));
    }

    /**
     * Get a permission descriptor.
     *
     * @param string $type The permission type
     *
     * @return PermissionDescriptor The permission descriptor
     */
    public function getPermType(string $type): PermissionDescriptor
    {
        return $this->types[$type] ?? new PermissionDescriptor(
            type: $type,
            label: sprintf(__('[%s] (unreferenced permission)'), $type)
        );
    }

    /**
     * Add a new permission type.
     *
     * @param PermissionDescriptor $descriptor The permission descriptor
     */
    public function addPermType(PermissionDescriptor $descriptor): void
    {
        $this->types[$descriptor->type] = $descriptor;
    }

    /**
     * Get all permissions type descriptor.
     *
     * @return array<string,PermissionDescriptor> The permissions types descriptors
     */
    public function getPermTypes(): array
    {
        return $this->types;
    }

    /**
     * Check if a permission type exists.
     *
     * @param string $type The permission type
     *
     * @return bool True if permission eists
     */
    public function isPermType(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * List all permissions types.
     *
     * @return array<int,string> The permissions types
     */
    public function listPermTypes(): array
    {
        return array_keys($this->types);
    }

    /**
     * Parse permissions from string to Strings array.
     *
     * @param string $level Permissions string
     *
     * @return Strings The parsed permissions
     */
    public function parsePermissions(?string $level): Strings
    {
        $level = (string) $level;
        $level = preg_replace('/^\|/', '', $level);
        $level = preg_replace('/\|$/', '', $level);

        $permissions = new Strings();
        foreach (explode('|', $level) as $v) {
            $permissions->add($v);
        }

        return $permissions;
    }

    /**
     * Get all blog permissions.
     *
     * @param string $id    The blog ID
     * @param bool   $super Includes super admins in result
     *
     * @return array<string,UserPermissionsDescriptor> The blog users permissions
     */
    public function getBlogPermissions(string $id, bool $super = true): array
    {
        if (empty($id)) {
            throw new MissingOrEmptyValue(__('No user ID given'));
        }

        if (isset($this->blogs[$id])) {
            return $this->blogs[$id];
        }

        $this->blogs[$id] = [];

        $join = new JoinStatement();
        $join->from(App::core()->prefix() . 'permissions P');
        $join->on('U.user_id = P.user_id');
        $join->where('blog_id = ' . $join->quote($id));

        $sql = new SelectStatement();
        $sql->from(App::core()->prefix() . 'user U');
        $sql->columns([
            'U.user_id AS user_id',
            'user_super',
            'user_name',
            'user_firstname',
            'user_displayname',
            'user_email',
            'permissions',
        ]);
        $sql->join($join->statement());

        if ($super) {
            $union = new SelectStatement();
            $union->from(App::core()->prefix() . 'user U');
            $union->columns([
                'U.user_id AS user_id',
                'user_super',
                'user_name',
                'user_firstname',
                'user_displayname',
                'user_email',
                'NULL AS permissions',
            ]);
            $union->where('user_super = 1');

            $sql->sql('UNION ' . $union->statement());
        }

        $record = $sql->select();
        while ($record->fetch()) {
            $this->blogs[$id][$record->field('user_id')] = new UserPermissionsDescriptor(
                id: $record->field('user_id'),
                name: $record->field('user_name'),
                firstname: $record->field('user_firstname'),
                displayname: $record->field('user_displayname'),
                email: $record->field('user_email'),
                super: (bool) $record->integer('user_super'),
                perm: $this->parsePermissions($record->field('permissions')),
            );
        }

        return $this->blogs[$id];
    }

    /**
     * Get all user permissions.
     *
     * @param string $id The user ID
     *
     * @return array<string,BlogPermissionsDescriptor> The user blogs permissions
     */
    public function getUserPermissions(string $id): array
    {
        if (empty($id)) {
            throw new MissingOrEmptyValue(__('No user ID given'));
        }

        if (isset($this->users[$id])) {
            return $this->users[$id];
        }

        $this->users[$id] = [];

        $join = new JoinStatement();
        $join->type('INNER');
        $join->from(App::core()->prefix() . 'blog B');
        $join->on('P.blog_id = B.blog_id');

        $sql = new SelectStatement();
        $sql->columns([
            'B.blog_id',
            'blog_name',
            'blog_url',
            'permissions',
        ]);
        $sql->from(App::core()->prefix() . 'permissions P');
        $sql->join($join->statement());
        $sql->where('user_id = ' . $sql->quote($id));

        $record = $sql->select();
        while ($record->fetch()) {
            $this->users[$id][$record->field('blog_id')] = new BlogPermissionsDescriptor(
                id: $record->field('blog_id'),
                name: $record->field('blog_name'),
                url: $record->field('blog_url'),
                perm: $this->parsePermissions($record->field('permissions')),
            );
        }

        return $this->users[$id];
    }

    /**
     * Get user blog permissions.
     *
     * @param string $id   The user ID
     * @param string $blog THe blog ID
     *
     * @throws MissingOrEmptyValue
     *
     * @return Strings The user blog permissions
     */
    public function getUserBlogPermissions(string $id, string $blog): Strings
    {
        if (empty($id)) {
            throw new MissingOrEmptyValue(__('No user ID given'));
        }

        if (empty($blog)) {
            throw new MissingOrEmptyValue(__('No blog ID given'));
        }

        // Get all user blogs permissions
        if (!isset($this->users[$id])) {
            $this->getUserPermissions(id: $id);
        }

        return isset($this->users[$id][$blog]) ? $this->users[$id][$blog]->perm : new Strings();
    }

    /**
     * Set the user blog permissions.
     *
     * @param string  $id          The user ID
     * @param string  $blog        The blog ID
     * @param Strings $permissions The permissions
     *
     * @throws InsufficientPermissions
     * @throws MissingOrEmptyValue
     */
    public function setUserBlogPermissions(string $id, string $blog, Strings $permissions): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not an administrator'));
        }

        if (empty($id)) {
            throw new MissingOrEmptyValue(__('No user ID given'));
        }

        if (empty($blog)) {
            throw new MissingOrEmptyValue(__('No blog ID given'));
        }

        // --BEHAVIOR-- coreBeforeSetUserBlogPermissions, string, string, Strings
        App::core()->behavior()->call('coreBeforeSetUserBlogPermissions', id: $id, blog: $blog, permissions: $permissions);

        // Delete all user blog permissions
        $sql = new DeleteStatement();
        $sql->where('blog_id = ' . $sql->quote($blog));
        $sql->and('user_id = ' . $sql->quote($id));
        $sql->from(App::core()->prefix() . 'permissions');
        $sql->delete();

        if ($permissions->count()) {
            // Set user blog permissions
            $sql = new InsertStatement();
            $sql->columns([
                'user_id',
                'blog_id',
                'permissions',
            ]);
            $sql->line([[
                $sql->quote($id),
                $sql->quote($blog),
                $sql->quote('|' . implode('|', $permissions->dump()) . '|'),
            ]]);
            $sql->from(App::core()->prefix() . 'permissions');
            $sql->insert();
        }

        // Reset instance loaded permissions
        $this->blogs[$id] = $this->users[$id] = [];
    }
}
