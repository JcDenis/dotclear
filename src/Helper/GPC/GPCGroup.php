<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\GPC;

// Dotclear\Helper\GPC\GPCGroup;

/**
 * HTTP GPC parser.
 *
 * @todo Enhance type conversion
 *
 * @ingroup Core Helper Http
 */
final class GPCGroup
{
    /**
     * Constructor.
     *
     * @param array<string,mixed> $stack Array of one of the GPCR group
     */
    public function __construct(private array $stack)
    {
    }

    /**
     * Get original value.
     *
     * @param string $key     The key
     * @param mixed  $default Default value to return if key not exists
     *
     * @return mixed The value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->isset($key) ? $this->stack[$key] : $default;
    }

    /**
     * Get string value.
     *
     * @param string      $key     The key
     * @param null|string $default Default value to return if key not exists
     *
     * @return null|string The value
     */
    public function string(string $key, ?string $default = ''): ?string
    {
        return $this->isset($key) ? (string) $this->stack[$key] : $default;
    }

    /**
     * Get integer value.
     *
     * @param string   $key     The key
     * @param null|int $default Default value to return if key not exists
     *
     * @return null|int The value
     */
    public function int(string $key, ?int $default = 0): ?int
    {
        return $this->isset($key) ? (int) $this->stack[$key] : $default;
    }

    /**
     * Get array value.
     *
     * @param string     $key     The key
     * @param null|array $default Default value to return if key not exists
     *
     * @return null|array The value
     */
    public function array(string $key, ?array $default = []): ?array
    {
        return $this->isset($key) ? (array) $this->stack[$key] : $default;
    }

    /**
     * Check if a value is empty.
     *
     * @param string $key The key
     *
     * @return bool True if the value is empty
     */
    public function empty(string $key): bool
    {
        return empty($this->stack[$key]);
    }

    /**
     * Check if a key exists.
     *
     * @param string $key The key
     *
     * @return bool True if the key is set
     */
    public function isset(string $key): bool
    {
        return array_key_exists($key, $this->stack);
    }

    /**
     * Count entries of this group.
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Dump all entries of this group.
     *
     * @return array<string,mixed>
     */
    public function dump(): array
    {
        return $this->stack;
    }
}
