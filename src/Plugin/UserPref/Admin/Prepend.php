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

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    public static function checkModule(Core $core): bool
    {
        return true;
    }

    public static function loadModule(Core $core): void
    {
/*
        # Register Plugin Admin Page
        $core->adminurl->register(
            'admin.plugin.UserPref',
            __NAMESPACE__ . '\\Page\\UserPref'
        );
*/
        # Add Plugin Admin Page sidebar menu item
        $core->menu['System']->addItem(
            'user:preferences',
            $core->adminurl->get('admin.plugin.UserPref'),
            '?pf=UserPref/icon.png',
            $core->adminurl->called() == 'admin.plugin.UserPref',
            $core->auth->isSuperAdmin()
        );
    }
}
