<?php
/**
 * @class Dotclear\Plugin\AboutConfig\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PlugniAboutConfig
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\AboutConfig\Admin;

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
        # Add Plugin Admin Page sidebar menu item
        $core->menu['System']->addItem(
            'about:config',
            $core->adminurl->get('admin.plugin.AboutConfig'),
            '?pf=AboutConfig/icon.png',
            $core->adminurl->called() == 'admin.plugin.AboutConfig',
            $core->auth->isSuperAdmin()
        );
    }
}
