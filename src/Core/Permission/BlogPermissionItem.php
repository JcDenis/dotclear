<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Permission;

// Dotclear\Core\Permission\BlogPermissionItem
use Dotclear\Helper\Mapper\Strings;

/**
 * Blog permissions descriptor.
 *
 * @ingroup  Core User Permission
 */
final class BlogPermissionItem
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
