<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Admin;

// Dotclear\Plugin\Pages\Admin\Handler
use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\Form;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Page\AbstractPage;

/**
 * Admin page for plugin Pages.
 *
 * @ingroup  Plugin Pages
 */
class Handler extends AbstractPage
{
    private $p_page = 1;
    private $p_nbbp = 30;

    protected function getPermissions(): string|bool
    {
        return 'pages,contentadmin';
    }

    protected function getActionInstance(): ?Action
    {
        return new PagesAction(App::core()->adminurl()->get('admin.plugin.Pages'));
    }

    protected function getInventoryInstance(): ?Inventory
    {
        $params = [
            'post_type' => 'page',
        ];

        $this->p_page = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $this->p_nbbp = App::core()->listoption()->getUserFiltersNb('pages');

        if (!empty($_GET['nb']) && 0 < (int) $_GET['nb']) {
            $this->p_nbbp = (int) $_GET['nb'];
        }

        $params['limit']      = [(($this->p_page - 1) * $this->p_nbbp), $this->p_nbbp];
        $params['no_content'] = true;
        $params['order']      = 'post_position ASC, post_title ASC';

        $pages = App::core()->blog()->posts()->getPosts($params);
        $count = App::core()->blog()->posts()->getPosts($params, true)->fInt();

        return new PagesInventory($pages, $count);
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageHelp('pages')
            ->setPageTitle(__('Pages'))
            ->setPageHead(
                App::core()->resource()->load('jquery/jquery-ui.custom.js') .
                App::core()->resource()->load('jquery/jquery.ui.touch-punch.js') .
                App::core()->resource()->json('pages_list', ['confirm_delete_posts' => __('Are you sure you want to delete selected pages?')]) .
                App::core()->resource()->load('list.js', 'Plugin', 'Pages')
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Pages')                                 => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            App::core()->notice()->success(__('Selected pages have been successfully updated.'));
        } elseif (!empty($_GET['del'])) {
            App::core()->notice()->success(__('Selected pages have been successfully deleted.'));
        } elseif (!empty($_GET['reo'])) {
            App::core()->notice()->success(__('Selected pages have been successfully reordered.'));
        }

        echo '<p class="top-add"><a class="button add" href="' . App::core()->adminurl()->get('admin.plugin.Page') . '">' . __('New page') . '</a></p>';

        if (!App::core()->error()->flag()) {
            // Show pages
            $this->inventory->display(
                $this->p_page,
                $this->p_nbbp,
                '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-entries">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected pages action:') . '</label> ' .
                Form::combo('action', $this->action->getCombo()) .
                '<input id="do-action" type="submit" value="' . __('ok') . '" />' .
                App::core()->adminurl()->getHiddenFormFields('admin.plugin.Pages', ['post_type' => 'page'], true) .
                '</p></div>' .
                '<p class="clear form-note hidden-if-js">' .
                __('To rearrange pages order, change number at the begining of the line, then click on “Save pages order” button.') . '</p>' .
                '<p class="clear form-note hidden-if-no-js">' .
                __('To rearrange pages order, move items by drag and drop, then click on “Save pages order” button.') . '</p>' .
                '<p><input type="submit" value="' . __('Save pages order') . '" name="reorder" class="clear" /></p>' .
                '</form>'
            );
        }
    }
}
