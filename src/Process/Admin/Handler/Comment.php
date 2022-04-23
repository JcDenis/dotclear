<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Comment
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Dt;
use Exception;

/**
 * Admin comment page.
 *
 * @ingroup  Admin Comment Handler
 */
class Comment extends AbstractPage
{
    private $comment_id;
    private $comment_dt              = '';
    private $comment_author          = '';
    private $comment_email           = '';
    private $comment_site            = '';
    private $comment_content         = '';
    private $comment_ip              = '';
    private $comment_status          = '';
    private $commnet_post_url        = '';
    private $commnet_can_edit        = false;
    private $commnet_can_delete      = false;
    private $commnet_can_publish     = false;

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        // Adding comment (comming from post form, comments tab)
        if (!empty($_POST['add']) && !empty($_POST['post_id'])) {
            try {
                $rs = dotclear()->blog()->posts()->getPosts(['post_id' => $_POST['post_id'], 'post_type' => '']);

                if ($rs->isEmpty()) {
                    throw new AdminException(__('Entry does not exist.'));
                }

                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');

                $cur->setField('comment_author', $_POST['comment_author']);
                $cur->setField('comment_email', Html::clean($_POST['comment_email']));
                $cur->setField('comment_site', Html::clean($_POST['comment_site']));
                $cur->setField('comment_content', Html::filter($_POST['comment_content']));
                $cur->setField('post_id', (int) $_POST['post_id']);

                // --BEHAVIOR-- adminBeforeCommentCreate
                dotclear()->behavior()->call('adminBeforeCommentCreate', $cur);

                $this->comment_id = dotclear()->blog()->comments()->addComment($cur);

                // --BEHAVIOR-- adminAfterCommentCreate
                dotclear()->behavior()->call('adminAfterCommentCreate', $cur, $this->comment_id);

                dotclear()->notice()->addSuccessNotice(__('Comment has been successfully created.'));
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
            Http::redirect(dotclear()->posttype()->getPostAdminURL($rs->f('post_type'), $rs->f('post_id'), false) . '&co=1');
        }

        $rs         = null;
        $post_id    = '';
        $post_type  = '';
        $post_title = '';

        if (!empty($_REQUEST['id'])) {
            try {
                $rs = dotclear()->blog()->comments()->getComments(['comment_id' => $_REQUEST['id']]);
                if (!$rs->isEmpty()) {
                    $this->comment_id      = $rs->fInt('comment_id');
                    $post_id               = $rs->fInt('post_id');
                    $post_type             = $rs->f('post_type');
                    $post_title            = $rs->f('post_title');
                    $this->comment_dt      = $rs->f('comment_dt');
                    $this->comment_author  = $rs->f('comment_author');
                    $this->comment_email   = $rs->f('comment_email');
                    $this->comment_site    = $rs->f('comment_site');
                    $this->comment_content = $rs->f('comment_content');
                    $this->comment_ip      = $rs->f('comment_ip');
                    $this->comment_status  = $rs->fInt('comment_status');
                }
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        if (!$this->comment_id && !dotclear()->error()->flag()) {
            dotclear()->error()->add(__('No comments'));
        }

        $this->commnet_can_edit = $this->commnet_can_delete = $this->commnet_can_publish = false;
        if (!dotclear()->error()->flag() && isset($rs)) {
            $this->commnet_can_edit = $this->commnet_can_delete = $this->commnet_can_publish = dotclear()->user()->check('contentadmin', dotclear()->blog()->id);

            if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id) && dotclear()->user()->userID() == $rs->f('user_id')) {
                $this->commnet_can_edit = true;
                if (dotclear()->user()->check('delete', dotclear()->blog()->id)) {
                    $this->commnet_can_delete = true;
                }
                if (dotclear()->user()->check('publish', dotclear()->blog()->id)) {
                    $this->commnet_can_publish = true;
                }
            }

            // update comment
            if (!empty($_POST['update']) && $this->commnet_can_edit) {
                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');

                $cur->setField('comment_author', $_POST['comment_author']);
                $cur->setField('comment_email', Html::clean($_POST['comment_email']));
                $cur->setField('comment_site', Html::clean($_POST['comment_site']));
                $cur->setField('comment_content', Html::filter($_POST['comment_content']));

                if (isset($_POST['comment_status'])) {
                    $cur->setField('comment_status', (int) $_POST['comment_status']);
                }

                try {
                    // --BEHAVIOR-- adminBeforeCommentUpdate
                    dotclear()->behavior()->call('adminBeforeCommentUpdate', $cur, $this->comment_id);

                    dotclear()->blog()->comments()->updComment($this->comment_id, $cur);

                    // --BEHAVIOR-- adminAfterCommentUpdate
                    dotclear()->behavior()->call('adminAfterCommentUpdate', $cur, $this->comment_id);

                    dotclear()->notice()->addSuccessNotice(__('Comment has been successfully updated.'));
                    dotclear()->adminurl()->redirect('admin.comment', ['id' => $this->comment_id]);
                } catch (Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }

            if (!empty($_POST['delete']) && $this->commnet_can_delete) {
                try {
                    // --BEHAVIOR-- adminBeforeCommentDelete
                    dotclear()->behavior()->call('adminBeforeCommentDelete', $this->comment_id);

                    dotclear()->blog()->comments()->delComment($this->comment_id);

                    dotclear()->notice()->addSuccessNotice(__('Comment has been successfully deleted.'));
                    Http::redirect(dotclear()->posttype()->getPostAdminURL($rs->f('post_type'), $rs->f('post_id')) . '&co=1');
                } catch (Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }

            if (!$this->commnet_can_edit) {
                dotclear()->error()->add(__("You can't edit this comment."));
            }
        }

        if ($rs) {
            $this->commnet_post_url = $rs->getPostURL();
        }

        // Page setup
        $comment_editor = dotclear()->user()->getOption('editor');

        $this
            ->setPageTitle(__('Edit comment'))
            ->setPageHelp('core_comments')
            ->setPageHead(
                dotclear()->resource()->confirmClose('comment-form') .
                dotclear()->resource()->load('_comment.js') .
                dotclear()->behavior()->call('adminPostEditor', $comment_editor['xhtml'], 'comment', ['#comment_content'], 'xhtml') .

                // --BEHAVIOR-- adminCommentHeaders
                dotclear()->behavior()->call('adminCommentHeaders')
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                Html::escapeHTML($post_title)              => dotclear()->posttype()->getPostAdminURL($post_type, $post_id) . ($this->comment_id ? '&amp;co=1#c' . $this->comment_id : ''),
                __('Edit comment')                         => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!$this->comment_id) {
            return;
        }

        if (!empty($_GET['upd'])) {
            dotclear()->notice()->success(__('Comment has been successfully updated.'));
        }

        // Status combo
        $status_combo = dotclear()->combo()->getCommentStatusesCombo();

        $comment_mailto = '';
        if ($this->comment_email) {
            $comment_mailto = '<a href="mailto:' . Html::escapeHTML($this->comment_email)
            . '?subject=' . rawurlencode(sprintf(__('Your comment on my blog %s'), dotclear()->blog()->name))
            . '&amp;body='
            . rawurlencode(sprintf(__("Hi!\n\nYou wrote a comment on:\n%s\n\n\n"), $this->commnet_post_url))
            . '">' . __('Send an e-mail') . '</a>';
        }

        echo '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="comment-form">' .
        '<div class="fieldset">' .
        '<h3>' . __('Information collected') . '</h3>';

        $show_ip = dotclear()->user()->check('contentadmin', dotclear()->blog()->id);
        if ($show_ip) {
            echo '<p>' . __('IP address:') . ' ' .
            '<a href="' . dotclear()->adminurl()->get('admin.comments', ['ip' => $this->comment_ip]) . '">' . $this->comment_ip . '</a></p>';
        }

        echo '<p>' . __('Date:') . ' ' .
        Dt::dt2str(__('%Y-%m-%d %H:%M'), $this->comment_dt) . '</p>' .
        '</div>' .

        '<h3>' . __('Comment submitted') . '</h3>' .
        '<p><label for="comment_author" class="required"><abbr title="' . __('Required field') . '">*</abbr>' . __('Author:') . '</label>' .
        Form::field('comment_author', 30, 255, [
            'default'    => Html::escapeHTML($this->comment_author),
            'extra_html' => 'required placeholder="' . __('Author') . '"',
        ]) .
        '</p>' .

        '<p><label for="comment_email">' . __('Email:') . '</label>' .
        Form::email('comment_email', 30, 255, Html::escapeHTML($this->comment_email)) .
        '<span>' . $comment_mailto . '</span>' .
        '</p>' .

        '<p><label for="comment_site">' . __('Web site:') . '</label>' .
        Form::url('comment_site', 30, 255, Html::escapeHTML($this->comment_site)) .
        '</p>' .

        '<p><label for="comment_status">' . __('Status:') . '</label>' .
        Form::combo(
            'comment_status',
            $status_combo,
            ['default' => $this->comment_status, 'disabled' => !$this->commnet_can_publish]
        ) .
        '</p>' .

        // --BEHAVIOR-- adminAfterCommentDesc
        // !dotclear()->behavior()->call('adminAfterCommentDesc', $rs) .

        '<p class="area"><label for="comment_content">' . __('Comment:') . '</label> ' .
        Form::textarea(
            'comment_content',
            50,
            10,
            [
                'default'    => Html::escapeHTML($this->comment_content),
                'extra_html' => 'lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"',
            ]
        ) .
        '</p>' .

        '<p>' . Form::hidden('id', $this->comment_id) .
        dotclear()->adminurl()->getHiddenFormFields('admin.comment', [], true) .
        '<input type="submit" accesskey="s" name="update" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';

        if ($this->commnet_can_delete) {
            echo ' <input type="submit" class="delete" name="delete" value="' . __('Delete') . '" />';
        }
        echo '</p>' .
            '</form>';
    }
}
