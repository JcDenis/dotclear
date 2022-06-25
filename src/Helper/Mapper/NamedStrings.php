<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Mapper;

// Dotclear\Helper\Mapper\NamedStrings

/**
 * Tiny helper to manage array of string values.
 *
 * @ingroup Helper Stack
 */
class NamedStrings
{
    /**
     * @var array<string,string> $stack
     *                           The stack
     */
    protected $stack = [];

    /**
     * Constructor.
     *
     * @param null|array<string,string> $pairs The keys\values pair
     */
    public function __construct(array $pairs = null)
    {
        if (is_iterable($pairs)) {
            array_walk($pairs, fn ($value, $key) => $this->set($key, $value));
        }
    }

    /**
     * Get a key value.
     *
     * If key does not exist, an empty string will be returned.
     *
     * @param string $key The key
     *
     * @return string The key value
     */
    public function get(string $key): string
    {
        return $this->stack[$key] ?? '';
    }

    /**
     * Set a new key value.
     *
     * If key exists, it will be overwrited.
     *
     * @param string $key   The key to add
     * @param string $value The value to add
     */
    public function set(string $key, string $value): void
    {
        $this->stack[$key] = $value;
    }

    /**
     * Concat a key value.
     *
     * If key does not exist, it will be created.
     *
     * @param string $key   The key to modify
     * @param string $value The value to add
     */
    public function concat(string $key, string $value): void
    {
        $this->set($key, $this->get($key) . $value);
    }

    /**
     * Unset a key.
     *
     * @param string $key The key to remove
     */
    public function remove(string $key): void
    {
        unset($this->stack[$key]);
    }

    /**
     * Get stack size (number of values).
     *
     * @return int The size
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Dump array.
     *
     * @return array<string,string> The array of keys\values pair
     */
    public function dump(): array
    {
        return $this->stack;
    }
}
