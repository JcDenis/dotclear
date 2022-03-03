<?php
/**
 * @class Dotclear\Plugin\Pages\Admin\Page
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

use Dotclear\Admin\Page\Action\Action;
use Dotclear\Admin\Page\Catalog\Catalog;
use Dotclear\Html\Html;
use Dotclear\Html\Form;
use Dotclear\Module\AbstractPage;
use Dotclear\Plugin\Pages\Admin\Action\PagesAction;
use Dotclear\Plugin\Pages\Admin\Catalog\PagesCatalog;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    private $p_page = 1;
    private $p_nbbp = 30;

    protected function getPermissions(): string|null|false
    {
        return 'pages,contentadmin';
    }

    protected function getActionInstance(): ?Action
    {
        return new PagesAction(dotclear()->adminurl()->get('admin.plugin.Pages'));
    }

    protected function getCatalogInstance(): ?Catalog
    {
        $params = [
            'post_type' => 'page',
        ];

        $this->p_page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $this->p_nbbp = dotclear()->listoption()->getUserFilters('pages', 'nb');

        if (!empty($_GET['nb']) && (int) $_GET['nb'] > 0) {
            $this->p_nbbp = (int) $_GET['nb'];
        }

        $params['limit']      = [(($this->p_page - 1) * $this->p_nbbp), $this->p_nbbp];
        $params['no_content'] = true;
        $params['order']      = 'post_position ASC, post_title ASC';

        $pages     = dotclear()->blog()->posts()->getPosts($params);
        $counter   = dotclear()->blog()->posts()->getPosts($params, true);

        return new PagesCatalog($pages, (int) $counter->f(0));
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageHelp('pages')
            ->setPageTitle(__('Pages'))
            ->setPageHead(
                dotclear()->filer()->load('jquery/jquery-ui.custom.js') .
                dotclear()->filer()->load('jquery/jquery.ui.touch-punch.js') .
                dotclear()->filer()->json('pages_list', ['confirm_delete_posts' => __('Are you sure you want to delete selected pages?')]) .
                dotclear()->filer()->load('list.js', 'Plugin', 'Pages')
            )
            ->setPageBreadcrumb([
                  Html::escapeHTML(dotclear()->blog()->name) => '',
                  __('Pages')                                => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            dotclear()->notice()->success(__('Selected pages have been successfully updated.'));
        } elseif (!empty($_GET['del'])) {
            dotclear()->notice()->success(__('Selected pages have been successfully deleted.'));
        } elseif (!empty($_GET['reo'])) {
            dotclear()->notice()->success(__('Selected pages have been successfully reordered.'));
        }

        echo
        '<p class="top-add"><a class="button add" href="' . dotclear()->adminurl()->get('admin.plugin.Page') . '">' . __('New page') . '</a></p>';

        if (!dotclear()->error()->flag()) {
            # Show pages
            $this->catalog->display(
                $this->p_page,
                $this->p_nbbp,
                '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-entries">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected pages action:') . '</label> ' .
                Form::combo('action', $this->action->getCombo()) .
                '<input id="do-action" type="submit" value="' . __('ok') . '" />' .
                dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Pages', ['post_type' => 'page'], true) .
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
