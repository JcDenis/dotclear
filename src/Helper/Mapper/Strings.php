<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Mapper;

// Dotclear\Helper\Mapper\Strings

/**
 * Tiny helper to manage array of string values.
 *
 * @ingroup Helper Stack
 */
class Strings
{
    /**
     * @var array<int,string> $stack
     *                        The ids stacks
     */
    protected $stack = [];

    /**
     * Constructor.
     *
     * @param mixed  $values  The values
     * @param string $pattern the value matching pattern
     */
    public function __construct(mixed $values = null, public readonly string $pattern = '/^[A-Za-z0-9._-]{2,}$/')
    {
        if (!empty($values)) {
            if (is_iterable($values)) {
                array_walk($values, fn ($value) => $this->add($value));
            } else {
                $this->add($values);
            }
        }
    }

    /**
     * Set a new value.
     *
     * @param mixed $value The value to add
     */
    public function add(mixed $value): void
    {
        if (!empty($value = $this->convert($value))) {
            $this->stack[] = $value;
        }
    }

    /**
     * Unset a value.
     *
     * Remove all occurences of a value.
     *
     * @param mixed $value The value to remove
     */
    public function remove(mixed $value): void
    {
        if (!empty($value = $this->convert($value))) {
            $this->stack = array_diff($this->stack, [$value]);
        }
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
     * Dump array of values.
     *
     * @return array<int,string> The values
     */
    public function dump(): array
    {
        return array_values($this->stack);
    }

    /**
     * Check if a value is set.
     *
     * @param mixed $value The value
     *
     * @return bool True if value exists
     */
    public function exists(mixed $value)
    {
        return in_array($value, $this->stack);
    }

    /**
     * Try to convert a value to string.
     *
     * @param mixed $value The value
     *
     * @return string The converted value
     */
    protected function convert(mixed $value): string
    {
        return strval($value);
        // return preg_match($this->pattern, (string) $value) ? (string) $value : '';
    }
}
