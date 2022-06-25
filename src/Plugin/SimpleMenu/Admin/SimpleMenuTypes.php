<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Admin;

// Dotclear\Plugin\SimpleMenu\Admin\SimpleMenuTypes

/**
 * SimpleMenu types helper.
 *
 * @ingroup  Plugin SimpleMenu Stack
 */
final class SimpleMenuTypes
{
    /**
     * @var array<string,SimpleMenuType> $types
     */
    private $types = [];

    /**
     * Add type.
     *
     * @param SimpleMenuType $item The type item
     */
    public function addType(SimpleMenuType $item): void
    {
        $this->types[$item->id] = $item;
    }

    /**
     * Get type.
     *
     * @param string $id The type ID
     *
     * @return null|SimpleMenuType The type item
     */
    public function getType(string $id): ?SimpleMenuType
    {
        return $this->types[$id] ?? null;
    }

    /**
     * Check if a type exists.
     *
     * @param string $id The type ID
     *
     * @return bool TRue if it is exists
     */
    public function hasType(string $id): bool
    {
        return array_key_exists($id, $this->types);
    }

    /**
     * Get all types.
     *
     * @return array<string,SimpleMenuType> The types items
     */
    public function dumpTypes(): array
    {
        return $this->types;
    }

    /**
     * Get types combo.
     *
     * @return array<string,string> The combo
     */
    public function getCombo(): array
    {
        $combo = [];
        foreach ($this->types as $type) {
            $combo[$type->label] = $type->id;
        }

        return $combo;
    }
}
