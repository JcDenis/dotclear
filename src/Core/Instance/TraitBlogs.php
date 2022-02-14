<?php
/**
 * @class Dotclear\Core\Instance\TraitBlogs
 * @brief Dotclear trait blogs managment
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Blog;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitBlogs
{
    /** @var    Blog   Blog instance */
    private $blogs = null;

    /**
     * Get instance
     *
     * @return  Blog   Blog instance
     */
    public function blogs(): Blogs
    {
        if (!($this->blogs instanceof Blogs)) {
            $this->blogs = new Blogs();
        }

        return $this->blogs;
    }
}
