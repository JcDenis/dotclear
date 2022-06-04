<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\Status

/**
 * Simple representation of a status.
 *
 * @ingroup  Helper Mapper
 */
class Status
{
    /**
     * Constructor.
     *
     * @param int    $code   The status code
     * @param string $id     The status ID
     * @param string $icon   The status icon URL
     * @param string $state  The human readable translated state
     * @param string $action The human readable translated action
     */
    public function __construct(
        public readonly int $code,
        public readonly string $id,
        public readonly string $icon,
        public readonly string $state,
        public readonly string $action = ''
    ) {
    }
}
