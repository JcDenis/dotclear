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
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->check('usage,contentadmin');

        if (!empty($_POST['delete_all_spam'])) {
            try {
                $core->blog->delJunkComments();
                $_SESSION['comments_del_spam'] = true;
                $core->adminurl->redirect('admin.comments');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        /* Filters
        -------------------------------------------------------- */
        $comment_filter = new CommentFilter($this->core);

        # get list params
        $params = $comment_filter->params();

        # lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'post_title'          => 'post_title',
            'comment_author'      => 'comment_author',
            'comment_spam_filter' => 'comment_spam_filter'];

        # --BEHAVIOR-- adminCommentsSortbyLexCombo
        $core->callBehavior('adminCommentsSortbyLexCombo', [& $sortby_lex]);

        $params['order'] = (array_key_exists($comment_filter->sortby, $sortby_lex) ?
            $this->core->con->lexFields($sortby_lex[$comment_filter->sortby]) :
            $comment_filter->sortby) . ' ' . $comment_filter->order;

        # default filter ? do not display spam
        if (!$comment_filter->show() && $comment_filter->status == '') {
            $params['comment_status_not'] = -2;
        }
        $params['no_content'] = true;

        /* Actions
        -------------------------------------------------------- */
        $combo_action = [];
        $default      = '';
        if ($this->core->auth->check('delete,contentadmin', $this->core->blog->id) && $comment_filter->status == -2) {
            $default = 'delete';
        }

        $comments_actions_page = new CommentAction($this->core, $this->core->adminurl->get('admin.comments'));

        if ($comments_actions_page->process()) {
            return;
        }

        /* List
        -------------------------------------------------------- */
        $comment_list = null;

        try {
            $comments     = $core->blog->getComments($params);
            $counter      = $core->blog->getComments($params, true);
            $comment_list = new CommentCatalog($this->core, $comments, $counter->f(0));
        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());
        }

        /* DISPLAY
        -------------------------------------------------------- */

        $this->open(__('Comments and trackbacks'),
            static::jsLoad('js/_comments.js') . $comment_filter->js(),
            $this->breadcrumb(
                [
                    Html::escapeHTML($this->core->blog->name) => '',
                    __('Comments and trackbacks')       => ''
                ])
        );
        if (!empty($_GET['upd'])) {
            static::success(__('Selected comments have been successfully updated.'));
        } elseif (!empty($_GET['del'])) {
            static::success(__('Selected comments have been successfully deleted.'));
        }

        if (!$core->error->flag()) {
            if (isset($_SESSION['comments_del_spam'])) {
                static::message(__('Spam comments have been successfully deleted.'));
                unset($_SESSION['comments_del_spam']);
            }

            $spam_count = $this->core->blog->getComments(['comment_status' => -2], true)->f(0);
            if ($spam_count > 0) {
                echo
                '<form action="' . $this->core->adminurl->get('admin.comments') . '" method="post" class="fieldset">';

                if (!$comment_filter->show() || ($comment_filter->status != -2)) {
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
                $this->core->callBehavior('adminCommentsSpamForm', $core);

                echo '</form>';
            }

            $comment_filter->display('admin.comments');

            # Show comments
            $comment_list->display($comment_filter->page, $comment_filter->nb,
                '<form action="' . $this->core->adminurl->get('admin.comments') . '" method="post" id="form-comments">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
                Form::combo('action', $comments_actions_page->getCombo(),
                    ['default' => $default, 'extra_html' => 'title="' . __('Actions') . '"']) .
                $this->core->formNonce() .
                '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
                $this->core->adminurl->getHiddenFormFields('admin.comments', $comment_filter->values(true)) .
                '</div>' .

                '</form>',
                $comment_filter->show(),
                ($comment_filter->show() || ($comment_filter->status == -2)),
                $this->core->auth->check('contentadmin', $this->core->blog->id)
            );
        }

        $this->helpBlock('core_comments');
        $this->close();
    }
}
