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
use Dotclear\Admin\Combos;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Comment extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->check('usage,contentadmin');

        $show_ip = $core->auth->check('contentadmin', $core->blog->id);

        $comment_id          = null;
        $comment_dt          = '';
        $comment_author      = '';
        $comment_email       = '';
        $comment_site        = '';
        $comment_content     = '';
        $comment_ip          = '';
        $comment_status      = '';
        $comment_trackback   = 0;
        $comment_spam_status = '';

        $comment_editor = $core->auth->getOption('editor');

        # Status combo
        $status_combo = Combos::getCommentStatusesCombo();

        # Adding comment (comming from post form, comments tab)
        if (!empty($_POST['add']) && !empty($_POST['post_id'])) {
            try {
                $rs = $core->blog->getPosts(['post_id' => $_POST['post_id'], 'post_type' => '']);

                if ($rs->isEmpty()) {
                    throw new AdminException(__('Entry does not exist.'));
                }

                $cur = $core->con->openCursor($core->prefix . 'comment');

                $cur->comment_author  = $_POST['comment_author'];
                $cur->comment_email   = Html::clean($_POST['comment_email']);
                $cur->comment_site    = Html::clean($_POST['comment_site']);
                $cur->comment_content = $core->HTMLfilter($_POST['comment_content']);
                $cur->post_id         = (integer) $_POST['post_id'];

                # --BEHAVIOR-- adminBeforeCommentCreate
                $core->behaviors->call('adminBeforeCommentCreate', $cur);

                $comment_id = $core->blog->addComment($cur);

                # --BEHAVIOR-- adminAfterCommentCreate
                $core->behaviors->call('adminAfterCommentCreate', $cur, $comment_id);

                static::addSuccessNotice(__('Comment has been successfully created.'));
            } catch (Exception $e) {
                $core->error->add($e->getMessage());
            }
            Http::redirect($core->getPostAdminURL($rs->post_type, $rs->post_id, false) . '&co=1');
        }

        $rs         = null;
        $post_id    = '';
        $post_type  = '';
        $post_title = '';

        if (!empty($_REQUEST['id'])) {
            $params['comment_id'] = $_REQUEST['id'];

            try {
                $rs = $core->blog->getComments($params);
                if (!$rs->isEmpty()) {
                    $comment_id          = $rs->comment_id;
                    $post_id             = $rs->post_id;
                    $post_type           = $rs->post_type;
                    $post_title          = $rs->post_title;
                    $comment_dt          = $rs->comment_dt;
                    $comment_author      = $rs->comment_author;
                    $comment_email       = $rs->comment_email;
                    $comment_site        = $rs->comment_site;
                    $comment_content     = $rs->comment_content;
                    $comment_ip          = $rs->comment_ip;
                    $comment_status      = $rs->comment_status;
                    $comment_trackback   = (boolean) $rs->comment_trackback;
                    $comment_spam_status = $rs->comment_spam_status;
                }
            } catch (Exception $e) {
                $core->error->add($e->getMessage());
            }
        }

        if (!$comment_id && !$core->error->flag()) {
            $core->error->add(__('No comments'));
        }

        $can_edit = $can_delete = $can_publish = false;
        if (!$core->error->flag() && isset($rs)) {
            $can_edit = $can_delete = $can_publish = $core->auth->check('contentadmin', $core->blog->id);

            if (!$core->auth->check('contentadmin', $core->blog->id) && $core->auth->userID() == $rs->user_id) {
                $can_edit = true;
                if ($core->auth->check('delete', $core->blog->id)) {
                    $can_delete = true;
                }
                if ($core->auth->check('publish', $core->blog->id)) {
                    $can_publish = true;
                }
            }

            # update comment
            if (!empty($_POST['update']) && $can_edit) {
                $cur = $core->con->openCursor($core->prefix . 'comment');

                $cur->comment_author  = $_POST['comment_author'];
                $cur->comment_email   = Html::clean($_POST['comment_email']);
                $cur->comment_site    = Html::clean($_POST['comment_site']);
                $cur->comment_content = $core->HTMLfilter($_POST['comment_content']);

                if (isset($_POST['comment_status'])) {
                    $cur->comment_status = (integer) $_POST['comment_status'];
                }

                try {
                    # --BEHAVIOR-- adminBeforeCommentUpdate
                    $core->behaviors->call('adminBeforeCommentUpdate', $cur, $comment_id);

                    $core->blog->updComment($comment_id, $cur);

                    # --BEHAVIOR-- adminAfterCommentUpdate
                    $core->behaviors->call('adminAfterCommentUpdate', $cur, $comment_id);

                    static::addSuccessNotice(__('Comment has been successfully updated.'));
                    $core->adminurl->redirect('admin.comment', ['id' => $comment_id]);
                } catch (Exception $e) {
                    $core->error->add($e->getMessage());
                }
            }

            if (!empty($_POST['delete']) && $can_delete) {
                try {
                    # --BEHAVIOR-- adminBeforeCommentDelete
                    $core->behaviors->call('adminBeforeCommentDelete', $comment_id);

                    $core->blog->delComment($comment_id);

                    static::addSuccessNotice(__('Comment has been successfully deleted.'));
                    Http::redirect($core->getPostAdminURL($rs->post_type, $rs->post_id) . '&co=1');
                } catch (Exception $e) {
                    $core->error->add($e->getMessage());
                }
            }

            if (!$can_edit) {
                $core->error->add(__("You can't edit this comment."));
            }
        }

        /* DISPLAY
        -------------------------------------------------------- */
        if ($comment_id) {
            $breadcrumb = $this->breadcrumb(
                [
                    Html::escapeHTML($core->blog->name) => '',
                    Html::escapeHTML($post_title)       => $core->getPostAdminURL($post_type, $post_id) . '&amp;co=1#c' . $comment_id,
                    __('Edit comment')                  => ''
                ]);
        } else {
            $breadcrumb = $this->breadcrumb(
                [
                    Html::escapeHTML($core->blog->name) => '',
                    Html::escapeHTML($post_title)       => $core->getPostAdminURL($post_type, $post_id),
                    __('Edit comment')                  => ''
                ]);
        }

        $this->open(__('Edit comment'),
            static::jsConfirmClose('comment-form') .
            static::jsLoad('js/_comment.js') .
            $core->behaviors->call('adminPostEditor', $comment_editor['xhtml'], 'comment', ['#comment_content'], 'xhtml') .
            # --BEHAVIOR-- adminCommentHeaders
            $core->behaviors->call('adminCommentHeaders'),
            $breadcrumb
        );

        if ($comment_id) {
            if (!empty($_GET['upd'])) {
                static::success(__('Comment has been successfully updated.'));
            }

            $comment_mailto = '';
            if ($comment_email) {
                $comment_mailto = '<a href="mailto:' . Html::escapeHTML($comment_email)
                . '?subject=' . rawurlencode(sprintf(__('Your comment on my blog %s'), $core->blog->name))
                . '&amp;body='
                . rawurlencode(sprintf(__("Hi!\n\nYou wrote a comment on:\n%s\n\n\n"), $rs->getPostURL()))
                . '">' . __('Send an e-mail') . '</a>';
            }

            echo
            '<form action="' . $core->adminurl->get('admin.comment') . '" method="post" id="comment-form">' .
            '<div class="fieldset">' .
            '<h3>' . __('Information collected') . '</h3>';

            if ($show_ip) {
                echo
                '<p>' . __('IP address:') . ' ' .
                '<a href="' . $core->adminurl->get('admin.comments', ['ip' => $comment_ip]) . '">' . $comment_ip . '</a></p>';
            }

            echo
            '<p>' . __('Date:') . ' ' .
            Dt::dt2str(__('%Y-%m-%d %H:%M'), $comment_dt) . '</p>' .
            '</div>' .

            '<h3>' . __('Comment submitted') . '</h3>' .
            '<p><label for="comment_author" class="required"><abbr title="' . __('Required field') . '">*</abbr>' . __('Author:') . '</label>' .
            Form::field('comment_author', 30, 255, [
                'default'    => Html::escapeHTML($comment_author),
                'extra_html' => 'required placeholder="' . __('Author') . '"'
            ]) .
            '</p>' .

            '<p><label for="comment_email">' . __('Email:') . '</label>' .
            Form::email('comment_email', 30, 255, Html::escapeHTML($comment_email)) .
            '<span>' . $comment_mailto . '</span>' .
            '</p>' .

            '<p><label for="comment_site">' . __('Web site:') . '</label>' .
            Form::url('comment_site', 30, 255, Html::escapeHTML($comment_site)) .
            '</p>' .

            '<p><label for="comment_status">' . __('Status:') . '</label>' .
            Form::combo('comment_status', $status_combo,
                ['default' => $comment_status, 'disabled' => !$can_publish]) .
            '</p>' .

            # --BEHAVIOR-- adminAfterCommentDesc
            $core->behaviors->call('adminAfterCommentDesc', $rs) .

            '<p class="area"><label for="comment_content">' . __('Comment:') . '</label> ' .
            Form::textarea('comment_content', 50, 10,
                [
                    'default'    => Html::escapeHTML($comment_content),
                    'extra_html' => 'lang="' . $core->auth->getInfo('user_lang') . '" spellcheck="true"'
                ]) .
            '</p>' .

            '<p>' . Form::hidden('id', $comment_id) .
            $core->formNonce() .
            '<input type="submit" accesskey="s" name="update" value="' . __('Save') . '" />' .
            ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';

            if ($can_delete) {
                echo ' <input type="submit" class="delete" name="delete" value="' . __('Delete') . '" />';
            }
            echo
                '</p>' .
                '</form>';
        }

        $this->helpBlock('core_comments');
        $this->close();
    }
}
