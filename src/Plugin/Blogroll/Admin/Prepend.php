<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Admin;

use Dotclear\Database\Structure;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Blogroll\Common\BlogrollWidgets;

/**
 * Admin prepend for plugin Blogroll.
 *
 * \Dotclear\Plugin\Blogroll\Admin\Prepend
 *
 * @ingroup  Plugin Blogroll
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        // Menu and favs
        $this->addStandardMenu('Blog');
        $this->addStandardFavorites('usage,contentadmin');

        // Manage user permissions
        dotclear()->behavior()->add(
            'adminUsersActionsHeaders',
            fn () => dotclear()->resource()->load('_users_actions.js', 'Plugin', 'Blogroll')
        );

        dotclear()->user()->setPermissionType('blogroll', __('manage blogroll'));

        // Widgets
        if (dotclear()->adminurl()->is('admin.plugin.Widgets')) {
            new BlogrollWidgets();
        }
    }

    public function installModule(): ?bool
    {
        $s = new Structure(dotclear()->con(), dotclear()->prefix);

        $s->table('link')
            ->field('link_id', 'bigint', 0, false)
            ->field('blog_id', 'varchar', 32, false)
            ->field('link_href', 'varchar', 255, false)
            ->field('link_title', 'varchar', 255, false)
            ->field('link_desc', 'varchar', 255, true)
            ->field('link_lang', 'varchar', 5, true)
            ->field('link_xfn', 'varchar', 255, true)
            ->field('link_position', 'integer', 0, false, 0)

            ->primary('pk_link', 'link_id')
        ;

        $s->table('link')->index('idx_link_blog_id', 'btree', 'blog_id');
        $s->table('link')->reference('fk_link_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');

        // Schema installation
        $si      = new Structure(dotclear()->con(), dotclear()->prefix);
        $changes = $si->synchronize($s);

        return true;
    }
}
