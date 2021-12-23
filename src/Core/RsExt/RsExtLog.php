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

use Dotclear\Core\Utils;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

/**
 * Extent log record class.
 */
class RsExtLog
{
    /**
     * Gets the user cn.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     string  The user cn.
     */
    public static function getUserCN($rs)
    {
        $user = Utils::getUserCN(
            $rs->user_id,
            $rs->user_name,
            $rs->user_firstname,
            $rs->user_displayname
        );

        if ($user === 'unknown') {
            $user = __('unknown');
        }

        return $user;
    }
}
