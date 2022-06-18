<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\PostType;

// Dotclear\Core\PostType\PostTypeDescriptor

/**
 * Post type definition.
 *
 * @ingroup  Core Post
 */
final class PostTypeDescriptor
{
    public readonly string $label;

    /**
     * Constructor.
     *
     * @param string $type   The type
     * @param string $admin  The admin url
     * @param string $public The public url
     * @param string $label  The label
     */
    public function __construct(
        public readonly string $type,
        public readonly string $admin,
        public readonly string $public,
        string $label = null,
    ) {
        $this->label = $label ?: $type;
    }
}
