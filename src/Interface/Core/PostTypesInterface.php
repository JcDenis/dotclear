<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Interface\Core;

use Dotclear\Core\PostType;

/**
 * @brief   Post types handler interface.
 *
 * @since   2.28
 */
interface PostTypesInterface
{
    /**
     * Check if post type exists.
     *
     * @param   string  $type   The post type
     *
     * @return  bool    Ture if it exists
     */
    public function exists(string $type): bool;

    /**
     * Get a post type.
     *
     * Magic alias of self::get()
     *
     * @param   string  $type   The post type
     *
     * @return  PostType    The post type descriptor
     */
    public function __get(string $type): PostType;

    /**
     * Get a post type.
     *
     * This always returns a PostType even if not exists,
     * use self::exists() to check if it exists.
     *
     * @param   string  $type   The post type
     *
     * @return  PostType    The post type descriptor
     */
    public function get(string $type): PostType;

    /**
     * Set a post type.
     *
     * If post type exists, it wil be overwritten
     *
     * @param   PostType    $descriptor     The post type descriptor
     *
     * @return  PostTypesInterface  This instance
     */
    public function set(PostType $descriptor): PostTypesInterface;

    /**
     * Get the posts types.
     *
     * @return  array<string,PostType>
     */
    public function dump(): array;

    /**
     * Gets the post admin URL, the old way.
     *
     * @param   string                  $type       The type
     * @param   int|string              $post_id    The post identifier
     * @param   bool                    $escaped    Escape the URL
     * @param   array<string,mixed>     $params     The query string parameters (associative array)
     *
     * @return  string  The post admin URL.
     */
    public function getPostAdminURL(string $type, int|string $post_id, bool $escaped = true, array $params = []): string;

    /**
     * Gets the post public URL, the old way.
     *
     * @param   string  $type   The type
     * @param   string  $post_url   The post URL
     * @param   bool    $escaped    Escape the URL
     *
     * @return  string  The post public URL.
     */
    public function getPostPublicURL(string $type, string $post_url, bool $escaped = true): string;

    /**
     * Sets the post type, the old way.
     *
     * @param   string  $type           The type
     * @param   string  $admin_url      The admin URL
     * @param   string  $public_url     The public URL
     * @param   string  $label          The label
     */
    public function setPostType(string $type, string $admin_url, string $public_url, string $label = ''): void;

    /**
     * Gets the post types, the old way.
     *
     * @return  array<string,array<string,string>>  The post types.
     */
    public function getPostTypes(): array;
}
