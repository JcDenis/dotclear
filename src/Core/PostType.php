<?php
/**
 * @brief Post type core class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Helper\Html\Html;

class PostType
{
    /** @var    string  The default type */
    public const DEFAULT_TYPE = 'post';

    /** @var    string  THe undefined type to return */
    public const UNDEFINE_TYPE = 'undefined';

    /** @var array<string,PostTypeDescriptor>   The posts types stack */
    private array $stack = [];

    /**
     * Gets the post admin url.
     *
     * @param   string      $type       The type
     * @param   int|string  $post_id    The post identifier
     * @param   bool        $escaped    Escape the URL
     *
     * @return  string  The post admin url.
     */
    public function backend(string $type, int|string $post_id, bool $escaped = true): string
    {
        if (!$this->has($type)) {
            $type = self::DEFAULT_TYPE;
        }

        $url = sprintf($this->stack[$type]->backend, $post_id);

        return $escaped ? Html::escapeURL($url) : $url;
    }

    /**
     * Gets the post public url.
     *
     * @param      string  $type      The type
     * @param      string  $post_url  The post url
     * @param      bool    $escaped   Escape the URL
     *
     * @return     string    The post public url.
     */
    public function frontend(string $type, string $post_url, bool $escaped = true): string
    {
        if (!$this->has($type)) {
            $type = self::DEFAULT_TYPE;
        }

        $url = sprintf($this->stack[$type]->frontend, $post_url);

        return $escaped ? Html::escapeURL($url) : $url;
    }

    /**
     * Check if a post type exists.
     *
     * @param   string  $type   The post type
     *
     * @return  bool    True on exist
     */
    public function has(string $type): bool
    {
        return isset($this->stack[$type]);
    }

    /**
     * Get a post type description.
     *
     * @param   string  $type   The post type
     *
     * @return PostTypeDescriptor   The post type desription
     */
    public function get(string $type): PostTypeDescriptor
    {
        return $this->has($type) ? $this->stack[$type] : new PostTypeDescriptor(
            type:     self::UNDEFINE_TYPE,
            backend:  '',
            frontend: '',
            label:    ''
        );
    }

    /**
     * Add a post type.
     *
     * @param   PostTypeDescriptor  $descriptor The post type description
     */
    public function add(PostTypeDescriptor $descriptor): void
    {
        $this->stack[$descriptor->type] = $descriptor;
    }

    /**
     * Sets the post type.
     *
     * @param   string  $type       The type
     * @param   string  $backend    The admin url
     * @param   string  $frontend   The public url
     * @param   string  $label      The label
     */
    public function set(string $type, string $backend, string $frontend, string $label = ''): void
    {
        $this->add(new PostTypeDescriptor(
            type:     $type,
            backend:  $backend,
            frontend: $frontend,
            label:    $label
        ));
    }

    /**
     * Gets the post types.
     *
     * @return  array<string,PostTypeDescriptor>  The post types.
     */
    public function dump(): array
    {
        return $this->stack;
    }
}
