<?php

/**
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Helper\Html\Html;
use Dotclear\Interface\Core\PostTypeInterface;

/**
 * @brief   Posts type descriptor.
 *
 * @since   2.28, a post type is now an object rather than an array
 */
class PostType implements PostTypeInterface
{
    /**
     * The post type name (untranslated).
     *
     * @var     string  $label  $label
     */
    public readonly string $label;

    /**
     * Constructor sets post type properties.
     *
     * @param   string  $type               The post type
     * @param   string  $admin_url          The backend URL representation
     * @param   string  $public_url         The frontend URL representation
     * @param   string  $label              The post type name (untranslated)
     * @param   string  $list_admin_url     The backend URL representation for list of posts
     */
    public function __construct(
        public readonly string $type,
        public readonly string $admin_url,
        public readonly string $public_url,
        string $label = '',
        public readonly string $list_admin_url = '',
        protected readonly string $icon = '',
        protected readonly string $icon_dark = '',
    ) {
        $this->label = $label !== '' ? $label : $type;
    }

    public function get(string $property): string
    {
        return $this->{$property} ?? '';
    }

    public function adminUrl(int|string $post_id, bool $escaped = true, array $params = []): string
    {
        $url = sprintf($this->admin_url, $post_id);

        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params, '', '&');
        }

        return $escaped ? Html::escapeURL($url) : $url;
    }

    public function listAdminUrl(bool $escaped = true, array $params = []): string
    {
        $url = $this->list_admin_url;

        if ($url !== '' && $params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params, '', '&');
        }

        return $escaped ? Html::escapeURL($url) : $url;
    }

    /**
     * Gets post type icon URI.
     */
    public function icon(): string
    {
        return $this->icon;
    }

    /**
     * Gets post type dark icon URI.
     */
    public function iconDark(): string
    {
        return $this->icon_dark;
    }

    public function publicUrl(string $post_url, bool $escaped = true): string
    {
        $url = sprintf($this->public_url, $post_url);

        return $escaped ? Html::escapeURL($url) : $url;
    }

    public function dump(): array
    {
        return get_object_vars($this);
    }
}
