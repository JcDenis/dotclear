<?php

declare(strict_types=1);

namespace Dotclear\Helper\Stack;

/**
 * @brief       Statuses handler.
 *
 * @since       2.33
 * @package     Dotclear
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
class Statuses
{
	/**
	 * @var    array<int, Status>  $stack
	 */
	protected array $stack = [];

    public function __construct(protected string $column, array $descriptors)
    {
        foreach($descriptors as $descriptor) {
            if ($descriptor instanceof Status) {
                $this->set($descriptor);
            }
        }
    }

    /**
     * Sets a status.
     *
     * @return  bool    False if status alreday exists
     */
    public function set(Status $descriptor): bool
    {
        if ($this->has($descriptor->level()) || $this->has($descriptor->id())) {
            return false;
        }

        $this->stack[] = $descriptor;

        return true;
    }

    /**
     * Checks if a status exists.
     *
     * Search by (string) id or (int) level.
     *
     * @return  bool    True if status exists.
     */
    public function has(int|string $needle): bool
    {
        foreach($this->stack as $descriptor) {
            if (is_int($needle) && $descriptor->level() === $needle
                || is_string($needle) && $descriptor->id() === $needle
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a status level.
     *
     * Search by (string) id.
     *
     * @return  int     The status level or 0.
     */
    public function level(string $needle): int
    {
        foreach($this->stack as $descriptor) {
            if ($descriptor->id() === $needle) {
                return $descriptor->level();
            }
        }

        return 0;
    }

    /**
     * Gets a status id.
     *
     * Search by (int) level.
     *
     * @return  string  The status id or empty string.
     */
    public function id(int $needle): string
    {
        foreach($this->stack as $descriptor) {
            if ($descriptor->level() === $needle) {
                return $descriptor->id();
            }
        }

        return '';
    }

    /**
     * Gets a status name.
     *
     * Search by (string) id or (int) level.
     *
     * @return  string  The status name or empty string.
     */
    public function name(int|string $needle): string
    {
        foreach($this->stack as $descriptor) {
            if (is_int($needle) && $descriptor->level() === $needle
                || is_string($needle) && $descriptor->id() === $needle
            ) {
                return $descriptor->name();
            }
        }

        return '';
    }

    /**
     * Gets status table colone for query.
     *
     * @return  string  The status colone
     */
    public function column(): string
    {
        return $this->column;
    }

    /**
     * Gets statuses descriptors.
     *
     * @return  array<int, Status>  The descriptors.
     */
    public function stack(): array
    {
        return $this->stack;
    }

    /**
     * Gets statuses.
     *
     * @return  array<int, string> The statuses by level/name.
     */
    public function statuses(): array
    {
        $combo = [];
        foreach ($this->stack as $descriptor) {
            $combo[$descriptor->level()] = $descriptor->name();
        }

        return $combo;
    }

    /**
     * Gets statuses combo.
     *
     * Levels are return as string.
     *
     * @return  array<string, string>   The statuses by name/level .
     */
    public function combo(): array
    {
        $combo = [];
        foreach ($this->stack as $descriptor) {
            $combo[$descriptor->name()] = (string) $descriptor->level();
        }

        return $combo;
    }

    /**
     * Gets status form filter.
     *
     * @return  Filter  The user status Filter instance.
     */
    public function filter(): Filter
    {
        return (new Filter('status'))
            ->param($this->column())
            ->title(__('Status:'))
            ->options(['-' => '', ...$this->combo()])
            ->prime(true);
    }
}