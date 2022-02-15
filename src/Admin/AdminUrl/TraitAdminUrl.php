<?php
/**
 * @class Dotclear\Admin\AdminUrl\TraitAdminUrl
 * @brief Dotclear trait admin url
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\AdminUrl;

use Dotclear\Admin\AdminUrl\AdminUrl;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitAdminUrl
{
    /** @var    AdminUrl   AdminUrl instance */
    private $adminurl;

    /**
     * Get instance
     *
     * @return  AdminUrl   AdminUrl instance
     */
    public function adminurl(): AdminUrl
    {
        if (!($this->adminurl instanceof AdminUrl)) {
            $this->adminurl = new AdminUrl();
        }

        return $this->adminurl;
    }
}
