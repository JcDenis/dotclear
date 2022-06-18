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
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Process\Admin\Page\AbstractPage;
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

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        // Adding comment (comming from post form, comments tab)
        if (!GPC::post()->empty('add') && !GPC::post()->empty('post_id')) {
            try {
                $param = new Param();
                $param->set('post_id', GPC::post()->int('post_id'));
                $param->set('post_type', '');

                $rs = App::core()->blog()->posts()->getPosts(param: $param);
                if ($rs->isEmpty()) {
                    throw new AdminException(__('Entry does not exist.'));
                }

                $cur = App::core()->con()->openCursor(App::core()->prefix() . 'comment');

                $cur->setField('comment_author', GPC::post()->string('comment_author'));
                $cur->setField('comment_email', Html::clean(GPC::post()->string('comment_email')));
                $cur->setField('comment_site', Html::clean(GPC::post()->string('comment_site')));
                $cur->setField('comment_content', Html::filter(GPC::post()->string('comment_content')));
                $cur->setField('post_id', GPC::post()->int('post_id'));

                // --BEHAVIOR-- adminBeforeCommentCreate
                App::core()->behavior('adminBeforeCommentCreate')->call($cur);

                $this->comment_id = App::core()->blog()->comments()->createComment(cursor: $cur);

                // --BEHAVIOR-- adminAfterCommentCreate
                App::core()->behavior('adminAfterCommentCreate')->call($cur, $this->comment_id);

                App::core()->notice()->addSuccessNotice(__('Comment has been successfully created.'));
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
            Http::redirect(App::core()->posttype()->getPostAdminURL(type: $rs->field('post_type'), id: $rs->field('post_id')) . '&co=1');
        }

        $rs         = null;
        $post_id    = '';
        $post_type  = '';
        $post_title = '';

        if (!GPC::request()->empty('id')) {
            try {
                $param = new Param();
                $param->set('comment_id', GPC::request()->int('id'));
                $rs = App::core()->blog()->comments()->getComments(param: $param);
                if (!$rs->isEmpty()) {
                    $this->comment_id      = $rs->integer('comment_id');
                    $post_id               = $rs->integer('post_id');
                    $post_type             = $rs->field('post_type');
                    $post_title            = $rs->field('post_title');
                    $this->comment_dt      = $rs->field('comment_dt');
                    $this->comment_author  = $rs->field('comment_author');
                    $this->comment_email   = $rs->field('comment_email');
                    $this->comment_site    = $rs->field('comment_site');
                    $this->comment_content = $rs->field('comment_content');
                    $this->comment_ip      = $rs->field('comment_ip');
                    $this->comment_status  = $rs->integer('comment_status');
                }
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        if (!$this->comment_id && !App::core()->error()->flag()) {
            App::core()->error()->add(__('No comments'));
        }

        $this->commnet_can_edit = $this->commnet_can_delete = $this->commnet_can_publish = false;
        if (!App::core()->error()->flag() && isset($rs)) {
            $this->commnet_can_edit = $this->commnet_can_delete = $this->commnet_can_publish = App::core()->user()->check('contentadmin', App::core()->blog()->id);

            if (!App::core()->user()->check('contentadmin', App::core()->blog()->id) && App::core()->user()->userID() == $rs->field('user_id')) {
                $this->commnet_can_edit = true;
                if (App::core()->user()->check('delete', App::core()->blog()->id)) {
                    $this->commnet_can_delete = true;
                }
                if (App::core()->user()->check('publish', App::core()->blog()->id)) {
                    $this->commnet_can_publish = true;
                }
            }

            // update comment
            if (!GPC::post()->empty('update') && $this->commnet_can_edit) {
                $cur = App::core()->con()->openCursor(App::core()->prefix() . 'comment');

                $cur->setField('comment_author', GPC::post()->string('comment_author'));
                $cur->setField('comment_email', Html::clean(GPC::post()->string('comment_email')));
                $cur->setField('comment_site', Html::clean(GPC::post()->string('comment_site')));
                $cur->setField('comment_content', Html::filter(GPC::post()->string('comment_content')));

                if (GPC::post()->isset('comment_status')) {
                    $cur->setField('comment_status', GPC::post()->int('comment_status'));
                }

                try {
                    // --BEHAVIOR-- adminBeforeCommentUpdate
                    App::core()->behavior('adminBeforeCommentUpdate')->call($cur, $this->comment_id);

                    App::core()->blog()->comments()->updateComment(id: $this->comment_id, cursor: $cur);

                    // --BEHAVIOR-- adminAfterCommentUpdate
                    App::core()->behavior('adminAfterCommentUpdate')->call($cur, $this->comment_id);

                    App::core()->notice()->addSuccessNotice(__('Comment has been successfully updated.'));
                    App::core()->adminurl()->redirect('admin.comment', ['id' => $this->comment_id]);
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }

            if (!GPC::post()->empty('delete') && $this->commnet_can_delete) {
                try {
                    // --BEHAVIOR-- adminBeforeCommentDelete
                    App::core()->behavior('adminBeforeCommentDelete')->call($this->comment_id);

                    App::core()->blog()->comments()->deleteComments(ids: new Integers($this->comment_id));

                    App::core()->notice()->addSuccessNotice(__('Comment has been successfully deleted.'));
                    Http::redirect(App::core()->posttype()->getPostAdminURL(type: $rs->field('post_type'), id: $rs->field('post_id')) . '&co=1');
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }

            if (!$this->commnet_can_edit) {
                App::core()->error()->add(__("You can't edit this comment."));
            }
        }

        if ($rs) {
            $this->commnet_post_url = $rs->getPostURL();
        }

        // Page setup
        $comment_editor = App::core()->user()->getOption('editor');

        $this
            ->setPageTitle(__('Edit comment'))
            ->setPageHelp('core_comments')
            ->setPageHead(
                App::core()->resource()->confirmClose('comment-form') .
                App::core()->resource()->load('_comment.js') .
                App::core()->behavior('adminPostEditor')->call($comment_editor['xhtml'], 'comment', ['#comment_content'], 'xhtml') .

                // --BEHAVIOR-- adminCommentHeaders
                App::core()->behavior('adminCommentHeaders')->call()
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                Html::escapeHTML($post_title)               => Html::escapeHTML(App::core()->posttype()->getPostAdminURL(type: $post_type, id: $post_id)) . ($this->comment_id ? '&amp;co=1#c' . $this->comment_id : ''),
                __('Edit comment')                          => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!$this->comment_id) {
            return;
        }

        if (!GPC::get()->empty('upd')) {
            App::core()->notice()->success(__('Comment has been successfully updated.'));
        }

        // Status combo
        $status_combo = App::core()->combo()->getCommentStatusesCombo();

        $comment_mailto = '';
        if ($this->comment_email) {
            $comment_mailto = '<a href="mailto:' . Html::escapeHTML($this->comment_email)
            . '?subject=' . rawurlencode(sprintf(__('Your comment on my blog %s'), App::core()->blog()->name))
            . '&amp;body='
            . rawurlencode(sprintf(__("Hi!\n\nYou wrote a comment on:\n%s\n\n\n"), $this->commnet_post_url))
            . '">' . __('Send an e-mail') . '</a>';
        }

        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="comment-form">' .
        '<div class="fieldset">' .
        '<h3>' . __('Information collected') . '</h3>';

        $show_ip = App::core()->user()->check('contentadmin', App::core()->blog()->id);
        if ($show_ip) {
            echo '<p>' . __('IP address:') . ' ' .
            '<a href="' . App::core()->adminurl()->get('admin.comments', ['ip' => $this->comment_ip]) . '">' . $this->comment_ip . '</a></p>';
        }

        echo '<p>' . __('Date:') . ' ' .
        Clock::str(format: __('%Y-%m-%d %H:%M'), date: $this->comment_dt, to: App::core()->timezone()) . '</p>' .
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
        // !App::core()->behavior('adminAfterCommentDesc')->call($rs) .

        '<p class="area"><label for="comment_content">' . __('Comment:') . '</label> ' .
        Form::textarea(
            'comment_content',
            50,
            10,
            [
                'default'    => Html::escapeHTML($this->comment_content),
                'extra_html' => 'lang="' . App::core()->user()->getInfo('user_lang') . '" spellcheck="true"',
            ]
        ) .
        '</p>' .

        '<p>' . Form::hidden('id', $this->comment_id) .
        App::core()->adminurl()->getHiddenFormFields('admin.comment', [], true) .
        '<input type="submit" accesskey="s" name="update" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';

        if ($this->commnet_can_delete) {
            echo ' <input type="submit" class="delete" name="delete" value="' . __('Delete') . '" />';
        }
        echo '</p>' .
            '</form>';
    }
}
