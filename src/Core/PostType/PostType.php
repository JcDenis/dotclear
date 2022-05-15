<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\PostType;

// Dotclear\Core\PostType\PostType

/**
 * Post type handling.
 *
 * @ingroup  Core Post
 */
class PostType
{
    /**
     * @var array<string,array> $post_types
     *                          Formaters container
     */
    private $post_types = [];

    /**
     * Get the post admin url.
     *
     * @param string     $type    The type
     * @param int|string $post_id The post identifier
     *
     * @return string The post admin url
     */
    public function getPostAdminURL(string $type, string|int $post_id): string
    {
        if (!isset($this->post_types[$type])) {
            $type = 'post';
        }

        return sprintf($this->post_types[$type]['admin_url'], $post_id);
    }

    /**
     * Get the post public url.
     *
     * @param string $type     The type
     * @param string $post_url The post url
     *
     * @return string The post public url
     */
    public function getPostPublicURL(string $type, string $post_url): string
    {
        if (!isset($this->post_types[$type])) {
            $type = 'post';
        }

        return sprintf($this->post_types[$type]['public_url'], $post_url);
    }

    /**
     * Set the post type.
     *
     * @param string $type       The type
     * @param string $admin_url  The admin url
     * @param string $public_url The public url
     * @param string $label      The label
     */
    public function setPostType(string $type, string $admin_url, string $public_url, string $label = ''): void
    {
        $this->post_types[$type] = [
            'admin_url'  => $admin_url,
            'public_url' => $public_url,
            'label'      => ('' != $label ? $label : $type),
        ];
    }

    /**
     * Get the post types.
     *
     * @return array<string,array> The post types
     */
    public function getPostTypes(): array
    {
        return $this->post_types;
    }

    /**
     * Check if a post type exists.
     *
     * @param string $post_type The post type
     *
     * @return bool True if it exists
     */
    public function exists(string $post_type): bool
    {
        return array_key_exists($post_type, $this->post_types);
    }
}
