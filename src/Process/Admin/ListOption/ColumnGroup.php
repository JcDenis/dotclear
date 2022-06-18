<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\ListOption;

// Dotclear\Process\Admin\ListOption\ColumnGroup

/**
 * User list columns group helper.
 *
 * @ingroup  Admin User Preference
 */
final class ColumnGroup
{
    /**
     * @var array<string,ColumnItem> $stack
     *                               The columns group items
     */
    private $stack;

    /**
     * Constructor.
     *
     * @param string $id    The columns group ID
     * @param string $title The columns group title
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
    ) {
    }

    /**
     * Add a column.
     *
     * @param ColumnItem $item The column instance to add
     */
    public function addItem(ColumnItem $item): void
    {
        $this->stack[$item->id] = $item;
    }

    /**
     * Get a column.
     *
     * @param string $id The column ID
     *
     * @return null|ColumnItem The column instance
     */
    public function getItem(string $id): ?ColumnItem
    {
        return $this->stack[$id] ?? null;
    }

    /**
     * Get columns.
     *
     * @return array<string,ColumnItem> The columns instances
     */
    public function getItems(): array
    {
        return $this->stack;
    }
}
