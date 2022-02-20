<?php
/**
 * @class Dotclear\Plugin\Blogroll\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBlogroll
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Admin;

use Dotclear\Core\Utils;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Blogroll\Lib\Blogroll;
use Dotclear\Plugin\Blogroll\Lib\BlogrollWidgets;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        # Menu and favs
        static::addStandardMenu('Blog');
        static::addStandardFavorites('usage,contentadmin');

        # Manage user permissions
        dotclear()->behavior()->add(
            'adminUsersActionsHeaders',
            fn () => Utils::jsLoad('?mf=Plugin/Blogroll/files/js/_users_actions.js')
        );

        dotclear()->user()->setPermissionType('blogroll', __('manage blogroll'));

        # Widgets
        if (dotclear()->adminurl()->called() == 'admin.plugin.Widgets') {
            new BlogrollWidgets();
        }
    }

    public static function installModule(): ?bool
    {
        return Blogroll::installModule();
    }
}
