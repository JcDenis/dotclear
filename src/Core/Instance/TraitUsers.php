<?php
/**
 * @class Dotclear\Core\Instance\TraitUsers
 * @brief Dotclear trait Users managment
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Users;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitUsers
{
    /** @var    Users   Users instance */
    private $users = null;

    /**
     * Get instance
     *
     * @return  Users   Users instance
     */
    public function users(): Users
    {
        if (!($this->users instanceof Users)) {
            $this->users = new Users();
        }

        return $this->users;
    }
}
