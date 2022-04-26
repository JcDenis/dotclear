<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\MagicTrait

/**
 * Simple no magic trait.
 *
 * This mainly used for code cleaning.
 */
trait MagicTrait
{
    /**
     * Disable magic get method.
     */
    final public function __get(string $_): mixed
    {
        trigger_error('Call to magic __get method: ' . $_, E_USER_ERROR);
    }

    /**
     * Disable magic set method.
     */
    final public function __set(string $_, mixed $__): void
    {
        trigger_error('Call to magic __set method: ' . $_, E_USER_ERROR);
    }

    /**
     * Disable magic isset method.
     */
    final public function __isset(string $_): bool
    {
        trigger_error('Call to magic __isset method: ' . $_, E_USER_ERROR);
    }

    /**
     * Disable magic unset method.
     */
    final public function __unset(string $_): void
    {
        trigger_error('Call to magic __unset method: ' . $_, E_USER_ERROR);
    }

    /**
     * Disable magic call method.
     */
    final public function __call(string $_, array $__): mixed
    {
        trigger_error('Call to magic __call method: ' . $_, E_USER_ERROR);
    }

    /**
     * Disable magic static call method.
     */
    final public static function __callStatic(string $_, array $__): mixed
    {
        trigger_error('Call to magic static __call method: ' . $_, E_USER_ERROR);
    }
}
