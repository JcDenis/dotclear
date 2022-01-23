<?php
/**
 * @class Dotclear\Admin\Page\Comment
 * @brief Dotclear admin blog page
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

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Comment extends Page
{
    private $comment_id          = null;
    private $comment_dt          = '';
    private $comment_author      = '';
    private $comment_email       = '';
    private $comment_site        = '';
    private $comment_content     = '';
    private $comment_ip          = '';
    private $comment_status      = '';
    private $comment_trackback   = 0;
    private $post_url            = '';
    private $can_edit            = false;
    private $can_delete          = false;
    private $can_publish         = false;

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        # Adding comment (comming from post form, comments tab)
        if (!empty($_POST['add']) && !empty($_POST['post_id'])) {
            try {
                $rs = $this->core->blog->getPosts(['post_id' => $_POST['post_id'], 'post_type' => '']);

                if ($rs->isEmpty()) {
                    throw new AdminException(__('Entry does not exist.'));
                }

                $cur = $this->core->con->openCursor($this->core->prefix . 'comment');

                $cur->comment_author  = $_POST['comment_author'];
                $cur->comment_email   = Html::clean($_POST['comment_email']);
                $cur->comment_site    = Html::clean($_POST['comment_site']);
                $cur->comment_content = $this->core->HTMLfilter($_POST['comment_content']);
                $cur->post_id         = (integer) $_POST['post_id'];

                # --BEHAVIOR-- adminBeforeCommentCreate
                $this->core->behaviors->call('adminBeforeCommentCreate', $cur);

                $this->comment_id = $this->core->blog->addComment($cur);

                # --BEHAVIOR-- adminAfterCommentCreate
                $this->core->behaviors->call('adminAfterCommentCreate', $cur, $this->comment_id);

                $this->core->notices->addSuccessNotice(__('Comment has been successfully created.'));
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
            Http::redirect($this->core->getPostAdminURL($rs->post_type, $rs->post_id, false) . '&co=1');
        }

        $rs         = null;
        $post_id    = '';
        $post_type  = '';
        $post_title = '';

        if (!empty($_REQUEST['id'])) {
            $params['comment_id'] = $_REQUEST['id'];

            try {
                $rs = $this->core->blog->getComments($params);
                if (!$rs->isEmpty()) {
                    $this->comment_id          = $rs->comment_id;
                    $post_id             = $rs->post_id;
                    $post_type           = $rs->post_type;
                    $post_title          = $rs->post_title;
                    $this->comment_dt          = $rs->comment_dt;
                    $this->comment_author      = $rs->comment_author;
                    $this->comment_email       = $rs->comment_email;
                    $this->comment_site        = $rs->comment_site;
                    $this->comment_content     = $rs->comment_content;
                    $this->comment_ip          = $rs->comment_ip;
                    $this->comment_status      = $rs->comment_status;
                    $this->comment_trackback   = (boolean) $rs->comment_trackback;
                }
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        if (!$this->comment_id && !$this->core->error->flag()) {
            $this->core->error->add(__('No comments'));
        }

        $this->can_edit = $this->can_delete = $this->can_publish = false;
        if (!$this->core->error->flag() && isset($rs)) {
            $this->can_edit = $this->can_delete = $this->can_publish = $this->core->auth->check('contentadmin', $this->core->blog->id);

            if (!$this->core->auth->check('contentadmin', $this->core->blog->id) && $this->core->auth->userID() == $rs->user_id) {
                $this->can_edit = true;
                if ($this->core->auth->check('delete', $this->core->blog->id)) {
                    $this->can_delete = true;
                }
                if ($this->core->auth->check('publish', $this->core->blog->id)) {
                    $this->can_publish = true;
                }
            }

            # update comment
            if (!empty($_POST['update']) && $this->can_edit) {
                $cur = $this->core->con->openCursor($this->core->prefix . 'comment');

                $cur->comment_author  = $_POST['comment_author'];
                $cur->comment_email   = Html::clean($_POST['comment_email']);
                $cur->comment_site    = Html::clean($_POST['comment_site']);
                $cur->comment_content = $this->core->HTMLfilter($_POST['comment_content']);

                if (isset($_POST['comment_status'])) {
                    $cur->comment_status = (integer) $_POST['comment_status'];
                }

                try {
                    # --BEHAVIOR-- adminBeforeCommentUpdate
                    $this->core->behaviors->call('adminBeforeCommentUpdate', $cur, $this->comment_id);

                    $this->core->blog->updComment($this->comment_id, $cur);

                    # --BEHAVIOR-- adminAfterCommentUpdate
                    $this->core->behaviors->call('adminAfterCommentUpdate', $cur, $this->comment_id);

                    $this->core->notices->addSuccessNotice(__('Comment has been successfully updated.'));
                    $this->core->adminurl->redirect('admin.comment', ['id' => $this->comment_id]);
                } catch (Exception $e) {
                    $this->core->error->add($e->getMessage());
                }
            }

            if (!empty($_POST['delete']) && $this->can_delete) {
                try {
                    # --BEHAVIOR-- adminBeforeCommentDelete
                    $this->core->behaviors->call('adminBeforeCommentDelete', $this->comment_id);

                    $this->core->blog->delComment($this->comment_id);

                    $this->core->notices->addSuccessNotice(__('Comment has been successfully deleted.'));
                    Http::redirect($this->core->getPostAdminURL($rs->post_type, $rs->post_id) . '&co=1');
                } catch (Exception $e) {
                    $this->core->error->add($e->getMessage());
                }
            }

            if (!$this->can_edit) {
                $this->core->error->add(__("You can't edit this comment."));
            }
        }

        if ($rs) {
            $this->post_url = $rs->getPostURL();
        }

        # Page setup
        $comment_editor = $this->core->auth->getOption('editor');

        $this
            ->setPageTitle(__('Edit comment'))
            ->setPageHelp('core_comments')
            ->setPageHead(
                static::jsConfirmClose('comment-form') .
                static::jsLoad('js/_comment.js') .
                $this->core->behaviors->call('adminPostEditor', $comment_editor['xhtml'], 'comment', ['#comment_content'], 'xhtml') .

                # --BEHAVIOR-- adminCommentHeaders
                $this->core->behaviors->call('adminCommentHeaders')
            )
            ->setPageBreadcrumb([
                Html::escapeHTML($this->core->blog->name) => '',
                Html::escapeHTML($post_title)       => $this->core->getPostAdminURL($post_type, $post_id) . ($this->comment_id ? '&amp;co=1#c' . $this->comment_id : ''),
                __('Edit comment')                  => ''
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
            static::success(__('Comment has been successfully updated.'));
        }

        # Status combo
        $status_combo = $this->core->combos->getCommentStatusesCombo();

        $comment_mailto = '';
        if ($this->comment_email) {
            $comment_mailto = '<a href="mailto:' . Html::escapeHTML($this->comment_email)
            . '?subject=' . rawurlencode(sprintf(__('Your comment on my blog %s'), $this->core->blog->name))
            . '&amp;body='
            . rawurlencode(sprintf(__("Hi!\n\nYou wrote a comment on:\n%s\n\n\n"), $this->post_url))
            . '">' . __('Send an e-mail') . '</a>';
        }

        echo
        '<form action="' . $this->core->adminurl->get('admin.comment') . '" method="post" id="comment-form">' .
        '<div class="fieldset">' .
        '<h3>' . __('Information collected') . '</h3>';

        $show_ip = $this->core->auth->check('contentadmin', $this->core->blog->id);
        if ($show_ip) {
            echo
            '<p>' . __('IP address:') . ' ' .
            '<a href="' . $this->core->adminurl->get('admin.comments', ['ip' => $this->comment_ip]) . '">' . $this->comment_ip . '</a></p>';
        }

        echo
        '<p>' . __('Date:') . ' ' .
        Dt::dt2str(__('%Y-%m-%d %H:%M'), $this->comment_dt) . '</p>' .
        '</div>' .

        '<h3>' . __('Comment submitted') . '</h3>' .
        '<p><label for="comment_author" class="required"><abbr title="' . __('Required field') . '">*</abbr>' . __('Author:') . '</label>' .
        Form::field('comment_author', 30, 255, [
            'default'    => Html::escapeHTML($this->comment_author),
            'extra_html' => 'required placeholder="' . __('Author') . '"'
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
        Form::combo('comment_status', $status_combo,
            ['default' => $this->comment_status, 'disabled' => !$this->can_publish]) .
        '</p>' .

        # --BEHAVIOR-- adminAfterCommentDesc
        //!$this->core->behaviors->call('adminAfterCommentDesc', $rs) .

        '<p class="area"><label for="comment_content">' . __('Comment:') . '</label> ' .
        Form::textarea('comment_content', 50, 10,
            [
                'default'    => Html::escapeHTML($this->comment_content),
                'extra_html' => 'lang="' . $this->core->auth->getInfo('user_lang') . '" spellcheck="true"'
            ]) .
        '</p>' .

        '<p>' . Form::hidden('id', $this->comment_id) .
        $this->core->formNonce() .
        '<input type="submit" accesskey="s" name="update" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';

        if ($this->can_delete) {
            echo ' <input type="submit" class="delete" name="delete" value="' . __('Delete') . '" />';
        }
        echo
            '</p>' .
            '</form>';
    }
}
