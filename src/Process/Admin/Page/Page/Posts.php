<?php
/**
 * @class Dotclear\Process\Admin\Page\Page\Posts
 * @brief Dotclear admin posts list page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Page\Page;

use Dotclear\Process\Admin\Page\Page;
use Dotclear\Process\Admin\Page\Action\Action;
use Dotclear\Process\Admin\Page\Action\Action\PostAction;
use Dotclear\Process\Admin\Page\Catalog\Catalog;
use Dotclear\Process\Admin\Page\Catalog\Catalog\PostCatalog;
use Dotclear\Process\Admin\Page\Filter\Filter;
use Dotclear\Process\Admin\Page\Filter\Filter\PostFilter;
use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Posts extends Page
{
    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?Action
    {
        return new PostAction(dotclear()->adminurl()->get('admin.posts'));
    }

    protected function getFilterInstance(): ?Filter
    {
        return new PostFilter();
    }

    protected function getCatalogInstance(): ?Catalog
    {
        # get list params
        $params = $this->filter->params();

        # lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'post_title' => 'post_title',
            'cat_title'  => 'cat_title',
            'user_id'    => 'P.user_id'];

        # --BEHAVIOR-- adminPostsSortbyLexCombo
        dotclear()->behavior()->call('adminPostsSortbyLexCombo', [& $sortby_lex]);

        $params['order'] = (array_key_exists($this->filter->sortby, $sortby_lex) ?
            dotclear()->con()->lexFields($sortby_lex[$this->filter->sortby]) :
            $this->filter->sortby) . ' ' . $this->filter->order;

        $params['no_content'] = true;

        $posts     = dotclear()->blog()->posts()->getPosts($params);
        $counter   = dotclear()->blog()->posts()->getPosts($params, true);

        return new PostCatalog($posts, (int) $counter->f(0));
    }

    protected function getPagePrepend(): ?bool
    {
        $this->setPageHelp('core_posts');
        $this->setPageTitle(__('Posts'));
        $this->setPageHead(
            $this->filter->js() .
            dotclear()->filer()->load('_posts_list.js')
        );
        $this->setPageBreadcrumb([
            Html::escapeHTML(dotclear()->blog()->name) => '',
            __('Posts')                               => ''
        ]);

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            static::success(__('Selected entries have been successfully updated.'));
        } elseif (!empty($_GET['del'])) {
            static::success(__('Selected entries have been successfully deleted.'));
        }
        if (!dotclear()->error()->flag()) {
            echo '<p class="top-add"><a class="button add" href="' . dotclear()->adminurl()->get('admin.post') . '">' . __('New post') . '</a></p>';

            # filters
            $this->filter->display('admin.posts');

            # Show posts
            $this->catalog->display($this->filter->page, $this->filter->nb,
                '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-entries">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
                Form::combo('action', $this->action->getCombo()) .
                '<input id="do-action" type="submit" value="' . __('ok') . '" disabled /></p>' .
                dotclear()->adminurl()->getHiddenFormFields('admin.posts', $this->filter->values(), true) .
                '</div>' .
                '</form>',
                $this->filter->show()
            );
        }
    }
}