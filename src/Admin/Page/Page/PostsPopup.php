<?php
/**
 * @class Dotclear\Admin\Page\Page\PostsPopup
 * @brief Dotclear admin posts popup list page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page\Page;

use Dotclear\Admin\Page\Page;
use Dotclear\Admin\Page\Catalog\Catalog;
use Dotclear\Admin\Page\Catalog\Catalog\PostMiniCatalog;
use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class PostsPopup extends Page
{
    private $plugin_id = '';
    private $q = '';
    private $page = 1;
    private $type = null;
    private $type_combo = [];

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getCatalogInstance(): ?Catalog
    {
        $this->plugin_id = !empty($_GET['plugin_id']) ? Html::sanitizeURL($_GET['plugin_id']) : '';
        $this->q         = !empty($_GET['q']) ? $_GET['q'] : null;
        $this->page      = !empty($_GET['page']) ? max(1, (integer) $_GET['page']) : 1;
        $this->type      = !empty($_GET['type']) ? $_GET['type'] : null;

        $post_types = dotclear()->posttype()->getPostTypes();
        foreach ($post_types as $k => $v) {
            $this->type_combo[__($k)] = (string) $k;
        }
        if (!in_array($this->type, $this->type_combo)) {
            $this->type = null;
        }

        $params               = [];
        $params['limit']      = [(($this->page - 1) * 10), 10];
        $params['no_content'] = true;
        $params['order']      = 'post_dt DESC';

        if ($this->q) {
            $params['search'] = $this->q;
        }

        if ($this->type) {
            $params['post_type'] = $this->type;
        }

        return new PostMiniCatalog(
            dotclear()->blog()->posts()->getPosts($params),
            dotclear()->blog()->posts()->getPosts($params, true)->f(0)
        );
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageTitle(__('Add a link to an entry'))
            ->setPageType('popup')
            ->setPageHead(
                static::jsLoad('js/_posts_list.js') .
                static::jsLoad('js/_popup_posts.js') .
                dotclear()->behavior()->call('adminPopupPosts', $this->plugin_id)
            )
        ;

        if ($this->plugin_id == 'admin.blog.pref') { //! ?
            $this->setPageHead(
                static::jsJson('admin.blog.pref', ['base_url' => dotclear()->blog()->url]) .
                static::jsLoad('js/_blog_pref_popup_posts.js')
            );
        }

        return true;
    }

    protected function getPageContent(): void
    {
        echo
        '<h2 class="page-title">' . __('Add a link to an entry') . '</h2>' .

        '<form action="' . dotclear()->adminurl()->get('admin.posts.popup') . '" method="get">' .
        '<p><label for="type" class="classic">' . __('Entry type:') . '</label> ' . Form::combo('type', $this->type_combo, $this->type) . '' .
        '<noscript><div><input type="submit" value="' . __('Ok') . '" /></div></noscript>' .
        Form::hidden('plugin_id', Html::escapeHTML($this->plugin_id)) . '</p>' .
        '</form>' .

       '<form action="' . dotclear()->adminurl()->get('admin.posts.popup') . '" method="get">' .
        '<p><label for="q" class="classic">' . __('Search entry:') . '</label> ' . Form::field('q', 30, 255, Html::escapeHTML($this->q)) .
        ' <input type="submit" value="' . __('Search') . '" />' .
        Form::hidden('plugin_id', Html::escapeHTML($this->plugin_id)) .
        Form::hidden('type', Html::escapeHTML($this->type)) .
        '</p></form>' .

        '<div id="form-entries">'; # I know it's not a form but we just need the ID
        $this->catalog->display($this->page, 10);
        '</div>' .

        '<p><button type="button" id="link-insert-cancel">' . __('cancel') . '</button></p>';
    }
}
