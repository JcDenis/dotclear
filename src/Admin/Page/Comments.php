<?php
/**
 * @class Dotclear\Admin\Page\Comments
 * @brief Dotclear admin posts list page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;
use Dotclear\Admin\Notices;
use Dotclear\Admin\Action;
use Dotclear\Admin\Filter;
use Dotclear\Admin\Catalog;

use Dotclear\Admin\Action\CommentAction;
use Dotclear\Admin\Catalog\CommentCatalog;
use Dotclear\Admin\Filter\CommentFilter;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Comments extends Page
{
    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?Action
    {
        return new CommentAction($this->core, $this->core->adminurl->get('admin.comments'));;
    }

    protected function getFilterInstance(): ?Filter
    {
        return new CommentFilter($this->core);
    }

    protected function getCatalogInstance(): ?Catalog
    {
        $params = $this->filter->params();

        # lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'post_title'          => 'post_title',
            'comment_author'      => 'comment_author',
            'comment_spam_filter' => 'comment_spam_filter'];

        # --BEHAVIOR-- adminCommentsSortbyLexCombo
        $this->core->behaviors->call('adminCommentsSortbyLexCombo', [& $sortby_lex]);

        $params['order'] = (array_key_exists($this->filter->sortby, $sortby_lex) ?
            $this->core->con->lexFields($sortby_lex[$this->filter->sortby]) :
            $this->filter->sortby) . ' ' . $this->filter->order;

        # default filter ? do not display spam
        if (!$this->filter->show() && $this->filter->status == '') {
            $params['comment_status_not'] = -2;
        }
        $params['no_content'] = true;

        return new CommentCatalog(
            $this->core,
            $this->core->blog->getComments($params),
            $this->core->blog->getComments($params, true)->f(0)
        );
    }

    protected function getPagePrepend(): ?bool
    {
        if (!empty($_POST['delete_all_spam'])) {
            try {
                $this->core->blog->delJunkComments();
                $_SESSION['comments_del_spam'] = true;
                $this->core->adminurl->redirect('admin.comments');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Comments and trackbacks'))
            ->setPageHelp('core_comments')
            ->setPageHead(static::jsLoad('js/_comments.js') . $this->filter->js())
            ->setPageBreadcrumb([
                Html::escapeHTML($this->core->blog->name) => '',
                __('Comments and trackbacks')             => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            $this->core->notices->success(__('Selected comments have been successfully updated.'));
        } elseif (!empty($_GET['del'])) {
            $this->core->notices->success(__('Selected comments have been successfully deleted.'));
        }

        if ($this->core->error->flag()) {
            return;
        }

        $combo_action = [];
        $default      = '';
        if ($this->core->auth->check('delete,contentadmin', $this->core->blog->id) && $this->filter->status == -2) {
            $default = 'delete';
        }

        if (isset($_SESSION['comments_del_spam'])) {
            $this->core->notices->message(__('Spam comments have been successfully deleted.'));
            unset($_SESSION['comments_del_spam']);
        }

        $spam_count = $this->core->blog->getComments(['comment_status' => -2], true)->f(0);
        if ($spam_count > 0) {
            echo
            '<form action="' . $this->core->adminurl->get('admin.comments') . '" method="post" class="fieldset">';

            if (!$this->filter->show() || ($this->filter->status != -2)) {
                if ($spam_count == 1) {
                    echo '<p>' . sprintf(__('You have one spam comment.'), '<strong>' . $spam_count . '</strong>') . ' ' .
                    '<a href="' . $this->core->adminurl->get('admin.comments', ['status' => -2]) . '">' . __('Show it.') . '</a></p>';
                } elseif ($spam_count > 1) {
                    echo '<p>' . sprintf(__('You have %s spam comments.'), '<strong>' . $spam_count . '</strong>') . ' ' .
                    '<a href="' . $this->core->adminurl->get('admin.comments', ['status' => -2]) . '">' . __('Show them.') . '</a></p>';
                }
            }

            echo
            '<p>' .
            $this->core->formNonce() .
            '<input name="delete_all_spam" class="delete" type="submit" value="' . __('Delete all spams') . '" /></p>';

            # --BEHAVIOR-- adminCommentsSpamForm
            $this->core->behaviors->call('adminCommentsSpamForm', $this->core);

            echo '</form>';
        }

        $this->filter->display('admin.comments');

        # Show comments
        $this->catalog->display($this->filter->page, $this->filter->nb,
            '<form action="' . $this->core->adminurl->get('admin.comments') . '" method="post" id="form-comments">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
            Form::combo('action', $this->action->getCombo(),
                ['default' => $default, 'extra_html' => 'title="' . __('Actions') . '"']) .
            $this->core->formNonce() .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            $this->core->adminurl->getHiddenFormFields('admin.comments', $this->filter->values(true)) .
            '</div>' .

            '</form>',
            $this->filter->show(),
            ($this->filter->show() || ($this->filter->status == -2)),
            $this->core->auth->check('contentadmin', $this->core->blog->id)
        );
    }
}
