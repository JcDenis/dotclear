<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Posts;

// Dotclear\Core\Posts\PostsParam
use Dotclear\Database\Param;

/**
 * Posts query parameter helper.
 *
 * @ingroup  Core Post Param
 */
final class PostsParam extends Param
{
    /**
     * Don't retrieve entry content (excerpt and content).
     *
     * @return bool True not to get content
     */
    public function no_content(): bool
    {
        return $this->getCleanedValue('no_content', 'bool', false);
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
     * Get entries belonging to given post ID(s).
     *
     * @return array<int,int> The post(s) id(s)
     */
    public function post_id(): array
    {
        return $this->getCleanedValues('post_id', 'int');
    }

    /**
     * Get entry with given post_url field.
     *
     * @return null|string The post URL
     */
    public function post_url(): ?string
    {
        return $this->getCleanedValue('post_url', 'string');
    }

    /**
     * Get entries belonging to given user ID.
     *
     * @return null|string The user ID
     */
    public function user_id(): ?string
    {
        return $this->getCleanedValue('user_id', 'string');
    }

    /**
     * Get entries belonging to given category(ies) ID(s).
     *
     * Use string for category ID as
     * you can add modifier "?not" or "?sub" after the ID
     *
     * @return array<int,string> The category(ies) id(s)
     */
    public function cat_id(): array
    {
        return $this->getCleanedValues('cat_id', 'string');
    }

    /**
     * Get entries belonging to given category(ies) URL(s).
     *
     * You can add modifier "?not" or "?sub" after the URL
     *
     * @return array<int,string> The category(ies) URL(s)
     */
    public function cat_url(): array
    {
        return $this->getCleanedValues('cat_url', 'string');
    }

    /**
     * Get blogs with given post status.
     *
     * @return null|int The post status
     */
    public function post_status(): ?int
    {
        return $this->getCleanedValue('post_status', 'int');
    }

    /**
     * Get select flaged entries.
     *
     * @return bool True for selected entries
     */
    public function post_selected(): ?bool
    {
        return $this->getCleanedValue('post_selected', 'bool');
    }

    /**
     * Get first publication flaged entries.
     *
     * @return bool True for first pub entries
     */
    public function post_firstpub(): ?bool
    {
        return $this->getCleanedValue('post_firstpub', 'bool');
    }

    /**
     * Get blogs with given post year.
     *
     * @return null|int The post year
     */
    public function post_year(): ?int
    {
        return $this->getCleanedValue('post_year', 'int');
    }

    /**
     * Get blogs with given post month.
     *
     * @return null|int The post month
     */
    public function post_month(): ?int
    {
        return $this->getCleanedValue('post_month', 'int');
    }

    /**
     * Get blogs with given post day.
     *
     * @return null|int The post day
     */
    public function post_day(): ?int
    {
        return $this->getCleanedValue('post_day', 'int');
    }

    /**
     * Get entries with given language code.
     *
     * @return null|string The lang code
     */
    public function post_lang(): ?string
    {
        return $this->getCleanedValue('post_lang', 'string');
    }

    /**
     * Get entries corresponding of the following search string.
     *
     * @return null|string The search string
     */
    public function search(): ?string
    {
        return $this->getCleanedValue('search', 'string');
    }

    /**
     * Exclude entries with given post(s) ID(s).
     *
     * @return array<int,int> The excluded post(s) id(s)
     */
    public function exclude_post_id(): array
    {
        return $this->getCleanedValues('exclude_post_id', 'int');
    }
}
