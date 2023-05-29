<?php
/**
 * @brief User blogs permissions stack core class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

class UserBlogsPermissions
{
    /** @var array<string,UserBlogPermissions>  The user blogs permisions stack */
    private array $stack = [];

    /**
     * Add user blog permissions.
     *
     * @param   UserBlogPermissions     $descriptor     The user blog permissions description
     */
    public function add(UserBlogPermissions $descriptor): void
    {
        $this->stack[$descriptor->id] = $descriptor;
    }

    /**
     * Get user blog permissions.
     *
     * @param   string  $id     The blog ID
     *
     * @return  UserBlogPermissions
     */
    public function get(string $id): UserBlogPermissions
    {
        return $this->has($id) ? $this->stack[$id] : new UserBlogPermissions(
            id:   $id,
            name: '',
            url:  '',
            p:    []
        );
    }

    /**
     * Check if a blog is set.
     *
     * @param   string  $id     The blog ID
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
     * @return  int     Number of blogs on which user has permissions
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Get user blogs permissions.
     *
     * @return  array<string,UserBlogPermissions>  The user blogs permisions stack
     */
    public function dump(): array
    {
        return $this->stack;
    }
}
