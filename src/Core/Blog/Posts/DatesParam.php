<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Posts;

// Dotclear\Core\Posts\DatesParam
use Dotclear\Database\Param;

/**
 * Posts query parameter helper.
 *
 * @ingroup  Core Post Param
 */
final class DatesParam extends Param
{
    /**
     * Get days, months or years.
     *
     * @return null|string The date type
     */
    public function type(): ?string
    {
        return $this->getCleanedValue('type', 'string');
    }

    /**
     * Get dates for given year.
     *
     * @return null|string The date year
     */
    public function year(): ?string
    {
        return $this->getCleanedValue('year', 'string');
    }

    /**
     * Get dates for given month.
     *
     * @return null|string The date month
     */
    public function month(): ?string
    {
        return $this->getCleanedValue('month', 'string');
    }

    /**
     * Get dates for given day.
     *
     * @return null|string The date day
     */
    public function day(): ?string
    {
        return $this->getCleanedValue('day', 'string');
    }

    /**
     * Category ID filter.
     *
     * @return null|int The category ID
     */
    public function cat_id(): ?int
    {
        return $this->getCleanedValue('cat_id', 'int');
    }

    /**
     * Category URL filter.
     *
     * @return null|string The category URL
     */
    public function cat_url(): ?string
    {
        return $this->getCleanedValue('cat_url', 'string');
    }

    /**
     * Restrict lang of the posts.
     *
     * @return null|string The lang code
     */
    public function post_lang(): ?string
    {
        return $this->getCleanedValue('post_lang', 'string');
    }

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
     * Get date following match.
     *
     * @return null|string The next date
     */
    public function next(): ?string
    {
        return $this->getCleanedValue('next', 'string');
    }

    /**
     * Get date before match.
     *
     * @return null|string The previous date
     */
    public function previous(): ?string
    {
        return $this->getCleanedValue('previous', 'string');
    }

    /**
     * Get entries with given language code.
     *
     * @return string The lang code
     */
    public function order(string $default = 'desc'): string
    {
        $order = strtolower($this->getCleanedValue('order', 'string', $default));

        return in_array($order, ['asc', 'desc']) ? $order : $default;
    }
}
