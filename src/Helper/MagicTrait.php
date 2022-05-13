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
use Dotclear\Exception\InvalidMethodException;

/**
 * Simple no magic trait.
 *
 * Explicit code is better !
 *
 * Only __construct, __destruct
 */
trait MagicTrait
{
    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __call(string $_, array $__): mixed
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public static function __callStatic(string $_, array $__): mixed
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __get(string $_): mixed
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __set(string $_, mixed $__): void
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __isset(string $_): bool
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __unset(string $_): void
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __sleep(): array
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __wakeup(): void
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __serialize(): array
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __unserialize(array $_): void
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __toString(): string
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @param array $_
     *
     * @throws InvalidMethodException
     */
    final public function __invoke(...$_): mixed
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public static function __set_state(array $_): object
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __clone()
    {
        throw new InvalidMethodException();
    }

    /**
     * Disabled method.
     *
     * @throws InvalidMethodException
     */
    final public function __debugInfo(): array
    {
        throw new InvalidMethodException();
    }
}
