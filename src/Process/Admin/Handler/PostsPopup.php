<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\PostsPopup
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Inventory\Inventory\PostMiniInventory;
use Dotclear\Process\Admin\Page\AbstractPage;

/**
 * Admin posts popup list page.
 *
 * @ingroup  Admin Post Handler
 */
class PostsPopup extends AbstractPage
{
    private $plugin_id = '';
    private $q         = '';
    private $page      = 1;
    private $type;
    private $type_combo = [];

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getInventoryInstance(): ?PostMiniInventory
    {
        $this->plugin_id = Html::sanitizeURL(GPC::request()->string('plugin_id'));
        $this->q         = GPC::request()->string('q', null);
        $this->page      = !GPC::request()->empty('page') ? max(1, GPC::request()->int('page')) : 1;
        $this->type      = GPC::request()->string('type', null);

        $post_types = App::core()->posttype()->dump();
        foreach ($post_types as $post_type) {
            $this->type_combo[$post_type->label] = $post_type->type;
        }
        if (!in_array($this->type, $this->type_combo)) {
            $this->type = null;
        }

        $param = new Param();
        $param->set('limit', [(($this->page - 1) * 10), 10]);
        $param->set('no_content', true);
        $param->set('order', 'post_dt DESC');

        if ($this->q) {
            $param->set('search', $this->q);
        }

        if ($this->type) {
            $param->set('post_type', $this->type);
        }

        return new PostMiniInventory(
            App::core()->blog()->posts()->getPosts(param: $param),
            App::core()->blog()->posts()->countPosts(param: $param)
        );
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageTitle(__('Add a link to an entry'))
            ->setPageType('popup')
            ->setPageHead(
                App::core()->resource()->load('_posts_list.js') .
                App::core()->resource()->load('_popup_posts.js') .
                App::core()->behavior()->call('adminPopupPosts', $this->plugin_id)
            )
        ;

        if ('admin.blog.pref' == $this->plugin_id) { // ! ?
            $this->setPageHead(
                App::core()->resource()->json('admin.blog.pref', ['base_url' => App::core()->blog()->url]) .
                App::core()->resource()->load('_blog_pref_popup_posts.js')
            );
        }

        return true;
    }

    protected function getPageContent(): void
    {
        echo '<h2 class="page-title">' . __('Add a link to an entry') . '</h2>' .

        '<form action="' . App::core()->adminurl()->get('admin.posts.popup') . '" method="get">' .
        '<p><label for="type" class="classic">' . __('Entry type:') . '</label> ' . Form::combo('type', $this->type_combo, $this->type) . '' .
        '<noscript><div><input type="submit" value="' . __('Ok') . '" /></div></noscript>' .
        Form::hidden(['plugin_id'], Html::escapeHTML($this->plugin_id)) .
        Form::hidden(['q'], Html::escapeHTML($this->q)) .
        Form::hidden(['popup'], '1') .
        Form::hidden(['handler'], 'admin.posts.popup') . '</p>' .
        '</form>' .

       '<form action="' . App::core()->adminurl()->get('admin.posts.popup') . '" method="get">' .
        '<p><label for="q" class="classic">' . __('Search entry:') . '</label> ' . Form::field('q', 30, 255, Html::escapeHTML($this->q)) .
        ' <input type="submit" value="' . __('Search') . '" />' .
        Form::hidden(['plugin_id'], Html::escapeHTML($this->plugin_id)) .
        Form::hidden(['type'], Html::escapeHTML($this->type)) .
        Form::hidden(['popup'], '1') .
        Form::hidden(['handler'], 'admin.posts.popup') .
        '</p></form>' .

        '<div id="form-entries">'; // I know it's not a form but we just need the ID
        $this->inventory->display($this->page, 10);
        '</div>' .

        '<p><button type="button" id="link-insert-cancel">' . __('cancel') . '</button></p>';
    }
}
