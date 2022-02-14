<?php
/**
 * @class Dotclear\Core\Instance\TraitPostMedia
 * @brief Dotclear trait PostMedia
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\PostMedia;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitPostMedia
{
    /** @var    PostMedia   PostMedia instance */
    private $postmedia;

    /**
     * Get instance
     *
     * @return  PostMedia   PostMedia instance
     */
    public function postmedia(): PostMedia
    {
        if (!($this->postmedia instanceof PostMedia)) {
            $this->postmedia = new PostMedia();
        }

        return $this->postmedia;
    }
}
