<?php
/**
 * @class Dotclear\Plugin\UserPref\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PlugniUserPref
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\UserPref\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function checkModule(): bool
    {
        return true;
    }

    public static function loadModule(): void
    {
        # Add Plugin Admin Page sidebar menu item
        dcCore()->menu['System']->addItem(
            'user:preferences',
            dcCore()->adminurl->get('admin.plugin.UserPref'),
            '?mf=Plugin/UserPref/icon.png',
            dcCore()->adminurl->called() == 'admin.plugin.UserPref',
            dcCore()->auth->isSuperAdmin()
        );
    }
}
