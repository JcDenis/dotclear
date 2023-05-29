<?php
/**
 * @brief User blog permissions descriptor core class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

class UserBlogPermissions
{
    /** @var    array<int,string>   The permissions list */
    public readonly array $permissions;

    /**
     * Constructor sets properties.
     *
     * @param   string              $id     The blog ID
     * @param   string              $name   The blog name
     * @param   string              $url    The blog URL
     * @param   array<string,bool>  $p      The user permissions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly array $p
    ) {
        $this->permissions = array_keys($this->p);
    }

    /**
     * check if a permission is set.
     *
     * @param   string  $permission     The permission
     *
     * @return  bool    True on exist
     */
    public function has(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    /**
     * Count user permissions.
     *
     * @return  int     The number of user permissions
     */
    public function count(): int
    {
        return count($this->permissions);
    }

    /**
     * Get array copy of permissions description.
     *
     * @return  array<string,mixed>     The permissions description
     */
    public function dump(): array
    {
        return get_object_vars($this);
    }
}
