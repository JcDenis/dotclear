<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Common;

// Dotclear\Plugin\SimpleMenu\Common\SimpleMenuItem

/**
 * SimpleMenu item helper.
 *
 * @ingroup  Plugin SimpleMenu Stack
 */
final class SimpleMenuItem
{
    /**
     * Constructor.
     *
     * @param string $url    The menu URL
     * @param string $label  The menu label
     * @param string $title  The menu title
     * @param string $span   The menu span
     * @param bool   $active Is active
     * @param string $class  The menu class
     */
    public function __construct(
        public string $url,
        public string $label,
        public string $title,
        public string $span,
        public bool $active,
        public string $class,
    ) {
    }
}
