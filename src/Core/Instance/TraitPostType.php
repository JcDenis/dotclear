<?php
/**
 * @class Dotclear\Core\Instance\PostType
 * @brief Dotclear trait Post Type
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\PostType;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitPostType
{
    /** @var    PostType   PostType instance */
    private $posttype;

    /**
     * Get instance
     *
     * @return  PostType   PostType instance
     */
    public function posttype(): PostType
    {
        if (!($this->posttype instanceof PostType)) {
            $this->posttype = new PostType();
        }

        return $this->posttype;
    }
}
