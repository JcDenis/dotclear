<?php
/**
 * @class Dotclear\Core\PostType\PostType
 * @brief Dotclear core Post Type class
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\PostType;

use Dotclear\Html\Html;;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class PostType
{
    /** @var array              formaters container */
    private $post_types  = [];

    /**
     * Gets the post admin url.
     *
     * @param   string      $type       The type
     * @param   string|int  $post_id    The post identifier
     * @param   bool        $escaped    Escape the URL
     *
     * @return  string  The post admin url.
     */
    public function getPostAdminURL(string $type, string|int $post_id, bool $escaped = true): string
    {
        if (!isset($this->post_types[$type])) {
            $type = 'post';
        }

        $url = sprintf($this->post_types[$type]['admin_url'], $post_id);

        return $escaped ? Html::escapeURL($url) : $url;
    }

    /**
     * Gets the post public url.
     *
     * @param  string   $type       The type
     * @param  string   $post_url   The post url
     * @param  bool     $escaped    Escape the URL
     *
     * @return string   The post public url.
     */
    public function getPostPublicURL(string $type, string $post_url, bool $escaped = true): string
    {
        if (!isset($this->post_types[$type])) {
            $type = 'post';
        }

        $url = sprintf($this->post_types[$type]['public_url'], $post_url);

        return $escaped ? Html::escapeURL($url) : $url;
    }

    /**
     * Sets the post type.
     *
     * @param   string  $type           The type
     * @param   string  $admin_url      The admin url
     * @param   string  $public_url     The public url
     * @param   string  $label          The label
     */
    public function setPostType(string $type, string $admin_url, string $public_url, string $label = ''): void
    {
        $this->post_types[$type] = [
            'admin_url'  => $admin_url,
            'public_url' => $public_url,
            'label'      => ($label != '' ? $label : $type)
        ];
    }

    /**
     * Gets the post types.
     *
     * @return  array   The post types.
     */
    public function getPostTypes(): array
    {
        return $this->post_types;
    }
}