<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\ListOption;

// Dotclear\Process\Admin\ListOption\ColumnItem

/**
 * User list column helper.
 *
 * @ingroup  Admin User Preference
 */
final class ColumnItem
{
    /**
     * Constructor.
     *
     * @param string $id     The column id
     * @param string $title  The column title
     * @param bool   $active Column is active (displayed)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        private bool $active = true,
    ) {
    }

    /**
     * Activate this column.
     */
    public function activate(): void
    {
        $this->active = true;
    }

    /**
     * Deactivate this column.
     */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * Check if this column is active.
     *
     * @return bool True if it is active
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}
