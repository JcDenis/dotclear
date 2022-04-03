<?php
/**
 * @class Dotclear\Plugin\Pages\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Admin;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Pages\Common\PagesUrl;
use Dotclear\Plugin\Pages\Common\PagesWidgets;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        # Add pages permissions
        dotclear()->user()->setPermissionType('pages', __('manage pages'));

        # Add admin url (only page detail, the other one was auto created by Module)
        dotclear()->adminurl()->register(
            'admin.plugin.Page',
            'Dotclear\\Plugin\\Pages\\Admin\\HandlerEdit'
        );

        # Add menu
        $this->addStandardMenu('Blog');

        # Add favorites
        dotclear()->behavior()->add('adminDashboardFavorites', function ($favs) {
            $favs->register('pages', [
                'title'        => __('Pages'),
                'url'          => dotclear()->adminurl()->get('admin.plugin.Pages'),
                'small-icon'   => ['?df=Plugin/Pages/icon.svg', '?df=Plugin/Pages/icon-dark.svg'],
                'large-icon'   => ['?df=Plugin/Pages/icon.svg', '?df=Plugin/Pages/icon-dark.svg'],
                'permissions'  => 'contentadmin,pages',
                'dashboard_cb' => function ($v) {
                    $page_count = dotclear()->blog()->posts()->getPosts(['post_type' => 'page'], true)->fInt();
                    if ($page_count > 0) {
                        $str_pages  = ($page_count > 1) ? __('%d pages') : __('%d page');
                        $v['title'] = sprintf($str_pages, $page_count);
                    }
                },
            ]);
            $favs->register('newpage', [
                'title'       => __('New page'),
                'url'         => dotclear()->adminurl()->get('admin.plugin.Page'),
                'small-icon'  => ['?df=Plugin/Pages/icon-np.svg', '?df=Plugin/Pages/icon-np-dark.svg'],
                'large-icon'  => ['?df=Plugin/Pages/icon-np.svg', '?df=Plugin/Pages/icon-np-dark.svg'],
                'permissions' => 'contentadmin,pages',
                'active_cb'   => function () {
                    return dotclear()->adminurl()->called() == 'admin.plugin.Page' && empty($_REQUEST['id']);
                }
            ]);
        });

        # Add headers
        dotclear()->behavior()->add(
            'adminUsersActionsHeaders',
            fn () => dotclear()->resource()->load('_users_actions.js', 'Plugin', 'Pages')
        );

        # Add user pref list columns
        dotclear()->behavior()->add('adminColumnsLists', function ($cols) {
            // Set optional columns in pages lists
            $cols['pages'] = [__('Pages'), [
                'date'       => [true, __('Date')],
                'author'     => [true, __('Author')],
                'comments'   => [true, __('Comments')],
                'trackbacks' => [true, __('Trackbacks')],
            ]];
        });

        # Add user pref list filters
        dotclear()->behavior()->add('adminFiltersLists', function ($sorts) {
            $sorts['pages'] = [
                __('Pages'),
                null,
                null,
                null,
                [__('entries per page'), 30],
            ];
        });

        # Urls
        new PagesUrl();

        # Widgets
        if (dotclear()->adminurl()->called() == 'admin.plugin.Widgets') {
            new PagesWidgets();
        }
    }

    public function installModule(): ?bool
    {
        if (dotclear()->version()->get('pages') != null) {
            return null;
        }

        // Create a first pending page, only on a new installation of this plugin
        if (0 == dotclear()->blog()->posts()->getPosts(['post_type' => 'page', 'no_content' => true], true)->fInt()
            && null == dotclear()->blog()->settings()->get('pages')->get('firstpage')
        ) {
            dotclear()->blog()->settings()->get('pages')->put('firstpage', true, 'boolean');

            $cur                     = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
            $cur->user_id            = dotclear()->user()->userID();
            $cur->post_type          = 'page';
            $cur->post_format        = 'xhtml';
            $cur->post_lang          = dotclear()->blog()->settings()->get('system')->get('lang');
            $cur->post_title         = __('My first page');
            $cur->post_content       = '<p>' . __('This is your first page. When you\'re ready to blog, log in to edit or delete it.') . '</p>';
            $cur->post_content_xhtml = $cur->post_content;
            $cur->post_excerpt       = '';
            $cur->post_excerpt_xhtml = $cur->post_excerpt;
            $cur->post_status        = -2; // Pending status
            $cur->post_open_comment  = 0;
            $cur->post_open_tb       = 0;

            # Magic tweak :)
            $old_url_format = dotclear()->blog()->settings()->get('system')->get('post_url_format');
            dotclear()->blog()->settings()->get('system')->set('post_url_format', '{t}');

            dotclear()->blog()->posts()->addPost($cur);

            dotclear()->blog()->settings()->get('system')->set('post_url_format', $old_url_format);
        }

        return true;
    }
}
