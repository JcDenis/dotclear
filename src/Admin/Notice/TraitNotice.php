<?php
/**
 * @class Dotclear\Admin\Notice\TraitNotice
 * @brief Dotclear trait admin notices
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Notice;

use Dotclear\Admin\Notice\Notice;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitNotice
{
    /** @var    Notice   Notice instance */
    private $notice;

    /**
     * Get instance
     *
     * @return  Notice   Notice instance
     */
    public function notice(): Notice
    {
        if (!($this->notice instanceof Notice)) {
            $this->notice = new Notice();
        }

        return $this->notice;
    }
}
