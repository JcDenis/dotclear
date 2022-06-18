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
final class PostType
{
    /**
     * @var array<string,PostTypeItem> $post_types
     *                                 Posts types descriptors
     */
    private $post_types = [];

    /**
     * Set the post type.
     *
     * @param PostTypeItem $item The post type descriptor
     */
    public function addItem(PostTypeItem $item): void
    {
        $this->post_types[$item->type] = $item;
    }

    /**
     * Dump posts types.
     *
     * @return array<string,PostTypeItem> The posts types descriptors
     */
    public function getItems(): array
    {
        return $this->post_types;
    }

    /**
     * Get the post types.
     *
     * @return array<int,string> The post types
     */
    public function listItems(): array
    {
        return array_keys($this->post_types);
    }

    /**
     * Check if a post type exists.
     *
     * @param string $type The post type
     *
     * @return bool True if it exists
     */
    public function hasItem(string $type): bool
    {
        return array_key_exists($type, $this->post_types);
    }

    /**
     * Get the post admin url.
     *
     * @param string     $type The type
     * @param int|string $id   The post ID
     *
     * @return string The post admin URL
     */
    public function getPostAdminURL(string $type, string|int $id): string
    {
        return sprintf($this->post_types[$this->hasItem($type) ? $type : 'post']->admin, $id);
    }

    /**
     * Get the post public url.
     *
     * @param string $type The type
     * @param string $url  The post URL
     *
     * @return string The post public URL
     */
    public function getPostPublicURL(string $type, string $url): string
    {
        return sprintf($this->post_types[$this->hasItem($type) ? $type : 'post']->public, $url);
    }
}
