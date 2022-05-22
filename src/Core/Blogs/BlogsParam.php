<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blogs;

// Dotclear\Core\Blogs\BlogsParam
use Dotclear\Database\Param;

/**
 * Blogs query parameter helper.
 *
 * @ingroup  Core Blog Param
 */
final class BlogsParam extends Param
{
    /**
     * Get blogs with given blog status.
     *
     * @return null|int The blog status
     */
    public function blog_status(): ?int
    {
        return $this->getCleanedValue('blog_status', 'int');
    }

    /**
     * Get blogs belonging to given blog ID.
     *
     * @return array<int,string> The blog(s) id(s)
     */
    public function blog_id(): array
    {
        return $this->getCleanedValues('blog_id', 'string');
    }

    /**
     * Search blogs (on blog_id, blog_name, blog_url).
     *
     * @return null|string The search string
     */
    public function q(): ?string
    {
        return $this->getCleanedValue('q', 'string');
    }
}
