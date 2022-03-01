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

use Dotclear\Admin\Filer;
use Dotclear\Database\Structure;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Blogroll\Common\BlogrollWidgets;

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
            fn () => Filer::load('js/_users_actions.js', 'Plugin', 'Blogroll')
        );

        dotclear()->user()->setPermissionType('blogroll', __('manage blogroll'));

        # Widgets
        if (dotclear()->adminurl()->called() == 'admin.plugin.Widgets') {
            BlogrollWidgets::initBlogroll();
        }
    }

    public static function installModule(): ?bool
    {
        $s = new Structure(dotclear()->con(), dotclear()->prefix);

        $s->link
            ->link_id('bigint', 0, false)
            ->blog_id('varchar', 32, false)
            ->link_href('varchar', 255, false)
            ->link_title('varchar', 255, false)
            ->link_desc('varchar', 255, true)
            ->link_lang('varchar', 5, true)
            ->link_xfn('varchar', 255, true)
            ->link_position('integer', 0, false, 0)

            ->primary('pk_link', 'link_id')
        ;

        $s->link->index('idx_link_blog_id', 'btree', 'blog_id');
        $s->link->reference('fk_link_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');

        # Schema installation
        $si      = new Structure(dotclear()->con(), dotclear()->prefix);
        $changes = $si->synchronize($s);

        return true;
    }
}
