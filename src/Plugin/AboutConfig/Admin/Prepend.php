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
            'about:config',
            dcCore()->adminurl->get('admin.plugin.AboutConfig'),
            '?mf=Plugin/AboutConfig/icon.png',
            dcCore()->adminurl->called() == 'admin.plugin.AboutConfig',
            dcCore()->auth->isSuperAdmin()
        );
    }
}
