<?php
/**
 * @class Dotclear\Core\Instance\TraitPosts
 * @brief Dotclear trait Posts
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Posts;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitPosts
{
    /** @var    Posts   Posts instance */
    private $posts;

    /**
     * Get instance
     *
     * @return  Posts   Posts instance
     */
    public function posts(): Posts
    {
        if (!($this->posts instanceof Posts)) {
            $this->posts = new Posts();
        }

        return $this->posts;
    }
}
