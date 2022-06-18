<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Permission;

// Dotclear\Core\Permission\UserPermissionItem
use Dotclear\Core\User\UserContainer;
use Dotclear\Helper\Mapper\Strings;

/**
 * User permissions descriptor.
 *
 * @ingroup  Core User Permission
 */
final class UserPermissionItem
{
    /**
     * Constructor.
     *
     * @param string  $id          The user ID
     * @param string  $name        The user name
     * @param string  $firstname   The user firstname
     * @param string  $displayname The user displayname
     * @param string  $email       The user email
     * @param bool    $super       Is super admin
     * @param Strings $perm        The blog user permissions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $firstname,
        public readonly string $displayname,
        public readonly string $email,
        public readonly bool $super,
        public readonly Strings $perm,
    ) {
    }

    /**
     * Get user common name.
     *
     * @return string The use common name
     */
    public function getUserCN(): string
    {
        return UserContainer::getUserCN(
            $this->id,
            $this->name,
            $this->firstname,
            $this->displayname,
        );
    }
}
