<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Mapper;

// Dotclear\Helper\Mapper\ContentGroups

/**
 * Stack of items groups helper.
 *
 * @ingroup  Helper Stack
 */
final class ContentGroups
{
    /**
     * @var array<string,ContentItems> The groups of items
     */
    private $groups = [];

    /**
     * Add a group of items.
     *
     * @param ContentItems $items The items group
     */
    public function addGroup(ContentItems $items): void
    {
        $this->groups[$items->id] = $items;
    }

    /**
     * Get a group of items.
     *
     * @param string $id The group ID
     *
     * @return null|ContentItems The items
     */
    public function getGroup(string $id): ?ContentItems
    {
        return $this->groups[$id] ?? null;
    }

    /**
     * Get all items groups.
     *
     * @return array<string,ContentItems> The groups of items
     */
    public function dumpGroups(): array
    {
        return $this->groups;
    }
}
