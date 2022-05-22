<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Users;

// Dotclear\Core\Users\UsersParam
use Dotclear\Database\Param;

/**
 * Users query parameter helper.
 *
 * @ingroup  Core User Param
 */
final class UsersParam extends Param
{
    /**
     * Search users (on user_id, user_name, user_firstname).
     *
     * @return null|string The search string
     */
    public function q(): ?string
    {
        return $this->getCleanedValue('q', 'string');
    }

    /**
     * Get users belonging to given user ID.
     *
     * @return null|string The user id
     */
    public function user_id(): ?string
    {
        return $this->getCleanedValue('user_id', 'string');
    }
}
