<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Log;

// Dotclear\Core\Log\LogRecordExtend
use Dotclear\Core\RsExt\RsExtend;
use Dotclear\Core\User\UserContainer;

/**
 * Logs record helpers.
 *
 * @ingroup  Core Log Record
 */
final class LogRecordExtend extends RsExtend
{
    /**
     * Gets the user cn.
     *
     * @return string the user cn
     */
    public function getUserCN()
    {
        $user = UserContainer::getUserCN(
            $this->rs->field('user_id'),
            $this->rs->field('user_name'),
            $this->rs->field('user_firstname'),
            $this->rs->field('user_displayname')
        );

        if ('unknown' === $user) {
            $user = __('unknown');
        }

        return $user;
    }
}
