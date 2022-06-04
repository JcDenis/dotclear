<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Comments
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Action\Action\CommentAction;
use Dotclear\Process\Admin\Inventory\Inventory\CommentInventory;
use Dotclear\Process\Admin\Filter\Filter\CommentFilters;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Admin comments list page.
 *
 * @ingroup  Admin Comment Handler
 */
class Comments extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?CommentAction
    {
        return new CommentAction(App::core()->adminurl()->get('admin.comments'));
    }

    protected function getFilterInstance(): ?CommentFilters
    {
        return new CommentFilters();
    }

    protected function getInventoryInstance(): ?CommentInventory
    {
        $param = $this->filter->getParams();

        // lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'post_title'          => 'post_title',
            'comment_author'      => 'comment_author',
            'comment_spam_filter' => 'comment_spam_filter', ];

        // --BEHAVIOR-- adminCommentsSortbyLexCombo
        App::core()->behavior()->call('adminCommentsSortbyLexCombo', [&$sortby_lex]);

        $param->set('order', (
            array_key_exists($this->filter->getValue(id: 'sortby'), $sortby_lex) ?
                App::core()->con()->lexFields($sortby_lex[$this->filter->getValue(id: 'sortby')]) :
                $this->filter->getValue(id: 'sortby')
        ) . ' ' . $this->filter->getValue(id: 'order'));

        // default filter ? do not display spam
        if (!$this->filter->isUnfolded() && '' == $this->filter->getValue(id: 'status')) {
            $param->set('comment_status_not', -2);
        }
        $param->set('no_content', true);

        return new CommentInventory(
            App::core()->blog()->comments()->getComments(param: $param),
            App::core()->blog()->comments()->countComments(param: $param)
        );
    }

    protected function getPagePrepend(): ?bool
    {
        if (!GPC::post()->empty('delete_all_spam')) {
            try {
                App::core()->blog()->comments()->deleteJunkComments();
                $_SESSION['comments_del_spam'] = true;
                App::core()->adminurl()->redirect('admin.comments');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Comments and trackbacks'))
            ->setPageHelp('core_comments')
            ->setPageHead(App::core()->resource()->load('_comments.js') . $this->filter?->getFoldableJSCode())
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Comments and trackbacks')               => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!GPC::get()->empty('upd')) {
            App::core()->notice()->success(__('Selected comments have been successfully updated.'));
        } elseif (!GPC::get()->empty('del')) {
            App::core()->notice()->success(__('Selected comments have been successfully deleted.'));
        }

        if (App::core()->error()->flag()) {
            return;
        }

        $combo_action = [];
        $default      = '';
        if (App::core()->user()->check('delete,contentadmin', App::core()->blog()->id) && -2 == $this->filter->getValue(id: 'status')) {
            $default = 'delete';
        }

        if (isset($_SESSION['comments_del_spam'])) {
            App::core()->notice()->message(__('Spam comments have been successfully deleted.'));
            unset($_SESSION['comments_del_spam']);
        }

        $param = new Param();
        $param->set('comment_status', -2);
        $spam_count = App::core()->blog()->comments()->countComments(param: $param);
        if (0 < $spam_count) {
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post" class="fieldset">';

            if (!$this->filter->isUnfolded() || -2 != $this->filter->getValue(id: 'status')) {
                if (1 == $spam_count) {
                    echo '<p>' . sprintf(__('You have one spam comment.'), '<strong>' . $spam_count . '</strong>') . ' ' .
                    '<a href="' . App::core()->adminurl()->get('admin.comments', ['status' => -2]) . '">' . __('Show it.') . '</a></p>';
                } else {
                    echo '<p>' . sprintf(__('You have %s spam comments.'), '<strong>' . $spam_count . '</strong>') . ' ' .
                    '<a href="' . App::core()->adminurl()->get('admin.comments', ['status' => -2]) . '">' . __('Show them.') . '</a></p>';
                }
            }

            echo '<p>' .
            App::core()->adminurl()->getHiddenFormFields('admin.comments', [], true) .
            '<input name="delete_all_spam" class="delete" type="submit" value="' . __('Delete all spams') . '" /></p>';

            // --BEHAVIOR-- adminCommentsSpamForm
            App::core()->behavior()->call('adminCommentsSpamForm');

            echo '</form>';
        }

        $this->filter?->displayHTMLForm('admin.comments');

        // Show comments
        $this->inventory?->display(
            $this->filter->getValue(id: 'page'),
            $this->filter->getValue(id: 'nb'),
            '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-comments">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
            Form::combo(
                'action',
                $this->action->getCombo(),
                ['default' => $default, 'extra_html' => 'title="' . __('Actions') . '"']
            ) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            App::core()->adminurl()->getHiddenFormFields('admin.comments', $this->filter->getEscapeValues(), true) .
            '</div>' .

            '</form>',
            $this->filter->isUnfolded(),
            ($this->filter->isUnfolded() || -2 == $this->filter->getValue(id: 'status')),
            App::core()->user()->check('contentadmin', App::core()->blog()->id)
        );
    }
}
