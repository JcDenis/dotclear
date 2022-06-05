<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Url;

// Dotclear\Core\Url\UrlDescriptor
use Dotclear\Exception\InvalidValueType;

/**
 * URL handler definition.
 *
 * @ingroup  Core Public Url
 */
final class UrlDescriptor
{
    /**
     * Constructor.
     *
     * @param string   $type           The type
     * @param string   $url            The URL
     * @param string   $representation The representation
     * @param callable $callback       The URL handler callback
     */
    public function __construct(
        public readonly string $type,
        public readonly string $url,
        public readonly string $representation,
        public readonly mixed $callback,
    ) {
        if (!is_callable($callback)) {
            throw new InvalidValueType(__('Url handler callback must be callable.'));
        }
    }
}
