<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\ListOption;

// Dotclear\Process\Admin\ListOption\Column
use ArrayObject;
use Dotclear\App;

/**
 * User list columns groups helper.
 *
 * - Column : The columns groups handler
 * - ColumnGroup : A columns group
 * - ColumnItem : A column
 *
 * @ingroup  Admin User Preference
 */
final class Column
{
    /**
     * @var array<string,ColumnGroup> $stack
     *                                The columns groups list
     */
    private $stack = [];

    /**
     * Constructor.
     *
     * Set up columns.
     */
    public function __construct()
    {
        // Set default columns (posts)
        $group = new ColumnGroup(
            id: 'posts',
            title: __('Posts'),
        );
        $group->addItem(new ColumnItem(
            id: 'date',
            title: __('Date'),
        ));
        $group->addItem(new ColumnItem(
            id: 'category',
            title: __('Category'),
        ));
        $group->addItem(new ColumnItem(
            id: 'author',
            title: __('Author'),
        ));
        $group->addItem(new ColumnItem(
            id: 'comments',
            title: __('Comments'),
        ));
        $group->addItem(new ColumnItem(
            id: 'trackbacks',
            title: __('Trackbacks'),
        ));
        $this->addGroup(group: $group);

        // --BEHAVIOR-- adminAfterConstructColumn, Column
        App::core()->behavior('adminAfterConstructColumn')->call(column: $this);

        // Load user settings
        $column = @App::core()->user()->preference()->get('interface')->get('cols');
        if (is_array($column) || $column instanceof ArrayObject) {
            foreach ($column as $column_type => $column_values) {
                foreach ($column_values as $column_id => $column_active) {
                    $item = $this->getGroup(id: $column_type)?->getItem(id: $column_id);
                    if ($item instanceof ColumnItem) {
                        if ($column_active) {
                            $item->activate();
                        } else {
                            $item->deactivate();
                        }
                    }
                }
            }
        }
    }

    /**
     * Add a columns group.
     *
     * @param ColumnGroup $group The columns group instance
     */
    public function addGroup(ColumnGroup $group): void
    {
        $this->stack[$group->id] = $group;
    }

    /**
     * Get a columns group.
     *
     * @param string $id The columns group ID
     *
     * @return null|ColumnGroup The columns group instance or null if not exists
     */
    public function getGroup(string $id): ?ColumnGroup
    {
        return $this->stack[$id] ?? null;
    }

    /**
     * Get all columns groups.
     *
     * @return array<string,ColumnGroup> The columns groups list
     */
    public function getGroups(): array
    {
        return $this->stack;
    }

    /**
     * Get user columns preferences.
     *
     * @param string      $id      The columns group ID
     * @param ArrayObject $columns The columns list
     *
     * @return ArrayObject The user columns
     */
    public function cleanColumns(string $id, ArrayObject $columns): ArrayObject
    {
        foreach ($this->getGroup(id: $id)?->getItems() as $item) {
            if (!$item->isActive()) {
                unset($columns[$item->id]);
            }
        }

        return $columns;
    }
}
