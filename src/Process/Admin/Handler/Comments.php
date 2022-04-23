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
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Action\Action\CommentAction;
use Dotclear\Process\Admin\Inventory\Inventory\CommentInventory;
use Dotclear\Process\Admin\Filter\Filter\CommentFilter;
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
    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?CommentAction
    {
        return new CommentAction(dotclear()->adminurl()->get('admin.comments'));
    }

    protected function getFilterInstance(): ?CommentFilter
    {
        return new CommentFilter();
    }

    protected function getInventoryInstance(): ?CommentInventory
    {
        $params = $this->filter->params();

        // lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'post_title'          => 'post_title',
            'comment_author'      => 'comment_author',
            'comment_spam_filter' => 'comment_spam_filter', ];

        // --BEHAVIOR-- adminCommentsSortbyLexCombo
        dotclear()->behavior()->call('adminCommentsSortbyLexCombo', [&$sortby_lex]);

        $params['order'] = (array_key_exists($this->filter->get('sortby'), $sortby_lex) ?
            dotclear()->con()->lexFields($sortby_lex[$this->filter->get('sortby')]) :
            $this->filter->get('sortby')) . ' ' . $this->filter->get('order');

        // default filter ? do not display spam
        if (!$this->filter->show() && '' == $this->filter->get('status')) {
            $params['comment_status_not'] = -2;
        }
        $params['no_content'] = true;

        return new CommentInventory(
            dotclear()->blog()->comments()->getComments($params),
            dotclear()->blog()->comments()->getComments($params, true)->fInt()
        );
    }

    protected function getPagePrepend(): ?bool
    {
        if (!empty($_POST['delete_all_spam'])) {
            try {
                dotclear()->blog()->comments()->delJunkComments();
                $_SESSION['comments_del_spam'] = true;
                dotclear()->adminurl()->redirect('admin.comments');
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Comments and trackbacks'))
            ->setPageHelp('core_comments')
            ->setPageHead(dotclear()->resource()->load('_comments.js') . $this->filter->js())
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Comments and trackbacks')              => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            dotclear()->notice()->success(__('Selected comments have been successfully updated.'));
        } elseif (!empty($_GET['del'])) {
            dotclear()->notice()->success(__('Selected comments have been successfully deleted.'));
        }

        if (dotclear()->error()->flag()) {
            return;
        }

        $combo_action = [];
        $default      = '';
        if (dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id) && -2 == $this->filter->get('status')) {
            $default = 'delete';
        }

        if (isset($_SESSION['comments_del_spam'])) {
            dotclear()->notice()->message(__('Spam comments have been successfully deleted.'));
            unset($_SESSION['comments_del_spam']);
        }

        $spam_count = dotclear()->blog()->comments()->getComments(['comment_status' => -2], true)->fInt();
        if (0 < $spam_count) {
            echo '<form action="' . dotclear()->adminurl()->root() . '" method="post" class="fieldset">';

            if (!$this->filter->show() || -2 != $this->filter->get('status')) {
                if (1 == $spam_count) {
                    echo '<p>' . sprintf(__('You have one spam comment.'), '<strong>' . $spam_count . '</strong>') . ' ' .
                    '<a href="' . dotclear()->adminurl()->get('admin.comments', ['status' => -2]) . '">' . __('Show it.') . '</a></p>';
                } else {
                    echo '<p>' . sprintf(__('You have %s spam comments.'), '<strong>' . $spam_count . '</strong>') . ' ' .
                    '<a href="' . dotclear()->adminurl()->get('admin.comments', ['status' => -2]) . '">' . __('Show them.') . '</a></p>';
                }
            }

            echo '<p>' .
            dotclear()->adminurl()->getHiddenFormFields('admin.comments', [], true) .
            '<input name="delete_all_spam" class="delete" type="submit" value="' . __('Delete all spams') . '" /></p>';

            // --BEHAVIOR-- adminCommentsSpamForm
            dotclear()->behavior()->call('adminCommentsSpamForm');

            echo '</form>';
        }

        $this->filter->display('admin.comments');

        // Show comments
        $this->inventory->display(
            $this->filter->get('page'),
            $this->filter->get('nb'),
            '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-comments">' .

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
            dotclear()->adminurl()->getHiddenFormFields('admin.comments', $this->filter->values(true), true) .
            '</div>' .

            '</form>',
            $this->filter->show(),
            ($this->filter->show() || -2 == $this->filter->get('status')),
            dotclear()->user()->check('contentadmin', dotclear()->blog()->id)
        );
    }
}
