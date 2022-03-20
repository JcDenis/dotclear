<?php
/**
 * @class Dotclear\Core\RsExt\RsExtLog
 * @brief Dotclear log record helpers.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Container\UserContainer;
use Dotclear\Core\RsExt\RsExtend;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

/**
 * Extent log record class.
 */
class RsExtLog extends RsExtend
{
    /**
     * Gets the user cn.
     *
     * @return     string  The user cn.
     */
    public function getUserCN()
    {
        $user = UserContainer::getUserCN(
            $this->rs->user_id,
            $this->rs->user_name,
            $this->rs->user_firstname,
            $this->rs->user_displayname
        );

        if ($user === 'unknown') {
            $user = __('unknown');
        }

        return $user;
    }
}
