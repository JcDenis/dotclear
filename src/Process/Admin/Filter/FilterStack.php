<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter;

// Dotclear\Process\Admin\Filter\FilterStack

/**
 * Simple filters stack.
 *
 * Used to pass filters to behaviors.
 *
 * @ingroup  Admin Filter Stack
 */
class FilterStack
{
    /**
     * @var array<int,Filter> $filters
     *                        The filters stack
     */
    private $filters = [];

    /**
     * Constructor.
     *
     * Usage:
     * $fs = new FilterStack($filter1, $filter2, $filter3);
     * $fs->add($filter4);
     * $filters_array = $fs->dump();
     *
     * @param null|Filter ...$filters The filters
     */
    public function __construct(?Filter ...$filters)
    {
        foreach ($filters as $filter) {
            $this->add($filter);
        }
    }

    /**
     * Add a filter to the satck.
     *
     * Empty filter is no added to the stack
     * ex: In post list, do not show form if there are no categories on a blog
     *
     * @param null|Filter $filter The filter
     */
    public function add(?Filter $filter): void
    {
        if (null !== $filter) {
            $this->filters[] = $filter;
        }
    }

    /**
     * Get filters stack array.
     *
     * @return array<int,Filter> The filters stack array
     */
    public function dump(): array
    {
        return $this->filters;
    }
}
