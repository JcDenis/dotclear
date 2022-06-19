<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\ListOption;

// Dotclear\Process\Admin\ListOption\SortGroup

/**
 * User list sorts group helper.
 *
 * @ingroup  Admin User Preference
 */
final class SortGroup
{
    /**
     * Constructor.
     *
     * @param string                    $id        The group ID
     * @param string                    $title     The group title
     * @param null|array<string,string> $combo     The group combo
     * @param null|string               $sortby    The sort by column name
     * @param null|string               $sortorder The sort order
     * @param null|int                  $sortlimit The sort limit
     * @param null|string               $keyword   The sort limit field title
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?array $combo = null,
        private ?string $sortby = null,
        private ?string $sortorder = null,
        private ?int $sortlimit = null,
        public readonly ?string $keyword = null,
    ) {
    }

    /**
     * Get sort by value.
     *
     * @return null|string The sort by value
     */
    public function getSortBy(): ?string
    {
        return $this->sortby;
    }

    /**
     * Set sort by value.
     *
     * @param string $by The sort by value
     */
    public function setSortBy(string $by)
    {
        $this->sortby = $by;
    }

    /**
     * Get sort order value.
     *
     * @return null|string The sort order value
     */
    public function getSortOrder(): ?string
    {
        return $this->sortorder;
    }

    /**
     * Set sort order value.
     *
     * @param string $order The sort order value
     */
    public function setSortOrder(string $order)
    {
        $this->sortorder = $order;
    }

    /**
     * Get sort limit value.
     *
     * @return null|int The sort limti value
     */
    public function getSortLimit(): ?int
    {
        return $this->sortlimit;
    }

    /**
     * Set sort limit value.
     *
     * @param int $limit The sort limit value
     */
    public function setSortLimit(int $limit)
    {
        $this->sortlimit = $limit;
    }
}
