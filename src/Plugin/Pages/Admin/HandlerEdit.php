<?php
/**
 * @class Dotclear\Plugin\Pages\Admin\HandlerEdit
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

use ArrayObject;

use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\Action\CommentAction;
use Dotclear\Core\Trackback\Trackback;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class HandlerEdit extends AbstractPage
{
    private $post_id            = 0;
    private $post_dt            = '';
    private $post_format        = '';
    private $post_editor        = '';
    private $post_password      = '';
    private $post_url           = '';
    private $post_lang          = 'en';
    private $post_title         = '';
    private $post_excerpt       = '';
    private $post_excerpt_xhtml = '';
    private $post_content       = '';
    private $post_content_xhtml = '';
    private $post_notes         = '';
    private $post_status        = -2;
    private $post_position      = 0;
    private $post_open_comment  = false;
    private $post_open_tb       = false;
    private $post_selected      = false;
    private $post_media = [];

    private $can_view_page = true;
    private $can_view_ip   = false;
    private $can_edit_page = false;
    private $can_publish   = false;
    private $can_delete    = false;

    private $post = null;
    private $trackback = null;
    private $tb_urls    ='';
    private $tb_excerpt = '';
    private $comments_actions = null;

    private $next_link     = null;
    private $prev_link     = null;

    private $bad_dt = false;
    private $img_status = '';

    protected function getPermissions(): string|null|false
    {
        return 'pages,contentadmin';
    }

    protected function getActionInstance(): ?Action
    {
        $action =  new CommentAction(dotclear()->adminurl()->get('admin.plugin.Page', ['id' => $_REQUEST['id'] ?? ''], '&'));
        $action->setEnableRedirSelection(false);

        return $action;
    }

    protected function getPagePrepend(): ? bool
    {
        Dt::setTZ(dotclear()->user()->getInfo('user_tz'));

        $page_title = __('New post');
        $next_headlink = $prev_headlink = '';

        $this->post_format   = dotclear()->user()->getOption('post_format');
        $this->post_editor   = dotclear()->user()->getOption('editor');
        $this->post_lang     = dotclear()->user()->getInfo('user_lang');
        $this->post_status   = dotclear()->user()->getInfo('user_post_status');
        $this->can_edit_page = dotclear()->user()->check('pages,usage', dotclear()->blog()->id);
        $this->can_publish   = dotclear()->user()->check('pages,publish,contentadmin', dotclear()->blog()->id);
        $post_headlink = '<link rel="%s" title="%s" href="' . dotclear()->adminurl()->get('admin.plugin.Page', ['id' => '%s'], '&amp;', true) . '" />';
        $post_link     = '<a href="' . dotclear()->adminurl()->get('admin.plugin.Page', ['id' => '%s'], '&amp;', true) . '" title="%s">%s</a>';

        # If user can't publish
        if (!$this->can_publish) {
            $this->post_status = -2;
        }

        $img_status_pattern = '<img class="img_select_option" alt="%1$s" title="%1$s" src="?df=images/%2$s" />';

        # Get page informations
        if (!empty($_REQUEST['id'])) {
            $page_title = __('Edit page');

            $params['post_type'] = 'page';
            $params['post_id']   = $_REQUEST['id'];

            $this->post = dotclear()->blog()->posts()->getPosts($params);

            if ($this->post->isEmpty()) {
                dotclear()->error()->add(__('This page does not exist.'));
                $this->can_view_page = false;
            } else {
                $this->post_id            = (int) $this->post->post_id;
                $this->post_dt            = date('Y-m-d H:i', strtotime($this->post->post_dt));
                $this->post_format        = $this->post->post_format;
                $this->post_password      = $this->post->post_password;
                $this->post_url           = $this->post->post_url;
                $this->post_lang          = $this->post->post_lang;
                $this->post_title         = $this->post->post_title;
                $this->post_excerpt       = $this->post->post_excerpt;
                $this->post_excerpt_xhtml = $this->post->post_excerpt_xhtml;
                $this->post_content       = $this->post->post_content;
                $this->post_content_xhtml = $this->post->post_content_xhtml;
                $this->post_notes         = $this->post->post_notes;
                $this->post_status        = $this->post->post_status;
                $this->post_position      = (int) $this->post->post_position;
                $this->post_open_comment  = (bool) $this->post->post_open_comment;
                $this->post_open_tb       = (bool) $this->post->post_open_tb;
                $this->post_selected      = (bool) $this->post->post_selected;

                $this->can_edit_page = $this->post->isEditable();
                $this->can_delete    = $this->post->isDeletable();

                $next_rs = dotclear()->blog()->posts()->getNextPost($this->post, 1);
                $prev_rs = dotclear()->blog()->posts()->getNextPost($this->post, -1);

                if ($next_rs !== null) {
                    $this->next_link = sprintf(
                        $post_link,
                        $next_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        __('Next page') . '&nbsp;&#187;'
                    );
                    $next_headlink = sprintf(
                        $post_headlink,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        $next_rs->post_id
                    );
                }

                if ($prev_rs !== null) {
                    $this->prev_link = sprintf(
                        $post_link,
                        $prev_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        '&#171;&nbsp;' . __('Previous page')
                    );
                    $prev_headlink = sprintf(
                        $post_headlink,
                        'previous',
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        $prev_rs->post_id
                    );
                }

                if (!dotclear()->blog()->public_path) {
                    dotclear()->error()->add(
                        __('There is no writable root directory for the media manager. You should contact your administrator.')
                    );
                }
            }
        }

        # Format content
        if (!empty($_POST) && $this->can_edit_page) {
            $this->post_format  = $_POST['post_format'];
            $this->post_excerpt = $_POST['post_excerpt'];
            $this->post_content = $_POST['post_content'];

            $this->post_title = $_POST['post_title'];

            if (isset($_POST['post_status'])) {
                $this->post_status = (int) $_POST['post_status'];
            }

            if (empty($_POST['post_dt'])) {
                $this->post_dt = '';
            } else {
                try {
                    $this->post_dt = strtotime($_POST['post_dt']);
                    if ($this->post_dt == false || $this->post_dt == -1) {
                        $this->bad_dt = true;

                        throw new AdminException(__('Invalid publication date'));
                    }
                    $this->post_dt = date('Y-m-d H:i', $this->post_dt);
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }

            $this->post_open_comment = !empty($_POST['post_open_comment']);
            $this->post_open_tb      = !empty($_POST['post_open_tb']);
            $this->post_selected     = !empty($_POST['post_selected']);
            $this->post_lang         = $_POST['post_lang'];
            $this->post_password     = !empty($_POST['post_password']) ? $_POST['post_password'] : null;
            $this->post_position     = (int) $_POST['post_position'];
            $this->post_notes        = $_POST['post_notes'];

            if (isset($_POST['post_url'])) {
                $this->post_url = $_POST['post_url'];
            }

            dotclear()->blog()->posts()->setPostContent(
                $this->post_id,
                $this->post_format,
                $this->post_lang,
                $this->post_excerpt,
                $this->post_excerpt_xhtml,
                $this->post_content,
                $this->post_content_xhtml
            );
        }

        # Delete post
        if (!empty($_POST['delete']) && $this->can_delete) {
            try {
                # --BEHAVIOR-- adminBeforePostDelete
                dotclear()->behavior()->call('adminBeforePageDelete', $this->post_id);
                dotclear()->blog()->posts()->delPost($this->post_id);
                dotclear()->adminurl()->redirect('admin.plugin.Page');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Create or update page
        if (!empty($_POST) && !empty($_POST['save']) && $this->can_edit_page && !$this->bad_dt) {
            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

            # Magic tweak :)
            dotclear()->blog()->settings()->system->post_url_format = '{t}';

            $cur->post_type          = 'page';
            $cur->post_dt            = $this->post_dt ? date('Y-m-d H:i:00', strtotime($this->post_dt)) : '';
            $cur->post_format        = $this->post_format;
            $cur->post_password      = $this->post_password;
            $cur->post_lang          = $this->post_lang;
            $cur->post_title         = $this->post_title;
            $cur->post_excerpt       = $this->post_excerpt;
            $cur->post_excerpt_xhtml = $this->post_excerpt_xhtml;
            $cur->post_content       = $this->post_content;
            $cur->post_content_xhtml = $this->post_content_xhtml;
            $cur->post_notes         = $this->post_notes;
            $cur->post_status        = $this->post_status;
            $cur->post_position      = $this->post_position;
            $cur->post_open_comment  = (int) $this->post_open_comment;
            $cur->post_open_tb       = (int) $this->post_open_tb;
            $cur->post_selected      = (int) $this->post_selected;

            if (isset($_POST['post_url'])) {
                $cur->post_url = $this->post_url;
            }

            // Back to UTC in order to keep UTC datetime for creadt/upddt
            Dt::setTZ('UTC');

            # Update post
            if ($this->post_id) {
                try {
                    # --BEHAVIOR-- adminBeforePageUpdate
                    dotclear()->behavior()->call('adminBeforePageUpdate', $cur, $this->post_id);

                    dotclear()->blog()->posts()->updPost($this->post_id, $cur);

                    # --BEHAVIOR-- adminAfterPageUpdate
                    dotclear()->behavior()->call('adminAfterPageUpdate', $cur, $this->post_id);

                    dotclear()->adminurl()->redirect('admin.plugin.Page', ['id' => $this->post_id, 'upd' => 1]);
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            } else {
                $cur->user_id = dotclear()->user()->userID();

                try {
                    # --BEHAVIOR-- adminBeforePageCreate
                    dotclear()->behavior()->call('adminBeforePageCreate', $cur);

                    $return_id = dotclear()->blog()->posts()->addPost($cur);

                    # --BEHAVIOR-- adminAfterPageCreate
                    dotclear()->behavior()->call('adminAfterPageCreate', $cur, $return_id);

                    dotclear()->adminurl()->redirect('admin.plugin.Page', ['id' => $return_id, 'crea' => 1]);
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }
        }

        # Page setup
        $default_tab = 'edit-entry';
        if (!$this->can_edit_page) {
            $default_tab = '';
        }
        if (!empty($_GET['co'])) {
            $default_tab = 'comments';
        }

        if ($this->post_id) {
            switch ($this->post_status) {
                case 1:
                    $this->img_status = sprintf($img_status_pattern, __('Published'), 'check-on.png');

                    break;
                case 0:
                    $this->img_status = sprintf($img_status_pattern, __('Unpublished'), 'check-off.png');

                    break;
                case -1:
                    $this->img_status = sprintf($img_status_pattern, __('Scheduled'), 'scheduled.png');

                    break;
                case -2:
                    $this->img_status = sprintf($img_status_pattern, __('Pending'), 'check-wrn.png');

                    break;
                default:
                    $this->img_status = '';
            }
            $edit_entry_str  = __('&ldquo;%s&rdquo;');
            $page_title_edit = sprintf($edit_entry_str, Html::escapeHTML(trim(Html::clean($this->post_title)))) . ' ' . $this->img_status;
        } else {
            $page_title_edit = $page_title;
        }

        $this
            ->setPageHelp('page', 'core_wiki')
            ->setPageTitle($page_title . ' - ' . __('Pages'))
            ->setPageHead(
                dotclear()->resource()->modal() .
                dotclear()->resource()->json('pages_page', ['confirm_delete_post' => __('Are you sure you want to delete this page?')]) .
                dotclear()->resource()->load('_post.js') .
                dotclear()->resource()->load('page.js', 'Plugin', 'Pages')
        );

        if ($this->post_editor) {
            $p_edit = $c_edit = '';
            if (!empty($this->post_editor[$this->post_format])) {
                $p_edit = $this->post_editor[$this->post_format];
            }
            if (!empty($this->post_editor['xhtml'])) {
                $c_edit = $this->post_editor['xhtml'];
            }
            if ($p_edit == $c_edit) {
                $this->setPageHead(dotclear()->behavior()->call(
                    'adminPostEditor',
                    $p_edit,
                    'post',
                    ['#post_excerpt', '#post_content', '#comment_content'],
                    $this->post_format
                ));
            } else {
                $this->setPageHead(dotclear()->behavior()->call(
                    'adminPostEditor',
                    $p_edit,
                    'post',
                    ['#post_excerpt', '#post_content'],
                    $this->post_format
                ));
                $this->setPageHead(dotclear()->behavior()->call(
                    'adminPostEditor',
                    $c_edit,
                    'comment',
                    ['#comment_content'],
                    'xhtml'
                ));
            }
        }

        $this
            ->setPageHead(
                dotclear()->resource()->confirmClose('entry-form', 'comment-form') .
                # --BEHAVIOR-- adminPostHeaders
                dotclear()->behavior()->call('adminPageHeaders') .
                dotclear()->resource()->pageTabs($default_tab) .
                $next_headlink . "\n" . $prev_headlink
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Pages')                                => dotclear()->adminurl()->get('admin.plugin.Pages'),
                $page_title_edit                           => '',
            ], [
                'x-frame-allow' => dotclear()->blog()->url,
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        $status_combo = dotclear()->combo()->getPostStatusesCombo();

        $rs         = dotclear()->blog()->posts()->getLangs(['order' => 'asc']);
        $lang_combo = dotclear()->combo()->getLangsCombo($rs, true);

        $core_formaters    = dotclear()->formater()->getFormaters();
        $available_formats = ['' => ''];
        foreach ($core_formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $available_formats[$format] = $format;
            }
        }

        if (!empty($_GET['upd'])) {
           dotclear()->notice()->success(__('Page has been successfully updated.'));
        } elseif (!empty($_GET['crea'])) {
            dotclear()->notice()->success(__('Page has been successfully created.'));
        } elseif (!empty($_GET['attached'])) {
            dotclear()->notice()->success(__('File has been successfully attached.'));
        } elseif (!empty($_GET['rmattach'])) {
            dotclear()->notice()->success(__('Attachment has been successfully removed.'));
        }

        # XHTML conversion
        if (!empty($_GET['xconv'])) {
            $this->post_excerpt = $this->post_excerpt_xhtml;
            $this->post_content = $this->post_content_xhtml;
            $this->post_format  = 'xhtml';

            dotclear()->notice()->message(__('Don\'t forget to validate your XHTML conversion by saving your post.'));
        }

        if ($this->post_id && $this->post->post_status == 1) {
            echo '<p><a class="onblog_link outgoing" href="' . $this->post->getURL() . '" title="' . Html::escapeHTML(trim(Html::clean($this->post_title))) . '">' . __('Go to this page on the site') . ' <img src="?df=images/outgoing-link.svg" alt="" /></a></p>';
        }
        if ($this->post_id) {
            echo '<p class="nav_prevnext">';
            if ($this->prev_link) {
                echo $this->prev_link;
            }
            if ($this->next_link && $this->prev_link) {
                echo ' | ';
            }
            if ($this->next_link) {
                echo $this->next_link;
            }

            # --BEHAVIOR-- adminPostNavLinks
            dotclear()->behavior()->call('adminPageNavLinks', $this->post ?? null);

            echo '</p>';
        }

        # Exit if we cannot view page
        if (!$this->can_view_page) {
            return;
        }

        /* Post form if we can edit page
        -------------------------------------------------------- */
        if ($this->can_edit_page) {
            $sidebar_items = new ArrayObject([
                'status-box' => [
                    'title' => __('Status'),
                    'items' => [
                        'post_status' => '<p><label for="post_status">' . __('Page status') . '</label> ' .
                        Form::combo(
                            'post_status',
                            $status_combo,
                            ['default' => $this->post_status, 'disabled' => !$this->can_publish]
                        ) .
                        '</p>',
                        'post_dt' => '<p><label for="post_dt">' . __('Publication date and hour') . '</label>' .
                        Form::datetime('post_dt', [
                            'default' => Html::escapeHTML(Dt::str('%Y-%m-%dT%H:%M', strtotime($this->post_dt))),
                            'class'   => ($this->bad_dt ? 'invalid' : ''),
                        ]) .
                        '</p>',
                        'post_lang' => '<p><label for="post_lang">' . __('Page language') . '</label>' .
                        Form::combo('post_lang', $lang_combo, $this->post_lang) .
                        '</p>',
                        'post_format' => '<div>' .
                        '<h5 id="label_format"><label for="post_format" class="classic">' . __('Text formatting') . '</label></h5>' .
                        '<p>' . Form::combo('post_format', $available_formats, $this->post_format, 'maximal') . '</p>' .
                        '<p class="format_control control_wiki">' .
                        '<a id="convert-xhtml" class="button' . ($this->post_id && $this->post_format != 'wiki' ? ' hide' : '') .
                        '" href="' . dotclear()->adminurl()->get('admin.plugin.Page', ['id' => $this->post_id, 'xconv' => '1']) . '">' .
                        __('Convert to XHTML') . '</a></p></div>', ], ],
                'metas-box' => [
                    'title' => __('Filing'),
                    'items' => [
                        'post_position' => '<p><label for="post_position" class="classic">' . __('Page position') . '</label> ' .
                        Form::number('post_position', [
                            'default' => $this->post_position,
                        ]) .
                        '</p>', ], ],
                'options-box' => [
                    'title' => __('Options'),
                    'items' => [
                        'post_open_comment_tb' => '<div>' .
                        '<h5 id="label_comment_tb">' . __('Comments and trackbacks list') . '</h5>' .
                        '<p><label for="post_open_comment" class="classic">' .
                        Form::checkbox('post_open_comment', 1, $this->post_open_comment) . ' ' .
                        __('Accept comments') . '</label></p>' .
                        (dotclear()->blog()->settings()->system->allow_comments ?
                            ($this->isContributionAllowed($this->post_id, strtotime($this->post_dt), true) ?
                                '' :
                                '<p class="form-note warn">' .
                                __('Warning: Comments are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' .
                            __('Comments are not accepted on this blog so far.') . '</p>') .
                        '<p><label for="post_open_tb" class="classic">' .
                        Form::checkbox('post_open_tb', 1, $this->post_open_tb) . ' ' .
                        __('Accept trackbacks') . '</label></p>' .
                        (dotclear()->blog()->settings()->system->allow_trackbacks ?
                            ($this->isContributionAllowed($this->post_id, strtotime($this->post_dt), false) ?
                                '' :
                                '<p class="form-note warn">' .
                                __('Warning: Trackbacks are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' . __('Trackbacks are not accepted on this blog so far.') . '</p>') .
                        '</div>',
                        'post_hide' => '<p><label for="post_selected" class="classic">' . Form::checkbox('post_selected', 1, $this->post_selected) . ' ' .
                        __('Hide in widget Pages') . '</label>' .
                        '</p>',
                        'post_password' => '<p><label for="post_password">' . __('Password') . '</label>' .
                        Form::field('post_password', 10, 32, Html::escapeHTML($this->post_password), 'maximal') .
                        '</p>',
                        'post_url' => '<div class="lockable">' .
                        '<p><label for="post_url">' . __('Edit basename') . '</label>' .
                        Form::field('post_url', 10, 255, Html::escapeHTML($this->post_url), 'maximal') .
                        '</p>' .
                        '<p class="form-note warn">' .
                        __('Warning: If you set the URL manually, it may conflict with another page.') .
                        '</p></div>',
                    ], ], ]);
            $main_items = new ArrayObject(
                [
                    'post_title' => '<p class="col">' .
                    '<label class="required no-margin bold" for="post_title"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label>' .
                    Form::field('post_title', 20, 255, [
                        'default'    => Html::escapeHTML($this->post_title),
                        'class'      => 'maximal',
                        'extra_html' => 'required placeholder="' . __('Title') . '" lang="' . $this->post_lang . '" spellcheck="true"',
                    ]) .
                    '</p>',

                    'post_excerpt' => '<p class="area" id="excerpt-area"><label for="post_excerpt" class="bold">' . __('Excerpt:') . ' <span class="form-note">' .
                    __('Introduction to the page.') . '</span></label> ' .
                    Form::textarea(
                        'post_excerpt',
                        50,
                        5,
                        [
                            'default'    => Html::escapeHTML($this->post_excerpt),
                            'extra_html' => 'lang="' . $this->post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',

                    'post_content' => '<p class="area" id="content-area"><label class="required bold" ' .
                    'for="post_content"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Content:') . '</label> ' .
                    Form::textarea(
                        'post_content',
                        50,
                        dotclear()->user()->getOption('edit_size'),
                        [
                            'default'    => Html::escapeHTML($this->post_content),
                            'extra_html' => 'required placeholder="' . __('Content') . '" lang="' . $this->post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',

                    'post_notes' => '<p class="area" id="notes-area"><label for="post_notes" class="bold">' . __('Personal notes:') . ' <span class="form-note">' .
                    __('Unpublished notes.') . '</span></label>' .
                    Form::textarea(
                        'post_notes',
                        50,
                        5,
                        [
                            'default'    => Html::escapeHTML($this->post_notes),
                            'extra_html' => 'lang="' . $this->post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',
                ]
            );

            # --BEHAVIOR-- adminPostFormItems
            dotclear()->behavior()->call('adminPageFormItems', $main_items, $sidebar_items, $this->post ?? null);

            echo '<div class="multi-part" title="' . ($this->post_id ? __('Edit page') : __('New page')) .
            sprintf(' &rsaquo; %s', $this->post_format) . '" id="edit-entry">';
            echo '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="entry-form">';

            echo '<div id="entry-wrapper">';
            echo '<div id="entry-content"><div class="constrained">';
            echo '<h3 class="out-of-screen-if-js">' . __('Edit page') . '</h3>';

            foreach ($main_items as $id => $item) {
                echo $item;
            }

            # --BEHAVIOR-- adminPageForm
            dotclear()->behavior()->call('adminPageForm', $this->post ?? null);

            echo
            '<p class="border-top">' .
            ($this->post_id ? Form::hidden('id', $this->post_id) : '') .
            '<input type="submit" value="' . __('Save') . ' (s)" ' .
                'accesskey="s" name="save" /> ';

            if ($this->post_id) {
                $preview_url = dotclear()->blog()->getURLFor(
                    'pagespreview',
                    dotclear()->user()->userID() . '/' .
                    Http::browserUID(dotclear()->config()->master_key . dotclear()->user()->userID() . dotclear()->user()->cryptLegacy(dotclear()->user()->userID())) .
                    '/' . $this->post->post_url
                );

                // Prevent browser caching on preview
                $preview_url .= (parse_url($preview_url, PHP_URL_QUERY) ? '&' : '?') . 'rand=' . md5((string) rand());

                $blank_preview = dotclear()->user()->preference()->interface->blank_preview;

                $preview_class  = $blank_preview ? '' : ' modal';
                $preview_target = $blank_preview ? '' : ' target="_blank"';

                echo '<a id="post-preview" href="' . $preview_url . '" class="button' . $preview_class . '" accesskey="p"' . $preview_target . '>' . __('Preview') . ' (p)' . '</a>';
                echo ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';
            } else {
                echo
                '<a id="post-cancel" href="' . dotclear()->adminurl()->get('admin.home') . '" class="button" accesskey="c">' . __('Cancel') . ' (c)</a>';
            }

            echo($this->can_delete ? ' <input type="submit" class="delete" value="' . __('Delete') . '" name="delete" />' : '') .
            dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Page', [], true) .
                '</p>';

            echo '</div></div>'; // End #entry-content
            echo '</div>';       // End #entry-wrapper

            echo '<div id="entry-sidebar" role="complementary">';

            foreach ($sidebar_items as $id => $c) {
                echo '<div id="' . $id . '" class="sb-box">' .
                    '<h4>' . $c['title'] . '</h4>';
                foreach ($c['items'] as $e_name => $e_content) {
                    echo $e_content;
                }
                echo '</div>';
            }

            # --BEHAVIOR-- adminPageFormSidebar
            dotclear()->behavior()->call('adminPageFormSidebar', $this->post ?? null);

            echo '</div>'; // End #entry-sidebar

            echo '</form>';

            # --BEHAVIOR-- adminPostForm
            dotclear()->behavior()->call('adminPageAfterForm', $this->post ?? null);

            echo '</div>'; // End

            if ($this->post_id && !empty($this->post_media)) {
                echo
                '<form action="' . $core->adminurl->root() . '" id="attachment-remove-hide" method="post">' .
                '<div>' .
                dotclear()->adminurl()->getHiddenFormFields('admin.post.media', [
                    'post_id'  => $this->post_id,
                    'media_id' => '',
                    'remove'   => 1,
                ], true) .
                '</div></form>';
            }
        }

        /* Comments and trackbacks
        -------------------------------------------------------- */
        if ($this->post_id) {
            $params = ['post_id' => $this->post_id, 'order' => 'comment_dt ASC'];

            $comments   = dotclear()->blog()->comments()->getComments(array_merge($params, ['comment_trackback' => 0]));
            $trackbacks = dotclear()->blog()->comments()->getComments(array_merge($params, ['comment_trackback' => 1]));

            # Actions combo box
            $combo_action = [];
            if ($this->can_edit_page && dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
                $combo_action[__('Publish')]         = 'publish';
                $combo_action[__('Unpublish')]       = 'unpublish';
                $combo_action[__('Mark as pending')] = 'pending';
                $combo_action[__('Mark as junk')]    = 'junk';
            }

            if ($this->can_edit_page && dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
                $combo_action[__('Delete')] = 'delete';
            }

            $has_action = !empty($combo_action) && (!$trackbacks->isEmpty() || !$comments->isEmpty());

            echo
            '<div id="comments" class="multi-part" title="' . __('Comments') . '">';

            echo
            '<p class="top-add"><a class="button add" href="#comment-form">' . __('Add a comment') . '</a></p>';

            if ($has_action) {
                echo '<form action="' . dotclear()->adminurl()->root() . '#comments" method="post">';
            }

            echo '<h3>' . __('Trackbacks') . '</h3>';

            if (!$trackbacks->isEmpty()) {
                $this->showComments($trackbacks, $has_action);
            } else {
                echo '<p>' . __('No trackback') . '</p>';
            }

            echo '<h3>' . __('Comments') . '</h3>';
            if (!$comments->isEmpty()) {
                $this->showComments($comments, $has_action);
            } else {
                echo '<p>' . __('No comments') . '</p>';
            }

            if ($has_action) {
                echo
                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
                Form::combo('action', $combo_action) .
                Form::hidden('redir', dotclear()->adminurl()->get('admin.plugin.Page', ['id' => $this->post_id, 'co' => 1])) .
                dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Page', ['id' => $this->post_id], true) .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                    '</div>' .
                    '</form>';
            }
            /* Add a comment
            -------------------------------------------------------- */

            echo
            '<div class="fieldset clear">' .
            '<h3>' . __('Add a comment') . '</h3>' .

            '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="comment-form">' .
            '<div class="constrained">' .
            '<p><label for="comment_author" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Name:') . '</label>' .
            Form::field('comment_author', 30, 255, [
                'default'    => Html::escapeHTML(dotclear()->user()->getInfo('user_cn')),
                'extra_html' => 'required placeholder="' . __('Author') . '"',
            ]) .
            '</p>' .

            '<p><label for="comment_email">' . __('Email:') . '</label>' .
            Form::email('comment_email', [
                'size'         => 30,
                'default'      => Html::escapeHTML(dotclear()->user()->getInfo('user_email')),
                'autocomplete' => 'email',
            ]) .
            '</p>' .

            '<p><label for="comment_site">' . __('Web site:') . '</label>' .
            Form::url('comment_site', [
                'size'         => 30,
                'default'      => Html::escapeHTML(dotclear()->user()->getInfo('user_url')),
                'autocomplete' => 'url',
            ]) .
            '</p>' .

            '<p class="area"><label for="comment_content" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' .
            __('Comment:') . '</label> ' .
            Form::textarea('comment_content', 50, 8, ['extra_html' => 'required placeholder="' . __('Comment') . '"']) .
            '</p>' .

            '<p>' . Form::hidden('post_id', $this->post_id) .
            dotclear()->adminurl()->getHiddenFormFields('admin.comment', [], true) .
            '<input type="submit" name="add" value="' . __('Save') . '" /></p>' .
            '</div>' . #constrained

            '</form>' .
            '</div>' . #add comment
            '</div>'; #comments
        }
    }

    # Controls comments capabilities
     protected function isContributionAllowed($id, $dt, $com = true)
     {
        if (!$id) {
            return true;
        }
        if ($com) {
            if ((dotclear()->blog()->settings()->system->comments_ttl == 0) || (time() - dotclear()->blog()->settings()->system->comments_ttl * 86400 < $dt)) {
                return true;
            }
        } else {
            if ((dotclear()->blog()->settings()->system->trackbacks_ttl == 0) || (time() - dotclear()->blog()->settings()->system->trackbacks_ttl * 86400 < $dt)) {
                return true;
            }
        }

        return false;
    }

    # Show comments
    protected function showComments($rs, $has_action, $tb = false)
    {
        echo
        '<div class="table-outer">' .
        '<table class="comments-list"><tr>' .
        '<th colspan="2" class="first">' . __('Author') . '</th>' .
        '<th>' . __('Date') . '</th>' .
        ($this->can_view_ip ? '<th class="nowrap">' . __('IP address') . '</th>' : '') .
        '<th>' . __('Status') . '</th>' .
        '<th>' . __('Edit') . '</th>' .
            '</tr>';
        $comments = [];
        if (isset($_REQUEST['comments'])) {
            foreach ($_REQUEST['comments'] as $v) {
                $comments[(int) $v] = true;
            }
        }

        while ($rs->fetch()) {
            $comment_url = dotclear()->adminurl()->get('admin.comment', ['id' => $rs->comment_id]);

            $img        = '<img alt="%1$s" title="%1$s" src="?df=images/%2$s" />';
            $this->img_status = '';
            $sts_class  = '';
            switch ($rs->comment_status) {
                case 1:
                    $this->img_status = sprintf($img, __('Published'), 'check-on.png');
                    $sts_class  = 'sts-online';

                    break;
                case 0:
                    $this->img_status = sprintf($img, __('Unpublished'), 'check-off.png');
                    $sts_class  = 'sts-offline';

                    break;
                case -1:
                    $this->img_status = sprintf($img, __('Pending'), 'check-wrn.png');
                    $sts_class  = 'sts-pending';

                    break;
                case -2:
                    $this->img_status = sprintf($img, __('Junk'), 'junk.png');
                    $sts_class  = 'sts-junk';

                    break;
            }

            echo
            '<tr class="line ' . ($rs->comment_status != 1 ? ' offline ' : '') . $sts_class . '"' .
            ' id="c' . $rs->comment_id . '">' .

            '<td class="nowrap">' .
            ($has_action ? Form::checkbox(
                ['comments[]'],
                $rs->comment_id,
                [
                    'checked'    => isset($comments[$rs->comment_id]),
                    'extra_html' => 'title="' . __('select this comment') . '"',
                ]
            ) : '') . '</td>' .
            '<td class="maximal">' . Html::escapeHTML($rs->comment_author) . '</td>' .
            '<td class="nowrap">' . Dt::dt2str(__('%Y-%m-%d %H:%M'), $rs->comment_dt) . '</td>' .
            ($this->can_view_ip ?
                '<td class="nowrap"><a href="' . dotclear()->adminurl()->get('admin.comments', ['ip' => $rs->comment_ip]) . '">' . $rs->comment_ip . '</a></td>' : '') .
            '<td class="nowrap status">' . $this->img_status . '</td>' .
            '<td class="nowrap status"><a href="' . $comment_url . '">' .
            '<img src="?df=images/edit-mini.png" alt="" title="' . __('Edit this comment') . '" /> ' . __('Edit') . '</a></td>' .

                '</tr>';
        }

        echo '</table></div>';
    }
}
