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

class PostTypeDescriptor
{
    /**
     * Constructor sets properties.
     *
     * @param   string  $type       The post type
     * @param   string  $backend    The backend URL representation
     * @param   string  $frontend   The frontend URL representation
     * @param   string  $label      The post type label
     */
    public function __construct(
        public readonly string $type,
        public readonly string $backend,
        public readonly string $frontend,
        public readonly string $label)
    {
    }

    /**
     * Get post type translated name.
     *
     * @return  string  The name
     */
    public function name(): string
    {
        return empty($this->label) ? $this->type : __($this->label);
    }
}