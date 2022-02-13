<?php
/**
 * @class Dotclear\Admin\Page\Services
 * @brief Dotclear admin rest service page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Admin\Page;
use Dotclear\Admin\RestMethods;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Services extends Page
{
    protected function getPermissions(): string|null|false
    {
        return false;
    }

    protected function getPagePrepend(): ?bool
    {
        RestMethods::initDefaultRestMethods();
        dotclear()->rest()->serve();

        return null;
    }
}
