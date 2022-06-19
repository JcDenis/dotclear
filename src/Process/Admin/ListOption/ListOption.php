<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\ListOption;

// Dotclear\Process\Admin\ListOption\ListOption

/**
 * User list option preference library.
 *
 * Dotclear utility class that provides reuseable user preference
 * across all admin page with lists and filters
 *
 * Accessible from App::core()->listoption()->
 *
 * @ingroup  Admin User Preference
 */
final class ListOption
{
    /**
     * @var Column $column
     *             The lists columns instance
     */
    private $column;

    /**
     * @var Sort $sort
     *           The lists sorts instance
     */
    private $sort;

    /**
     * Get lists columns instance.
     *
     * @return Column The lists columns instance
     */
    public function column(): Column
    {
        if (!($this->column instanceof Column)) {
            $this->column = new Column();
        }

        return $this->column;
    }

    /**
     * Get lists srots instance.
     *
     * @return Sort The lists sorts instance
     */
    public function sort(): Sort
    {
        if (!($this->sort instanceof Sort)) {
            $this->sort = new Sort();
        }

        return $this->sort;
    }
}
