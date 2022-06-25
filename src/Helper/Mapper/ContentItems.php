<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Mapper;

// Dotclear\Helper\Mapper\ContentItems

/**
 * Stack of items groups helper.
 *
 * @ingroup  Helper Stack
 */
final class ContentItems
{
    /**
     * @var array<int,ContentItem> The items
     */
    private $items = [];

    /**
     * Constructor.
     *
     * @param string $id    The id
     * @param string $title The title
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
    ) {
    }

    /**
     * Add an item.
     *
     * @param ContentItem $item The item
     */
    public function addItem(ContentItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * Get all items.
     *
     * @return array<int,ContentItem> The items
     */
    public function dumpItems(): array
    {
        return $this->items;
    }

    /**
     * Echo items content.
     */
    public function echoContent(): void
    {
        foreach ($this->items as $item) {
            echo $item->content;
        }
    }
}
