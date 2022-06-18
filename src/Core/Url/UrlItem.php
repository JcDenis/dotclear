<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Url;

// Dotclear\Core\Url\UrlItem
use Dotclear\Exception\InvalidValueType;

/**
 * URL handler definition.
 *
 * @ingroup  Core Public Url
 */
final class UrlItem
{
    /**
     * Constructor.
     *
     * @param string   $type     The type
     * @param string   $url      The URL
     * @param string   $scheme   The scheme
     * @param callable $callback The URL handler callback
     */
    public function __construct(
        public readonly string $type,
        public readonly string $url,
        public readonly string $scheme,
        public readonly mixed $callback,
    ) {
        // Check in constructor as we can't assign callable to readonly method arguments
        if (!is_callable($callback)) {
            throw new InvalidValueType(__('Url handler callback must be callable.'));
        }
    }
}
