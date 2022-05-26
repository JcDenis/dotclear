<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Categories;

// Dotclear\Core\Posts\CategoriesParam
use Dotclear\Database\Param;

/**
 * Categories query parameter helper.
 *
 * @ingroup  Core Category Param
 */
final class CategoriesParam extends Param
{
    /**
     * Get only entries with given type(s).
     *
     * default "post", array for many types and '' for no type
     *
     * @return array<int,string> The post(s) type(s)
     */
    public function post_type(): array
    {
        $types = $this->getCleanedValues('post_type', 'string');
        if (in_array('', $types, true)) {
            return [];
        }

        return empty($types) ? ['post'] : $types;
    }

    /**
     * Get only non empty categories.
     *
     * @return bool True for non empty categories
     */
    public function without_empty(): ?bool
    {
        return $this->getCleanedValue('without_empty', 'bool');
    }

    /**
     * Start with a given category.
     *
     * @return int The category ID
     */
    public function start(): int
    {
        return $this->getCleanedValue('start', 'int', 0);
    }

    /**
     * Categories level to retrieve.
     *
     * @return int The level
     */
    public function level(): int
    {
        return $this->getCleanedValue('level', 'int', 0);
    }

    /**
     * Filter on a category ID.
     *
     * @return null|int The category ID
     */
    public function cat_id(): ?int
    {
        return $this->getCleanedValue('cat_id', 'int');
    }

    /**
     * Filter on a category URL.
     *
     * @return null|string The category URL
     */
    public function cat_url(): ?string
    {
        return $this->getCleanedValue('cat_url', 'string');
    }
}
