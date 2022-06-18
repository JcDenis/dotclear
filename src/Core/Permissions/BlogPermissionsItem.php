<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Permissions;

// Dotclear\Core\Permissions\BlogPermissionsDescriptor
use Dotclear\Helper\Mapper\Strings;

/**
 * Blog permissions descriptor.
 *
 * @ingroup  Core User Permission
 */
final class BlogPermissionsDescriptor
{
    /**
     * Constructor.
     *
     * @param string  $id   The blog ID
     * @param string  $name The blog name
     * @param string  $url  The blog URL
     * @param Strings $perm The user blog permissions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly Strings $perm,
    ) {
    }
}
