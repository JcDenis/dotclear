<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Admin;

// Dotclear\Plugin\Pages\Admin\HandlerEdit
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\Action\CommentAction;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin page edition for plugin Pages.
 *
 * @ingroup  Plugin Pages
 */
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
    // private $post_media = [];

    private $can_view_page = true;
    private $can_view_ip   = false;
    private $can_edit_page = false;
    private $can_publish   = false;
    private $can_delete    = false;

    private $post;
    // private $trackback = null;
    // private $tb_urls    ='';
    // private $tb_excerpt = '';
    // private $comments_actions = null;

    private $next_link;
    private $prev_link;

    private $bad_dt     = false;
    private $img_status = '';

    protected function getPermissions(): string|bool
    {
        return 'pages,contentadmin';
    }

    protected function getActionInstance(): ?Action
    {
        $action = new CommentAction(App::core()->adminurl()->get('admin.plugin.Page', ['id' => GPC::request()->int('id')], '&'));
        $action->setEnableRedirSelection(false);

        return $action;
    }

    protected function getPagePrepend(): ?bool
    {
        $page_title    = __('New post');
        $next_headlink = $prev_headlink = '';

        $this->post_format   = App::core()->user()->getOption('post_format');
        $this->post_editor   = App::core()->user()->getOption('editor');
        $this->post_lang     = App::core()->user()->getInfo('user_lang');
        $this->post_status   = App::core()->user()->getInfo('user_post_status');
        $this->can_edit_page = App::core()->user()->check('pages,usage', App::core()->blog()->id);
        $this->can_publish   = App::core()->user()->check('pages,publish,contentadmin', App::core()->blog()->id);
        $post_headlink       = '<link rel="%s" title="%s" href="' . App::core()->adminurl()->get('admin.plugin.Page', ['id' => '%s'], '&amp;', true) . '" />';
        $post_link           = '<a href="' . App::core()->adminurl()->get('admin.plugin.Page', ['id' => '%s'], '&amp;', true) . '" title="%s">%s</a>';

        // If user can't publish
        if (!$this->can_publish) {
            $this->post_status = -2;
        }

        $img_status_pattern = '<img class="img_select_option" alt="%1$s" title="%1$s" src="?df=images/%2$s" />';

        // Get page informations
        if (!GPC::request()->empty('id')) {
            $page_title = __('Edit page');

            $param = new Param();
            $param->set('post_type', 'page');
            $param->set('post_id', GPC::request()->int('id'));

            $this->post = App::core()->blog()->posts()->getPosts(param: $param);

            if ($this->post->isEmpty()) {
                App::core()->error()->add(__('This page does not exist.'));
                $this->can_view_page = false;
            } else {
                $this->post_id            = $this->post->fInt('post_id');
                $this->post_dt            = $this->post->f('post_dt');
                $this->post_format        = $this->post->f('post_format');
                $this->post_password      = $this->post->f('post_password');
                $this->post_url           = $this->post->f('post_url');
                $this->post_lang          = $this->post->f('post_lang');
                $this->post_title         = $this->post->f('post_title');
                $this->post_excerpt       = $this->post->f('post_excerpt');
                $this->post_excerpt_xhtml = $this->post->f('post_excerpt_xhtml');
                $this->post_content       = $this->post->f('post_content');
                $this->post_content_xhtml = $this->post->f('post_content_xhtml');
                $this->post_notes         = $this->post->f('post_notes');
                $this->post_status        = $this->post->f('post_status');
                $this->post_position      = (int) $this->post->fInt('post_position');
                $this->post_open_comment  = (bool) $this->post->fInt('post_open_comment');
                $this->post_open_tb       = (bool) $this->post->fInt('post_open_tb');
                $this->post_selected      = (bool) $this->post->fInt('post_selected');

                $this->can_edit_page = $this->post->call('isEditable');
                $this->can_delete    = $this->post->call('isDeletable');

                $next_rs = App::core()->blog()->posts()->getNextPost(record: $this->post);
                $prev_rs = App::core()->blog()->posts()->getPreviousPost(record: $this->post);

                if (null !== $next_rs) {
                    $this->next_link = sprintf(
                        $post_link,
                        $next_rs->fInt('post_id'),
                        Html::escapeHTML(trim(Html::clean($next_rs->f('post_title')))),
                        __('Next page') . '&nbsp;&#187;'
                    );
                    $next_headlink = sprintf(
                        $post_headlink,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->f('post_title')))),
                        $next_rs->fInt('post_id')
                    );
                }

                if (null !== $prev_rs) {
                    $this->prev_link = sprintf(
                        $post_link,
                        $prev_rs->fInt('post_id'),
                        Html::escapeHTML(trim(Html::clean($prev_rs->f('post_title')))),
                        '&#171;&nbsp;' . __('Previous page')
                    );
                    $prev_headlink = sprintf(
                        $post_headlink,
                        'previous',
                        Html::escapeHTML(trim(Html::clean($prev_rs->f('post_title')))),
                        $prev_rs->fInt('post_id')
                    );
                }

                if (!App::core()->blog()->public_path) {
                    App::core()->error()->add(
                        __('There is no writable root directory for the media manager. You should contact your administrator.')
                    );
                }
            }
        }

        // Format content
        if (GPC::post()->count() && $this->can_edit_page) {
            $this->post_format  = GPC::post()->string('post_format');
            $this->post_excerpt = GPC::post()->string('post_excerpt');
            $this->post_content = GPC::post()->string('post_content');
            $this->post_title   = GPC::post()->string('post_title');
            $this->post_dt      = '';

            if (GPC::post()->isset('post_status')) {
                $this->post_status = GPC::post()->int('post_status');
            }

            if (!GPC::post()->empty('post_dt')) {
                try {
                    $this->post_dt = Clock::ts(date: GPC::post()->string('post_dt'), from: App::core()->timezone());
                } catch (Exception $e) {
                    $this->bad_dt  = true;
                    $this->post_dt = Clock::format('Y-m-d H:i');

                    App::core()->error()->add(__('Invalid publication date'));
                }
            }

            $this->post_open_comment = !GPC::post()->empty('post_open_comment');
            $this->post_open_tb      = !GPC::post()->empty('post_open_tb');
            $this->post_selected     = !GPC::post()->empty('post_selected');
            $this->post_lang         = GPC::post()->string('post_lang');
            $this->post_password     = GPC::post()->string('post_password', null);
            $this->post_position     = GPC::post()->int('post_position');
            $this->post_notes        = GPC::post()->string('post_notes');

            if (GPC::post()->isset('post_url')) {
                $this->post_url = GPC::post()->string('post_url');
            }

            App::core()->blog()->posts()->formatPostContent(
                id: $this->post_id,
                format: $this->post_format,
                lang: $this->post_lang,
                excerpt: $this->post_excerpt,
                excerpt_xhtml: $this->post_excerpt_xhtml,
                content: $this->post_content,
                content_xhtml: $this->post_content_xhtml
            );
        }

        // Delete post
        if (!GPC::post()->empty('delete') && $this->can_delete) {
            try {
                App::core()->blog()->posts()->deletePosts(ids: new Integers($this->post_id));
                App::core()->adminurl()->redirect('admin.plugin.Page');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Create or update page
        if (!GPC::post()->empty('save') && $this->can_edit_page && !$this->bad_dt) {
            $cur = App::core()->con()->openCursor(App::core()->prefix() . 'post');

            // Magic tweak :)
            App::core()->blog()->settings()->getGroup('system')->setSetting('post_url_format', '{t}');

            $cur->setField('post_type', 'page');
            $cur->setField('post_dt', $this->post_dt ? Clock::database($this->post_dt) : '');
            $cur->setField('post_format', $this->post_format);
            $cur->setField('post_password', $this->post_password);
            $cur->setField('post_lang', $this->post_lang);
            $cur->setField('post_title', $this->post_title);
            $cur->setField('post_excerpt', $this->post_excerpt);
            $cur->setField('post_excerpt_xhtml', $this->post_excerpt_xhtml);
            $cur->setField('post_content', $this->post_content);
            $cur->setField('post_content_xhtml', $this->post_content_xhtml);
            $cur->setField('post_notes', $this->post_notes);
            $cur->setField('post_status', $this->post_status);
            $cur->setField('post_position', $this->post_position);
            $cur->setField('post_open_comment', (int) $this->post_open_comment);
            $cur->setField('post_open_tb', (int) $this->post_open_tb);
            $cur->setField('post_selected', (int) $this->post_selected);

            if (GPC::post()->isset('post_url')) {
                $cur->setField('post_url', $this->post_url);
            }

            // Update post
            if ($this->post_id) {
                try {
                    // --BEHAVIOR-- adminBeforePageUpdate
                    App::core()->behavior()->call('adminBeforePageUpdate', $cur, $this->post_id);

                    App::core()->blog()->posts()->updatePost(id: $this->post_id, cursor: $cur);

                    // --BEHAVIOR-- adminAfterPageUpdate
                    App::core()->behavior()->call('adminAfterPageUpdate', $cur, $this->post_id);

                    App::core()->adminurl()->redirect('admin.plugin.Page', ['id' => $this->post_id, 'upd' => 1]);
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            } else {
                $cur->setField('user_id', App::core()->user()->userID());

                try {
                    // --BEHAVIOR-- adminBeforePageCreate
                    App::core()->behavior()->call('adminBeforePageCreate', $cur);

                    $return_id = App::core()->blog()->posts()->createPost(cursor: $cur);

                    // --BEHAVIOR-- adminAfterPageCreate
                    App::core()->behavior()->call('adminAfterPageCreate', $cur, $return_id);

                    App::core()->adminurl()->redirect('admin.plugin.Page', ['id' => $return_id, 'crea' => 1]);
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }
        }

        // Page setup
        $default_tab = 'edit-entry';
        if (!$this->can_edit_page) {
            $default_tab = '';
        }
        if (!GPC::get()->empty('co')) {
            $default_tab = 'comments';
        }

        if ($this->post_id) {
            $this->img_status = match ($this->post_status) {
                1       => sprintf($img_status_pattern, __('Published'), 'check-on.png'),
                0       => sprintf($img_status_pattern, __('Unpublished'), 'check-off.png'),
                -1      => sprintf($img_status_pattern, __('Scheduled'), 'scheduled.png'),
                -2      => sprintf($img_status_pattern, __('Pending'), 'check-wrn.png'),
                default => '',
            };
            $edit_entry_str  = __('&ldquo;%s&rdquo;');
            $page_title_edit = sprintf($edit_entry_str, Html::escapeHTML(trim(Html::clean($this->post_title)))) . ' ' . $this->img_status;
        } else {
            $page_title_edit = $page_title;
        }

        $this
            ->setPageHelp('page', 'core_wiki')
            ->setPageTitle($page_title . ' - ' . __('Pages'))
            ->setPageHead(
                App::core()->resource()->modal() .
                App::core()->resource()->json('pages_page', ['confirm_delete_post' => __('Are you sure you want to delete this page?')]) .
                App::core()->resource()->load('_post.js') .
                App::core()->resource()->load('page.js', 'Plugin', 'Pages')
            )
        ;

        if ($this->post_editor) {
            $p_edit = $c_edit = '';
            if (!empty($this->post_editor[$this->post_format])) {
                $p_edit = $this->post_editor[$this->post_format];
            }
            if (!empty($this->post_editor['xhtml'])) {
                $c_edit = $this->post_editor['xhtml'];
            }
            if ($p_edit == $c_edit) {
                $this->setPageHead(App::core()->behavior()->call(
                    'adminPostEditor',
                    $p_edit,
                    'post',
                    ['#post_excerpt', '#post_content', '#comment_content'],
                    $this->post_format
                ));
            } else {
                $this->setPageHead(App::core()->behavior()->call(
                    'adminPostEditor',
                    $p_edit,
                    'post',
                    ['#post_excerpt', '#post_content'],
                    $this->post_format
                ));
                $this->setPageHead(App::core()->behavior()->call(
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
                App::core()->resource()->confirmClose('entry-form', 'comment-form') .
                // --BEHAVIOR-- adminPostHeaders
                App::core()->behavior()->call('adminPageHeaders') .
                App::core()->resource()->pageTabs($default_tab) .
                $next_headlink . "\n" . $prev_headlink
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Pages')                                 => App::core()->adminurl()->get('admin.plugin.Pages'),
                $page_title_edit                            => '',
            ], [
                'x-frame-allow' => App::core()->blog()->url,
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        $status_combo = App::core()->combo()->getPostStatusesCombo();

        $param = new Param();
        $param->set('order', 'asc');
        $rs         = App::core()->blog()->posts()->getLangs(param: $param);
        $lang_combo = App::core()->combo()->getLangsCombo($rs, true);

        $core_formaters    = App::core()->formater()->getFormaters();
        $available_formats = ['' => ''];
        foreach ($core_formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $available_formats[$format] = $format;
            }
        }

        if (!GPC::get()->empty('upd')) {
            App::core()->notice()->success(__('Page has been successfully updated.'));
        } elseif (!GPC::get()->empty('crea')) {
            App::core()->notice()->success(__('Page has been successfully created.'));
        } elseif (!GPC::get()->empty('attached')) {
            App::core()->notice()->success(__('File has been successfully attached.'));
        } elseif (!GPC::get()->empty('rmattach')) {
            App::core()->notice()->success(__('Attachment has been successfully removed.'));
        }

        // XHTML conversion
        if (!GPC::get()->empty('xconv')) {
            $this->post_excerpt = $this->post_excerpt_xhtml;
            $this->post_content = $this->post_content_xhtml;
            $this->post_format  = 'xhtml';

            App::core()->notice()->message(__('Don\'t forget to validate your XHTML conversion by saving your post.'));
        }

        if ($this->post_id && 1 == $this->post->fInt('post_status')) {
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

            // --BEHAVIOR-- adminPostNavLinks
            App::core()->behavior()->call('adminPageNavLinks', $this->post ?? null);

            echo '</p>';
        }

        // Exit if we cannot view page
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
                            'default' => Clock::formfield(date: $this->post_dt, to: App::core()->timezone()),
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
                        '<a id="convert-xhtml" class="button' . ($this->post_id && 'wiki' != $this->post_format ? ' hide' : '') .
                        '" href="' . App::core()->adminurl()->get('admin.plugin.Page', ['id' => $this->post_id, 'xconv' => '1']) . '">' .
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
                        (App::core()->blog()->settings()->getGroup('system')->getSetting('allow_comments') ?
                            ($this->isContributionAllowed($this->post_id, Clock::ts(date: $this->post_dt), true) ?
                                '' :
                                '<p class="form-note warn">' .
                                __('Warning: Comments are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' .
                            __('Comments are not accepted on this blog so far.') . '</p>') .
                        '<p><label for="post_open_tb" class="classic">' .
                        Form::checkbox('post_open_tb', 1, $this->post_open_tb) . ' ' .
                        __('Accept trackbacks') . '</label></p>' .
                        (App::core()->blog()->settings()->getGroup('system')->getSetting('allow_trackbacks') ?
                            ($this->isContributionAllowed($this->post_id, Clock::ts(date: $this->post_dt), false) ?
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
                        (int) App::core()->user()->getOption('edit_size'),
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

            // --BEHAVIOR-- adminPostFormItems
            App::core()->behavior()->call('adminPageFormItems', $main_items, $sidebar_items, $this->post ?? null);

            echo '<div class="multi-part" title="' . ($this->post_id ? __('Edit page') : __('New page')) .
            sprintf(' &rsaquo; %s', $this->post_format) . '" id="edit-entry">';
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="entry-form">';

            echo '<div id="entry-wrapper">';
            echo '<div id="entry-content"><div class="constrained">';
            echo '<h3 class="out-of-screen-if-js">' . __('Edit page') . '</h3>';

            foreach ($main_items as $id => $item) {
                echo $item;
            }

            // --BEHAVIOR-- adminPageForm
            App::core()->behavior()->call('adminPageForm', $this->post ?? null);

            echo '<p class="border-top">' .
            ($this->post_id ? Form::hidden('id', $this->post_id) : '') .
            '<input type="submit" value="' . __('Save') . ' (s)" ' .
                'accesskey="s" name="save" /> ';

            if ($this->post_id) {
                $preview_url = App::core()->blog()->getURLFor(
                    'pagespreview',
                    App::core()->user()->userID() . '/' .
                    Http::browserUID(App::core()->config()->get('master_key') . App::core()->user()->userID() . App::core()->user()->cryptLegacy(App::core()->user()->userID())) .
                    '/' . $this->post->f('post_url')
                );

                // Prevent browser caching on preview
                $preview_url .= (parse_url($preview_url, PHP_URL_QUERY) ? '&' : '?') . 'rand=' . md5((string) rand());

                $blank_preview = App::core()->user()->preference()->get('interface')->get('blank_preview');

                $preview_class  = $blank_preview ? '' : ' modal';
                $preview_target = $blank_preview ? '' : ' target="_blank"';

                echo '<a id="post-preview" href="' . $preview_url . '" class="button' . $preview_class . '" accesskey="p"' . $preview_target . '>' . __('Preview') . ' (p)' . '</a>';
                echo ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';
            } else {
                echo '<a id="post-cancel" href="' . App::core()->adminurl()->get('admin.home') . '" class="button" accesskey="c">' . __('Cancel') . ' (c)</a>';
            }

            echo($this->can_delete ? ' <input type="submit" class="delete" value="' . __('Delete') . '" name="delete" />' : '') .
            App::core()->adminurl()->getHiddenFormFields('admin.plugin.Page', [], true) .
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

            // --BEHAVIOR-- adminPageFormSidebar
            App::core()->behavior()->call('adminPageFormSidebar', $this->post ?? null);

            echo '</div>'; // End #entry-sidebar

            echo '</form>';

            // --BEHAVIOR-- adminPostForm
            App::core()->behavior()->call('adminPageAfterForm', $this->post ?? null);

            echo '</div>'; // End

            if ($this->post_id) { // && !empty($this->post_media)) {
                echo '<form action="' . App::core()->adminurl()->root() . '" id="attachment-remove-hide" method="post">' .
                '<div>' .
                App::core()->adminurl()->getHiddenFormFields('admin.post.media', [
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
            $param = new Param();
            $param->set('post_id', $this->post_id);
            $param->set('order', 'comment_dt ASC');

            $param->set('comment_trackback', 0);
            $comments   = App::core()->blog()->comments()->getComments(param: $param);

            $param->set('comment_trackback', 1);
            $trackbacks = App::core()->blog()->comments()->getComments(param: $param);

            // Actions combo box
            $combo_action = [];
            if ($this->can_edit_page && App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
                $combo_action[__('Publish')]         = 'publish';
                $combo_action[__('Unpublish')]       = 'unpublish';
                $combo_action[__('Mark as pending')] = 'pending';
                $combo_action[__('Mark as junk')]    = 'junk';
            }

            if ($this->can_edit_page && App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
                $combo_action[__('Delete')] = 'delete';
            }

            $has_action = !empty($combo_action) && (!$trackbacks->isEmpty() || !$comments->isEmpty());

            echo '<div id="comments" class="multi-part" title="' . __('Comments') . '">';

            echo '<p class="top-add"><a class="button add" href="#comment-form">' . __('Add a comment') . '</a></p>';

            if ($has_action) {
                echo '<form action="' . App::core()->adminurl()->root() . '#comments" method="post">';
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
                echo '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
                Form::combo('action', $combo_action) .
                Form::hidden('redir', App::core()->adminurl()->get('admin.plugin.Page', ['id' => $this->post_id, 'co' => 1])) .
                App::core()->adminurl()->getHiddenFormFields('admin.plugin.Page', ['id' => $this->post_id], true) .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                    '</div>' .
                    '</form>';
            }
            /* Add a comment
            -------------------------------------------------------- */

            echo '<div class="fieldset clear">' .
            '<h3>' . __('Add a comment') . '</h3>' .

            '<form action="' . App::core()->adminurl()->root() . '" method="post" id="comment-form">' .
            '<div class="constrained">' .
            '<p><label for="comment_author" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Name:') . '</label>' .
            Form::field('comment_author', 30, 255, [
                'default'    => Html::escapeHTML(App::core()->user()->userCN()),
                'extra_html' => 'required placeholder="' . __('Author') . '"',
            ]) .
            '</p>' .

            '<p><label for="comment_email">' . __('Email:') . '</label>' .
            Form::email('comment_email', [
                'size'         => 30,
                'default'      => Html::escapeHTML(App::core()->user()->getInfo('user_email')),
                'autocomplete' => 'email',
            ]) .
            '</p>' .

            '<p><label for="comment_site">' . __('Web site:') . '</label>' .
            Form::url('comment_site', [
                'size'         => 30,
                'default'      => Html::escapeHTML(App::core()->user()->getInfo('user_url')),
                'autocomplete' => 'url',
            ]) .
            '</p>' .

            '<p class="area"><label for="comment_content" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' .
            __('Comment:') . '</label> ' .
            Form::textarea('comment_content', 50, 8, ['extra_html' => 'required placeholder="' . __('Comment') . '"']) .
            '</p>' .

            '<p>' . Form::hidden('post_id', $this->post_id) .
            App::core()->adminurl()->getHiddenFormFields('admin.comment', [], true) .
            '<input type="submit" name="add" value="' . __('Save') . '" /></p>' .
            '</div>' . // constrained

            '</form>' .
            '</div>' . // add comment
            '</div>'; // comments
        }
    }

    // Controls comments capabilities
    protected function isContributionAllowed($id, $dt, $com = true)
    {
        if (!$id) {
            return true;
        }
        if ($com) {
            if (0 == App::core()->blog()->settings()->getGroup('system')->getSetting('comments_ttl') || $dt > (Clock::ts() - App::core()->blog()->settings()->getGroup('system')->getSetting('comments_ttl') * 86400)) {
                return true;
            }
        } else {
            if (0 == App::core()->blog()->settings()->getGroup('system')->getSetting('trackbacks_ttl') || $dt > (Clock::ts() - App::core()->blog()->settings()->getGroup('system')->getSetting('trackbacks_ttl') * 86400)) {
                return true;
            }
        }

        return false;
    }

    // Show comments
    protected function showComments($rs, $has_action, $tb = false)
    {
        echo '<div class="table-outer">' .
        '<table class="comments-list"><tr>' .
        '<th colspan="2" class="first">' . __('Author') . '</th>' .
        '<th>' . __('Date') . '</th>' .
        ($this->can_view_ip ? '<th class="nowrap">' . __('IP address') . '</th>' : '') .
        '<th>' . __('Status') . '</th>' .
        '<th>' . __('Edit') . '</th>' .
            '</tr>';
        $comments = [];
        foreach (GPC::request()->array('comments') as $v) {
            $comments[(int) $v] = true;
        }

        while ($rs->fetch()) {
            $comment_url = App::core()->adminurl()->get('admin.comment', ['id' => $rs->f('comment_id')]);
            $img_status  = sprintf(
                '<img alt="%1$s" title="%1$s" src="?df=%2$s" />',
                App::core()->blog()->comments()->status()->getState($rs->fInt('comment_status')),
                App::core()->blog()->comments()->status()->getIcon($rs->fInt('comment_status')),
            );
            $sts_class = 'sts-' . App::core()->blog()->comments()->status()->getId($rs->fInt('comment_status'));

            echo '<tr class="line ' . (1 != $rs->fInt('comment_status') ? ' offline ' : '') . $sts_class . '"' .
            ' id="c' . $rs->f('comment_id') . '">' .

            '<td class="nowrap">' .
            ($has_action ? Form::checkbox(
                ['comments[]'],
                $rs->f('comment_id'),
                [
                    'checked'    => isset($comments[$rs->f('comment_id')]),
                    'extra_html' => 'title="' . __('select this comment') . '"',
                ]
            ) : '') . '</td>' .
            '<td class="maximal">' . Html::escapeHTML($rs->f('comment_author')) . '</td>' .
            '<td class="nowrap">' . Clock::str(format: __('%Y-%m-%d %H:%M'), date: $rs->f('comment_dt'), to: App::core()->timezone()) . '</td>' .
            ($this->can_view_ip ?
                '<td class="nowrap"><a href="' . App::core()->adminurl()->get('admin.comments', ['ip' => $rs->f('comment_ip')]) . '">' . $rs->f('comment_ip') . '</a></td>' : '') .
            '<td class="nowrap status">' . $img_status . '</td>' .
            '<td class="nowrap status"><a href="' . $comment_url . '">' .
            '<img src="?df=images/edit-mini.png" alt="" title="' . __('Edit this comment') . '" /> ' . __('Edit') . '</a></td>' .

                '</tr>';
        }

        echo '</table></div>';
    }
}
