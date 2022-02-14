<?php
/**
 * @class Dotclear\Core\Instance\TraitComments
 * @brief Dotclear trait comments
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Comments;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitComments
{
    /** @var    Comments   Comments instance */
    private $comments;

    /**
     * Get instance
     *
     * @return  Comments   Comments instance
     */
    public function comments(): Comments
    {
        if (!($this->comments instanceof Comments)) {
            $this->comments = new Comments();
        }

        return $this->comments;
    }
}
