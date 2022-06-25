<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Admin;

// Dotclear\Plugin\SimpleMenu\Admin\SimplemenuType

/**
 * SimpleMenu type helper.
 *
 * @ingroup  Plugin SimpleMenu Stack
 */
final class SimpleMenuType
{
    /**
     * Constructor.
     *
     * @param string $id      The type ID
     * @param string $label   The type label
     * @param bool   $stepped Use stepped configuration
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly bool $stepped = false,
    ) {
    }
}
