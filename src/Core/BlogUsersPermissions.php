<?php
/**
 * @brief Blog users permissions stack core class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

class BlogUsersPermissions
{
    /** @var array<string,BlogUserPermissions>  The blog users permisions stack */
    private array $stack = [];

    /**
     * Add blog user permissions.
     *
     * @param   BlogUserPermissions     $descriptor     The blog user permissions description
     */
    public function add(BlogUserPermissions $descriptor): void
    {
        $this->stack[$descriptor->id] = $descriptor;
    }

    /**
     * Get blog user permissions.
     *
     * @param   string  $id     The blog ID
     *
     * @return  BlogUserPermissions
     */
    public function get(string $id): BlogUserPermissions
    {
        return $this->has($id) ? $this->stack[$id] : new BlogUserPermissions(
            id:          $id,
            name:        $id,
            firstname:   $id,
            displayname: $id,
            email:       '',
            super:       false,
            p:           [],
        );
    }

    /**
     * Check if a user is set.
     *
     * @param   string  $id     The user ID
     *
     * @return  bool    True on exist
     */
    public function has(string $id): bool
    {
        return isset($this->stack[$id]);
    }

    /**
     * Count blogs.
     *
     * @return  int     Number of users on which blog has permissions
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Get blog users permissions.
     *
     * @return  array<string,BlogUserPermissions>  The blog users permisions stack
     */
    public function dump(): array
    {
        return $this->stack;
    }
}