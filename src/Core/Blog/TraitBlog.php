<?php
/**
 * @class Dotclear\Core\Blog\TraitBlog
 * @brief Dotclear trait Log
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog;

use Dotclear\Core\Blog\Blog;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitBlog
{
    /** @var    Blog   Blog instance */
    private $blog = null;

    /**
     * Get instance
     *
     * @return  Blog|null   Blog instance
     */
    public function blog(): ?Blog
    {
        return $this->blog;
    }

    /**
     * Sets the blog to use.
     *
     * @param   string  $blog_id    The blog ID
     */
    public function setBlog(string $blog_id): void
    {
        $this->blog = new Blog($blog_id);
    }

    /**
     * Unsets blog property
     */
    public function unsetBlog(): void
    {
        $this->blog = null;
    }
}
