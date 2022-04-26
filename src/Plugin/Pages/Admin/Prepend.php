<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Admin;

// Dotclear\Plugin\Pages\Admin\Prepend
use ArrayObject;
use Dotclear\App;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Pages\Common\PagesUrl;
use Dotclear\Plugin\Pages\Common\PagesWidgets;
use Dotclear\Process\Admin\Favorite\Favorite;

/**
 * Admin prepend for plugin Pages.
 *
 * @ingroup  Plugin Pages
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        // Add pages permissions
        App::core()->user()->setPermissionType('pages', __('manage pages'));

        // Add admin url (only page detail, the other one was auto created by Module)
        App::core()->adminurl()->register(
            'admin.plugin.Page',
            'Dotclear\\Plugin\\Pages\\Admin\\HandlerEdit'
        );

        // Add menu
        $this->addStandardMenu('Blog');

        // Add favorites
        App::core()->behavior()->add('adminDashboardFavorites', function (Favorite $favs): void {
            $favs->register('pages', [
                'title'        => __('Pages'),
                'url'          => App::core()->adminurl()->get('admin.plugin.Pages'),
                'small-icon'   => ['?df=Plugin/Pages/icon.svg', '?df=Plugin/Pages/icon-dark.svg'],
                'large-icon'   => ['?df=Plugin/Pages/icon.svg', '?df=Plugin/Pages/icon-dark.svg'],
                'permissions'  => 'contentadmin,pages',
                'dashboard_cb' => function (ArrayObject $v): void {
                    $page_count = App::core()->blog()->posts()->getPosts(['post_type' => 'page'], true)->fInt();
                    if (0 < $page_count) {
                        $str_pages = (1 < $page_count) ? __('%d pages') : __('%d page');
                        $v['title'] = sprintf($str_pages, $page_count);
                    }
                },
            ]);
            $favs->register('newpage', [
                'title'       => __('New page'),
                'url'         => App::core()->adminurl()->get('admin.plugin.Page'),
                'small-icon'  => ['?df=Plugin/Pages/icon-np.svg', '?df=Plugin/Pages/icon-np-dark.svg'],
                'large-icon'  => ['?df=Plugin/Pages/icon-np.svg', '?df=Plugin/Pages/icon-np-dark.svg'],
                'permissions' => 'contentadmin,pages',
                'active_cb'   => fn () => App::core()->adminurl()->is('admin.plugin.Page') && empty($_REQUEST['id']),
            ]);
        });

        // Add headers
        App::core()->behavior()->add(
            'adminUsersActionsHeaders',
            fn () => App::core()->resource()->load('_users_actions.js', 'Plugin', 'Pages')
        );

        // Add user pref list columns
        App::core()->behavior()->add('adminColumnsLists', function (ArrayObject $cols): void {
            // Set optional columns in pages lists
            $cols['pages'] = [__('Pages'), [
                'date'       => [true, __('Date')],
                'author'     => [true, __('Author')],
                'comments'   => [true, __('Comments')],
                'trackbacks' => [true, __('Trackbacks')],
            ]];
        });

        // Add user pref list filters
        App::core()->behavior()->add('adminFiltersLists', function (ArrayObject $sorts): void {
            $sorts['pages'] = [
                __('Pages'),
                null,
                null,
                null,
                [__('entries per page'), 30],
            ];
        });

        // Urls
        new PagesUrl();

        // Widgets
        if (App::core()->adminurl()->is('admin.plugin.Widgets')) {
            new PagesWidgets();
        }
    }

    public function installModule(): ?bool
    {
        if (null != App::core()->version()->get('pages')) {
            return null;
        }

        // Create a first pending page, only on a new installation of this plugin
        if (0 == App::core()->blog()->posts()->getPosts(['post_type' => 'page', 'no_content' => true], true)->fInt()
            && null == App::core()->blog()->settings()->get('pages')->get('firstpage')
        ) {
            App::core()->blog()->settings()->get('pages')->put('firstpage', true, 'boolean');

            $cur = App::core()->con()->openCursor(App::core()->prefix . 'post');
            $cur->setField('user_id', App::core()->user()->userID());
            $cur->setField('post_type', 'page');
            $cur->setField('post_format', 'xhtml');
            $cur->setField('post_lang', App::core()->blog()->settings()->get('system')->get('lang'));
            $cur->setField('post_title', __('My first page'));
            $cur->setField('post_content', '<p>' . __('This is your first page. When you\'re ready to blog, log in to edit or delete it.') . '</p>');
            $cur->setField('post_content_xhtml', $cur->getField('post_content'));
            $cur->setField('post_excerpt', '');
            $cur->setField('post_excerpt_xhtml', $cur->getField('post_excerpt'));
            $cur->setField('post_status', -2); // Pending status
            $cur->setField('post_open_comment', 0);
            $cur->setField('post_open_tb', 0);

            // Magic tweak :)
            $old_url_format = App::core()->blog()->settings()->get('system')->get('post_url_format');
            App::core()->blog()->settings()->get('system')->set('post_url_format', '{t}');

            App::core()->blog()->posts()->addPost($cur);

            App::core()->blog()->settings()->get('system')->set('post_url_format', $old_url_format);
        }

        return true;
    }
}
