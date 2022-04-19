<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Action\Action\PostAction;
use Dotclear\Process\Admin\Inventory\Inventory\PostInventory;
use Dotclear\Process\Admin\Filter\Filter\PostFilter;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;

/**
 * Admin posts list page.
 *
 * \Dotclear\Process\Admin\Handler\Posts
 *
 * @ingroup  Admin Post Handler
 */
class Posts extends AbstractPage
{
    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?PostAction
    {
        return new PostAction(dotclear()->adminurl()->get('admin.posts'));
    }

    protected function getFilterInstance(): ?PostFilter
    {
        return new PostFilter();
    }

    protected function getInventoryInstance(): ?PostInventory
    {
        // get list params
        $params = $this->filter->params();

        // lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'post_title' => 'post_title',
            'cat_title'  => 'cat_title',
            'user_id'    => 'P.user_id', ];

        // --BEHAVIOR-- adminPostsSortbyLexCombo
        dotclear()->behavior()->call('adminPostsSortbyLexCombo', [&$sortby_lex]);

        $params['order'] = (array_key_exists($this->filter->get('sortby'), $sortby_lex) ?
            dotclear()->con()->lexFields($sortby_lex[$this->filter->get('sortby')]) :
            $this->filter->get('sortby')) . ' ' . $this->filter->get('order');

        $params['no_content'] = true;

        return new PostInventory(
            dotclear()->blog()->posts()->getPosts($params),
            dotclear()->blog()->posts()->getPosts($params, true)->fInt()
        );
    }

    protected function getPagePrepend(): ?bool
    {
        $this->setPageHelp('core_posts');
        $this->setPageTitle(__('Posts'));
        $this->setPageHead(
            $this->filter->js() .
            dotclear()->resource()->load('_posts_list.js')
        );
        $this->setPageBreadcrumb([
            Html::escapeHTML(dotclear()->blog()->name) => '',
            __('Posts')                                => '',
        ]);

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            dotclear()->notice()->success(__('Selected entries have been successfully updated.'));
        } elseif (!empty($_GET['del'])) {
            dotclear()->notice()->success(__('Selected entries have been successfully deleted.'));
        }
        if (!dotclear()->error()->flag()) {
            echo '<p class="top-add"><a class="button add" href="' . dotclear()->adminurl()->get('admin.post') . '">' . __('New post') . '</a></p>';

            // filters
            $this->filter->display('admin.posts');

            // Show posts
            $this->inventory->display(
                $this->filter->get('page'),
                $this->filter->get('nb'),
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
