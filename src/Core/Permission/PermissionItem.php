<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Permission;

// Dotclear\Core\Permission\PermissionItem
use Dotclear\Exception\InvalidValueFormat;

/**
 * Permission descriptor.
 *
 * @ingroup  Core User Permission
 */
final class PermissionItem
{
    private const PERM_TYPE_SCHEMA = '/^[a-z_]{2,}$/';

    /**
     * Constructor.
     *
     * @param string $type  The permission type
     * @param string $label The permission label
     *
     * @throws InvalidValueFormat
     */
    public function __construct(
        public readonly string $type,
        public readonly string $label,
    ) {
        if (!preg_match(self::PERM_TYPE_SCHEMA, $this->type)) {
            throw new InvalidValueFormat(sprintf(__('%s is not a valid permission type'), $this->type));
        }
    }
}
