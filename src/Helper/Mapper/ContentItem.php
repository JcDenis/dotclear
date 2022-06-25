<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Mapper;

// Dotclear\Helper\Mapper\ContentItem

/**
 * Stack of items groups helper.
 *
 * @ingroup  Helper Stack
 */
final class ContentItem
{
    /**
     * Constructor.
     *
     * @param string $id      The id
     * @param string $content The content
     */
    public function __construct(
        public readonly string $id,
        public readonly string $content,
    ) {
    }
}
