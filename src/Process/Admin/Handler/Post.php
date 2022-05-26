<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Pos
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Trackback\Trackback;
use Dotclear\Database\Param;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Helper\Text;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Action\Action\CommentAction;
use Exception;

/**
 * Admin post page.
 *
 * @ingroup  Admin Post Handler
 */
class Post extends AbstractPage
{
    private $post_id;
    private $cat_id             = '';
    private $post_dt            = '';
    private $post_format        = '';
    private $post_editor        = '';
    private $post_password      = '';
    private $post_url           = '';
    private $post_lang          = '';
    private $post_title         = '';
    private $post_excerpt       = '';
    private $post_excerpt_xhtml = '';
    private $post_content       = '';
    private $post_content_xhtml = '';
    private $post_notes         = '';
    private $post_status        = -2;
    private $post_selected      = false;
    private $post_open_comment  = false;
    private $post_open_tb       = false;

    private $can_view_page = true;
    private $can_view_ip   = false;
    private $can_edit_post = false;
    private $can_publish   = false;
    private $can_delete    = false;

    private $post;
    private $trackback;
    private $tb_urls    = '';
    private $tb_excerpt = '';
    private $comments_actions;

    private $next_link;
    private $prev_link;

    private $bad_dt     = false;
    private $img_status = '';

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $page_title = __('New post');

        $this->trackback = new Trackback();

        $this->post_format       = App::core()->user()->getOption('post_format');
        $this->post_editor       = App::core()->user()->getOption('editor');
        $this->post_lang         = App::core()->user()->getInfo('user_lang');
        $this->post_status       = App::core()->user()->getInfo('user_post_status');
        $this->post_open_comment = App::core()->blog()->settings()->get('system')->get('allow_comments');
        $this->post_open_tb      = App::core()->blog()->settings()->get('system')->get('allow_trackbacks');

        $this->can_view_ip   = App::core()->user()->check('contentadmin', App::core()->blog()->id);
        $this->can_edit_post = App::core()->user()->check('usage,contentadmin', App::core()->blog()->id);
        $this->can_publish   = App::core()->user()->check('publish,contentadmin', App::core()->blog()->id);

        $post_headlink      = '<link rel="%s" title="%s" href="' . App::core()->adminurl()->get('admin.post', ['id' => '%s'], '&amp;', true) . '" />';
        $post_link          = '<a href="' . App::core()->adminurl()->get('admin.post', ['id' => '%s'], '&amp;', true) . '" title="%s">%s</a>';
        $next_headlink      = $prev_headlink      = null;
        $img_status_pattern = '<img class="img_select_option" alt="%1$s" title="%1$s" src="?df=images/%2$s" />';

        // If user can't publish
        if (!$this->can_publish) {
            $this->post_status = -2;
        }

        // Get entry informations
        if (!empty($_REQUEST['id'])) {
            $page_title = __('Edit post');

            $param = new Param();
            $param->set('post_id', (int) $_REQUEST['id']);

            $this->post = $rs = App::core()->blog()->posts()->getPosts(param: $param);

            if ($rs->isEmpty()) {
                App::core()->error()->add(__('This entry does not exist.'));
                $this->can_view_page = false;
            } else {
                $this->post_id            = $rs->fInt('post_id');
                $this->cat_id             = $rs->fInt('cat_id');
                $this->post_dt            = $rs->f('post_dt');
                $this->post_format        = $rs->f('post_format');
                $this->post_password      = $rs->f('post_password');
                $this->post_url           = $rs->f('post_url');
                $this->post_lang          = $rs->f('post_lang');
                $this->post_title         = $rs->f('post_title');
                $this->post_excerpt       = $rs->f('post_excerpt');
                $this->post_excerpt_xhtml = $rs->f('post_excerpt_xhtml');
                $this->post_content       = $rs->f('post_content');
                $this->post_content_xhtml = $rs->f('post_content_xhtml');
                $this->post_notes         = $rs->f('post_notes');
                $this->post_status        = $rs->f('post_status');
                $this->post_selected      = (bool) $rs->fInt('post_selected');
                $this->post_open_comment  = (bool) $rs->fInt('post_open_comment');
                $this->post_open_tb       = (bool) $rs->fInt('post_open_tb');

                $this->can_edit_post = $rs->isEditable();
                $this->can_delete    = $rs->isDeletable();

                $next_rs = App::core()->blog()->posts()->getNextPost(record: $rs);
                $prev_rs = App::core()->blog()->posts()->getPreviousPost(record: $rs);

                if (null !== $next_rs) {
                    $this->next_link = sprintf(
                        $post_link,
                        $next_rs->f('post_id'),
                        Html::escapeHTML(trim(Html::clean($next_rs->f('post_title')))),
                        __('Next entry') . '&nbsp;&#187;'
                    );
                    $next_headlink = sprintf(
                        $post_headlink,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->f('post_title')))),
                        $next_rs->f('post_id')
                    );
                }

                if (null !== $prev_rs) {
                    $this->prev_link = sprintf(
                        $post_link,
                        $prev_rs->f('post_id'),
                        Html::escapeHTML(trim(Html::clean($prev_rs->f('post_title')))),
                        '&#171;&nbsp;' . __('Previous entry')
                    );
                    $prev_headlink = sprintf(
                        $post_headlink,
                        'previous',
                        Html::escapeHTML(trim(Html::clean($prev_rs->f('post_title')))),
                        $prev_rs->f('post_id')
                    );
                }

                /*
                if (!App::core()->blog()->public_path) {
                    App::core()->error()->add(
                        __('There is no writable root directory for the media manager. You should contact your administrator.')
                    );
                }
                */

                // Sanitize trackbacks excerpt
                $this->tb_excerpt = empty($_POST['tb_excerpt']) ?
                    $this->post_excerpt_xhtml . ' ' . $this->post_content_xhtml : $_POST['tb_excerpt'];
                $this->tb_excerpt = Html::decodeEntities(Html::clean($this->tb_excerpt));
                $this->tb_excerpt = Text::cutString(Html::escapeHTML($this->tb_excerpt), 255);
                $this->tb_excerpt = preg_replace('/\s+/ms', ' ', $this->tb_excerpt);
            }
        }

        $anchor = isset($_REQUEST['section']) && 'trackbacks' == $_REQUEST['section'] ? 'trackbacks' : 'comments';

        $this->comments_actions = new CommentAction(App::core()->adminurl()->get('admin.post'), ['id' => $this->post_id, '_ANCHOR' => $anchor, 'section' => $anchor]);
        $this->comments_actions->pageProcess(); // Redirect on action made

        // Ping blogs
        if (!empty($_POST['ping'])) {
            if (!empty($_POST['tb_urls']) && $this->post_id && 1 == $this->post_status && $this->can_edit_post) {
                $this->tb_urls = $_POST['tb_urls'];
                $this->tb_urls = str_replace("\r", '', $this->tb_urls);
                $tb_post_title = Html::escapeHTML(trim(Html::clean($this->post_title)));
                $tb_post_url   = $this->post->getURL();

                foreach (explode("\n", $this->tb_urls) as $tb_url) {
                    try {
                        // --BEHAVIOR-- adminBeforePingTrackback
                        App::core()->behavior()->call('adminBeforePingTrackback', $tb_url, $this->post_id, $tb_post_title, $this->tb_excerpt, $tb_post_url);

                        $this->trackback->ping($tb_url, $this->post_id, $tb_post_title, $this->tb_excerpt, $tb_post_url);
                    } catch (Exception $e) {
                        App::core()->error()->add($e->getMessage());
                    }
                }

                if (!App::core()->error()->flag()) {
                    App::core()->notice()->addSuccessNotice(__('All pings sent.'));
                    App::core()->adminurl()->redirect(
                        'admin.post',
                        ['id' => $this->post_id, 'tb' => '1']
                    );
                }
            }
        }

        // Format excerpt and content
        elseif (!empty($_POST) && $this->can_edit_post) {
            $this->post_format  = $_POST['post_format'];
            $this->post_excerpt = $_POST['post_excerpt'];
            $this->post_content = $_POST['post_content'];
            $this->post_title   = $_POST['post_title'];
            $this->cat_id       = (int) $_POST['cat_id'];

            if (isset($_POST['post_status'])) {
                $this->post_status = (int) $_POST['post_status'];
            }

            if (empty($_POST['post_dt'])) {
                $this->post_dt = '';
            } else {
                try {
                    $this->post_dt = Clock::ts(date: $_POST['post_dt'], from: App::core()->timezone());
                } catch (Exception $e) {
                    $this->bad_dt  = true;
                    $this->post_dt = Clock::format(format: 'Y-m-d H:i');

                    App::core()->error()->add(__('Invalid publication date'));
                }
            }

            $this->post_open_comment = !empty($_POST['post_open_comment']);
            $this->post_open_tb      = !empty($_POST['post_open_tb']);
            $this->post_selected     = !empty($_POST['post_selected']);
            $this->post_lang         = $_POST['post_lang'];
            $this->post_password     = !empty($_POST['post_password']) ? $_POST['post_password'] : null;
            $this->post_notes        = $_POST['post_notes'];

            if (isset($_POST['post_url'])) {
                $this->post_url = $_POST['post_url'];
            }

            App::core()->blog()->posts()->setPostContent(
                $this->post_id,
                $this->post_format,
                $this->post_lang,
                $this->post_excerpt,
                $this->post_excerpt_xhtml,
                $this->post_content,
                $this->post_content_xhtml
            );
        }

        // Delete post
        if (!empty($_POST['delete']) && $this->can_delete) {
            try {
                App::core()->blog()->posts()->delPosts(ids: new Integers($this->post_id));
                App::core()->adminurl()->redirect('admin.posts');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Create or update post
        if (!empty($_POST) && !empty($_POST['save']) && $this->can_edit_post && !$this->bad_dt) {
            // Create category
            if (!empty($_POST['new_cat_title']) && App::core()->user()->check('categories', App::core()->blog()->id)) {
                $cur_cat = App::core()->con()->openCursor(App::core()->prefix() . 'category');
                $cur_cat->setField('cat_title', $_POST['new_cat_title']);
                $cur_cat->setField('cat_url', '');

                // --BEHAVIOR-- adminBeforeCategoryCreate
                App::core()->behavior()->call('adminBeforeCategoryCreate', $cur_cat);

                $this->cat_id = App::core()->blog()->categories()->addCategory(
                    cursor: $cur_cat,
                    parent: !empty($_POST['new_cat_parent']) ? (int) $_POST['new_cat_parent'] : 0
                );

                // --BEHAVIOR-- adminAfterCategoryCreate
                App::core()->behavior()->call('adminAfterCategoryCreate', $cur_cat, $this->cat_id);
            }

            $cur = App::core()->con()->openCursor(App::core()->prefix() . 'post');

            $cur->setField('cat_id', $this->cat_id ?: null);
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
            $cur->setField('post_selected', (int) $this->post_selected);
            $cur->setField('post_open_comment', (int) $this->post_open_comment);
            $cur->setField('post_open_tb', (int) $this->post_open_tb);

            if (isset($_POST['post_url'])) {
                $cur->setField('post_url', $this->post_url);
            }

            // Update post
            if ($this->post_id) {
                try {
                    // --BEHAVIOR-- adminBeforePostUpdate, Cursor, int
                    App::core()->behavior()->call('adminBeforePostUpdate', $cur, $this->post_id);

                    App::core()->blog()->posts()->updPost($this->post_id, $cur);

                    // --BEHAVIOR-- adminAfterPostUpdate, Cursor, int
                    App::core()->behavior()->call('adminAfterPostUpdate', $cur, $this->post_id);
                    App::core()->notice()->addSuccessNotice(sprintf(
                        __('The post "%s" has been successfully updated'),
                        Html::escapeHTML(trim(Html::clean($cur->getField('post_title'))))
                    ));
                    App::core()->adminurl()->redirect(
                        'admin.post',
                        ['id' => $this->post_id]
                    );
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            } else {
                $cur->setField('user_id', App::core()->user()->userID());

                try {
                    // --BEHAVIOR-- adminBeforePostCreate, Cursor
                    App::core()->behavior()->call('adminBeforePostCreate', $cur);

                    $return_id = App::core()->blog()->posts()->addPost($cur);

                    // --BEHAVIOR-- adminAfterPostCreate, Cursor, int
                    App::core()->behavior()->call('adminAfterPostCreate', $cur, $return_id);

                    App::core()->notice()->addSuccessNotice(__('Entry has been successfully created.'));
                    App::core()->adminurl()->redirect(
                        'admin.post',
                        ['id' => $return_id]
                    );
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }
        }

        // Page setup
        $default_tab = 'edit-entry';
        if (!$this->can_edit_post) {
            $default_tab = '';
        }
        if (!empty($_GET['co'])) {
            $default_tab = 'comments';
        } elseif (!empty($_GET['tb'])) {
            $default_tab = 'trackbacks';
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
            $page_title_edit = '';
        }

        $this->setPageHead(
            App::core()->resource()->modal() .
            App::core()->resource()->metaEditor()
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

        $this->setPageHelp('core_post');
        if (!$this->can_view_page) {
            $this->setPageHelp('core_post', 'core_trackbacks', 'core_wiki');
        }

        $this
            ->setPageTitle($page_title . ' - ' . __('Posts'))
            ->setPageHead(
                App::core()->resource()->load('_post.js') .
                App::core()->resource()->confirmClose('entry-form', 'comment-form') .
                // --BEHAVIOR-- adminPostHeaders
                App::core()->behavior()->call('adminPostHeaders') .
                App::core()->resource()->pageTabs($default_tab) .
                $next_headlink . "\n" . $prev_headlink
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name)        => '',
                __('Posts')                                        => App::core()->adminurl()->get('admin.posts'),
                ($this->post_id ? $page_title_edit : $page_title)  => '',
            ], [
                'x-frame-allow' => App::core()->blog()->url,
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        $categories_combo = App::core()->combo()->getCategoriesCombo(
            App::core()->blog()->categories()->getCategories()
        );

        $status_combo = App::core()->combo()->getPostStatusesCombo();

        $param = new Param();
        $param->set('order', 'asc');
        $lang_combo = App::core()->combo()->getLangsCombo(
            App::core()->blog()->posts()->getLangs(param: $param),
            true
        );

        $core_formaters    = App::core()->formater()->getFormaters();
        $available_formats = ['' => ''];
        foreach ($core_formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $available_formats[$format] = $format;
            }
        }

        if (!empty($_GET['upd'])) {
            App::core()->notice()->success(__('Entry has been successfully updated.'));
        } elseif (!empty($_GET['crea'])) {
            App::core()->notice()->success(__('Entry has been successfully created.'));
        } elseif (!empty($_GET['attached'])) {
            App::core()->notice()->success(__('File has been successfully attached.'));
        } elseif (!empty($_GET['rmattach'])) {
            App::core()->notice()->success(__('Attachment has been successfully removed.'));
        }

        if (!empty($_GET['creaco'])) {
            App::core()->notice()->success(__('Comment has been successfully created.'));
        }
        if (!empty($_GET['tbsent'])) {
            App::core()->notice()->success(__('All pings sent.'));
        }

        // XHTML conversion
        if (!empty($_GET['xconv'])) {
            $this->post_excerpt = $this->post_excerpt_xhtml;
            $this->post_content = $this->post_content_xhtml;
            $this->post_format  = 'xhtml';

            App::core()->notice()->message(__('Don\'t forget to validate your XHTML conversion by saving your post.'));
        }

        if ($this->post_id && 1 == $this->post_status) {
            echo '<p><a class="onblog_link outgoing" href="' . $this->post->getURL() . '" title="' . Html::escapeHTML(trim(Html::clean($this->post_title))) . '">' . __('Go to this entry on the site') . ' <img src="?df=images/outgoing-link.svg" alt="" /></a></p>';
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
            App::core()->behavior()->call('adminPostNavLinks', $this->post ?? null, 'post');

            echo '</p>';
        }

        // Exit if we cannot view page
        if (!$this->can_view_page) {
            return;
        }

        /* Post form if we can edit post
        -------------------------------------------------------- */
        if ($this->can_edit_post) {
            $sidebar_items = new ArrayObject([
                'status-box' => [
                    'title' => __('Status'),
                    'items' => [
                        'post_status' => '<p class="entry-status"><label for="post_status">' . __('Entry status') . ' ' . $this->img_status . '</label>' .
                        Form::combo(
                            'post_status',
                            $status_combo,
                            ['default' => $this->post_status, 'class' => 'maximal', 'disabled' => !$this->can_publish]
                        ) .
                        '</p>',
                        'post_dt' => '<p><label for="post_dt">' . __('Publication date and hour') . '</label>' .
                        Form::datetime('post_dt', [
                            'default' => Clock::formfield(date: $this->post_dt, to: App::core()->timezone()),
                            'class'   => ($this->bad_dt ? 'invalid' : ''),
                        ]) .
                        '</p>',
                        'post_lang' => '<p><label for="post_lang">' . __('Entry language') . '</label>' .
                        Form::combo('post_lang', $lang_combo, $this->post_lang) .
                        '</p>',
                        'post_format' => '<div>' .
                        '<h5 id="label_format"><label for="post_format" class="classic">' . __('Text formatting') . '</label></h5>' .
                        '<p>' . Form::combo('post_format', $available_formats, $this->post_format, 'maximal') . '</p>' .
                        '<p class="format_control control_no_xhtml">' .
                        '<a id="convert-xhtml" class="button' . ($this->post_id && 'wiki' != $this->post_format ? ' hide' : '') . '" href="' .
                        App::core()->adminurl()->get('admin.post', ['id' => $this->post_id, 'xconv' => '1']) .
                        '">' .
                        __('Convert to XHTML') . '</a></p></div>', ], ],
                'metas-box' => [
                    'title' => __('Filing'),
                    'items' => [
                        'post_selected' => '<p><label for="post_selected" class="classic">' .
                        Form::checkbox('post_selected', 1, $this->post_selected) . ' ' .
                        __('Selected entry') . '</label></p>',
                        'cat_id' => '<div>' .
                        '<h5 id="label_cat_id">' . __('Category') . '</h5>' .
                        '<p><label for="cat_id">' . __('Category:') . '</label>' .
                        Form::combo('cat_id', $categories_combo, $this->cat_id, 'maximal') .
                        '</p>' .
                        (App::core()->user()->check('categories', App::core()->blog()->id) ?
                            '<div>' .
                            '<h5 id="create_cat">' . __('Add a new category') . '</h5>' .
                            '<p><label for="new_cat_title">' . __('Title:') . ' ' .
                            Form::field('new_cat_title', 30, 255, ['class' => 'maximal']) . '</label></p>' .
                            '<p><label for="new_cat_parent">' . __('Parent:') . ' ' .
                            Form::combo('new_cat_parent', $categories_combo, '', 'maximal') .
                            '</label></p>' .
                            '</div>'
                            : '') .
                        '</div>', ], ],
                'options-box' => [
                    'title' => __('Options'),
                    'items' => [
                        'post_open_comment_tb' => '<div>' .
                        '<h5 id="label_comment_tb">' . __('Comments and trackbacks list') . '</h5>' .
                        '<p><label for="post_open_comment" class="classic">' .
                        Form::checkbox('post_open_comment', 1, $this->post_open_comment) . ' ' .
                        __('Accept comments') . '</label></p>' .
                        (App::core()->blog()->settings()->get('system')->get('allow_comments') ?
                            ($this->isContributionAllowed($this->post_id, Clock::ts(date: $this->post_dt), true) ?
                                '' :
                                '<p class="form-note warn">' .
                                __('Warning: Comments are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' .
                            __('Comments are not accepted on this blog so far.') . '</p>') .
                        '<p><label for="post_open_tb" class="classic">' .
                        Form::checkbox('post_open_tb', 1, $this->post_open_tb) . ' ' .
                        __('Accept trackbacks') . '</label></p>' .
                        (App::core()->blog()->settings()->get('system')->get('allow_trackbacks') ?
                            ($this->isContributionAllowed($this->post_id, Clock::ts(date: $this->post_dt), false) ?
                                '' :
                                '<p class="form-note warn">' .
                                __('Warning: Trackbacks are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' . __('Trackbacks are not accepted on this blog so far.') . '</p>') .
                        '</div>',
                        'post_password' => '<p><label for="post_password">' . __('Password') . '</label>' .
                        Form::field('post_password', 10, 32, Html::escapeHTML($this->post_password), 'maximal') .
                        '</p>',
                        'post_url' => '<div class="lockable">' .
                        '<p><label for="post_url">' . __('Edit basename') . '</label>' .
                        Form::field('post_url', 10, 255, Html::escapeHTML($this->post_url), 'maximal') .
                        '</p>' .
                        '<p class="form-note warn">' .
                        __('Warning: If you set the URL manually, it may conflict with another entry.') .
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
                    __('Introduction to the post.') . '</span></label> ' .
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

            // --BEHAVIOR-- adminPostFormItems, ArrayObject, ArrayObject, Record|null, string
            App::core()->behavior()->call('adminPostFormItems', $main_items, $sidebar_items, $this->post ?? null, 'post');

            echo '<div class="multi-part" title="' . ($this->post_id ? __('Edit post') : __('New post')) .
            sprintf(' &rsaquo; %s', $this->post_format) . '" id="edit-entry">';
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="entry-form">';
            echo '<div id="entry-wrapper">';
            echo '<div id="entry-content"><div class="constrained">';

            echo '<h3 class="out-of-screen-if-js">' . __('Edit post') . '</h3>';

            foreach ($main_items as $id => $item) {
                echo $item;
            }

            // --BEHAVIOR-- adminPostForm (may be deprecated)
            App::core()->behavior()->call('adminPostForm', $this->post ?? null, 'post');

            echo '<p class="border-top">' .
            ($this->post_id ? Form::hidden('id', $this->post_id) : '') .
            '<input type="submit" value="' . __('Save') . ' (s)" ' .
                'accesskey="s" name="save" /> ';
            if ($this->post_id) {
                $preview_url = App::core()->blog()->getURLFor('preview', App::core()->user()->userID() . '/' .
                    Http::browserUID(App::core()->config()->get('master_key') . App::core()->user()->userID() . App::core()->user()->cryptLegacy(App::core()->user()->userID())) .
                    '/' . $this->post->f('post_url'));
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
            App::core()->adminurl()->getHiddenFormFields('admin.post', [], true) .
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

            // --BEHAVIOR-- adminPostFormSidebar (may be deprecated)
            App::core()->behavior()->call('adminPostFormSidebar', $this->post ?? null, 'post');
            echo '</div>'; // End #entry-sidebar

            echo '</form>';

            // --BEHAVIOR-- adminPostForm
            App::core()->behavior()->call('adminPostAfterForm', $this->post ?? null, 'post');

            echo '</div>';
        }

        if ($this->post_id) {
            /* Comments
            -------------------------------------------------------- */

            $param = new Param();
            $param->set('post_id', $this->post_id);
            $param->set('order', 'comment_dt ASC');
            $param->set('comment_trackback', 0);

            $comments = App::core()->blog()->comments()->getComments(param: $param);

            echo '<div id="comments" class="clear multi-part" title="' . __('Comments') . '">';
            $combo_action = $this->comments_actions->getCombo();
            $has_action   = !empty($combo_action) && !$comments->isEmpty();
            echo '<p class="top-add"><a class="button add" href="#comment-form">' . __('Add a comment') . '</a></p>';

            if ($has_action) {
                echo '<form action="' . App::core()->adminurl()->root() . '" id="form-comments" method="post">';
            }

            echo '<h3>' . __('Comments') . '</h3>';
            if (!$comments->isEmpty()) {
                $this->showComments($comments, $has_action, false);
            } else {
                echo '<p>' . __('No comments') . '</p>';
            }

            if ($has_action) {
                echo '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
                Form::combo('action', $combo_action) .
                App::core()->adminurl()->getHiddenFormFields('admin.post', [
                    'section' => 'comments',
                    'id'      => $this->post_id,
                ], true) .
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
            Form::email('comment_email', 30, 255, Html::escapeHTML(App::core()->user()->getInfo('user_email'))) .
            '</p>' .

            '<p><label for="comment_site">' . __('Web site:') . '</label>' .
            Form::url('comment_site', 30, 255, Html::escapeHTML(App::core()->user()->getInfo('user_url'))) .
            '</p>' .

            '<p class="area"><label for="comment_content" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' .
            __('Comment:') . '</label> ' .
            Form::textarea(
                'comment_content',
                50,
                8,
                [
                    'extra_html' => 'required placeholder="' . __('Comment') . '" lang="' . App::core()->user()->getInfo('user_lang') .
                        '" spellcheck="true"',
                ]
            ) .
            '</p>' .

            '<p>' .
            Form::hidden('post_id', $this->post_id) .
            App::core()->adminurl()->getHiddenFormFields('admin.comment', [], true) .
            '<input type="submit" name="add" value="' . __('Save') . '" /></p>' .
            '</div>' . // constrained

            '</form>' .
            '</div>' . // add comment
            '</div>'; // comments
        }

        if ($this->post_id && 1 == $this->post_status) {
            /* Trackbacks
            -------------------------------------------------------- */

            $param = new Param();
            $param->set('post_id', $this->post_id);
            $param->set('order', 'comment_dt ASC');
            $param->set('comment_trackback', 1);

            $trackbacks = App::core()->blog()->comments()->getComments(param: $param);

            // Actions combo box
            $combo_action = $this->comments_actions->getCombo();
            $has_action   = !empty($combo_action) && !$trackbacks->isEmpty();

            if (!empty($_GET['tb_auto'])) {
                $this->tb_urls = implode("\n", $this->trackback->discover($this->post_excerpt_xhtml . ' ' . $this->post_content_xhtml));
            }

            // Display tab
            echo '<div id="trackbacks" class="clear multi-part" title="' . __('Trackbacks') . '">';

            // tracbacks actions
            if ($has_action) {
                echo '<form action="' . App::core()->adminurl()->root() . '" id="form-trackbacks" method="post">';
            }

            echo '<h3>' . __('Trackbacks received') . '</h3>';

            if (!$trackbacks->isEmpty()) {
                $this->showComments($trackbacks, $has_action, true);
            } else {
                echo '<p>' . __('No trackback') . '</p>';
            }

            if ($has_action) {
                echo '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected trackbacks action:') . '</label> ' .
                Form::combo('action', $combo_action) .
                Form::hidden('id', $this->post_id) .
                App::core()->adminurl()->getHiddenFormFields('admin.post', ['section' => 'trackbacks'], true) .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                    '</div>' .
                    '</form>';
            }

            /* Add trackbacks
            -------------------------------------------------------- */
            if ($this->can_edit_post && $this->post->f('post_status')) {
                echo '<div class="fieldset clear">';

                echo '<h3>' . __('Ping blogs') . '</h3>' .
                '<form action="' . App::core()->adminurl()->root() . '" id="trackback-form" method="post">' .
                '<p><label for="tb_urls" class="area">' . __('URLs to ping:') . '</label>' .
                Form::textarea('tb_urls', 60, 5, $this->tb_urls) .
                '</p>' .

                '<p><label for="tb_excerpt" class="area">' . __('Excerpt to send:') . '</label>' .
                Form::textarea('tb_excerpt', 60, 5, $this->tb_excerpt) . '</p>' .

                '<p>' .
                App::core()->adminurl()->getHiddenFormFields('admin.post', ['id' => $this->post_id], true) .
                '<input type="submit" name="ping" value="' . __('Ping blogs') . '" />' .
                    (empty($_GET['tb_auto']) ?
                    '&nbsp;&nbsp;<a class="button" href="' .
                    App::core()->adminurl()->get('admin.post', ['id' => $this->post_id, 'tb_auto' => 1, 'tb' => 1]) .
                    '">' . __('Auto discover ping URLs') . '</a>'
                    : '') .
                    '</p>' .
                    '</form>';

                $pings = $this->trackback->getPostPings($this->post_id);

                if (!$pings->isEmpty()) {
                    echo '<h3>' . __('Previously sent pings') . '</h3>';

                    echo '<ul class="nice">';
                    while ($pings->fetch()) {
                        echo '<li>' . Clock::str(format: __('%Y-%m-%d %H:%M'), date: $pings->ping_dt, to: App::core()->timezone()) . ' - ' .
                        $pings->ping_url . '</li>';
                    }
                    echo '</ul>';
                }

                echo '</div>';
            }

            echo '</div>'; // trackbacks
        }
    }

    // Controls comments or trakbacks capabilities
    protected function isContributionAllowed($id, $dt, $com = true)
    {
        if (!$id) {
            return true;
        }
        if ($com) {
            if (0 == App::core()->blog()->settings()->get('system')->get('comments_ttl') || $dt > (Clock::ts() - App::core()->blog()->settings()->get('system')->get('comments_ttl') * 86400)) {
                return true;
            }
        } else {
            if (0 == App::core()->blog()->settings()->get('system')->get('trackbacks_ttl') || $dt > (Clock::ts() - App::core()->blog()->settings()->get('system')->get('trackbacks_ttl') * 86400)) {
                return true;
            }
        }

        return false;
    }

    // Show comments or trackbacks
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
        if (isset($_REQUEST['comments'])) {
            foreach ($_REQUEST['comments'] as $v) {
                $comments[(int) $v] = true;
            }
        }

        while ($rs->fetch()) {
            $comment_url = App::core()->adminurl()->get('admin.comment', ['id' => $rs->f('comment_id')]);

            $img              = '<img alt="%1$s" title="%1$s" src="?df=images/%2$s" />';
            $this->img_status = '';
            $sts_class        = '';

            switch ($rs->fInt('comment_status')) {
                case 1:
                    $this->img_status = sprintf($img, __('Published'), 'check-on.png');
                    $sts_class        = 'sts-online';

                    break;

                case 0:
                    $this->img_status = sprintf($img, __('Unpublished'), 'check-off.png');
                    $sts_class        = 'sts-offline';

                    break;

                case -1:
                    $this->img_status = sprintf($img, __('Pending'), 'check-wrn.png');
                    $sts_class        = 'sts-pending';

                    break;

                case -2:
                    $this->img_status = sprintf($img, __('Junk'), 'junk.png');
                    $sts_class        = 'sts-junk';

                    break;
            }

            echo '<tr class="line ' . (1 != $rs->f('comment_status') ? ' offline ' : '') . $sts_class . '"' .
            ' id="c' . $rs->f('comment_id') . '">' .

            '<td class="nowrap">' .
            ($has_action ? Form::checkbox(
                ['comments[]'],
                $rs->f('comment_id'),
                [
                    'checked'    => isset($comments[$rs->fInt('comment_id')]),
                    'extra_html' => 'title="' . ($tb ? __('select this trackback') : __('select this comment') . '"'),
                ]
            ) : '') . '</td>' .
            '<td class="maximal">' . Html::escapeHTML($rs->f('comment_author')) . '</td>' .
            '<td class="nowrap">' . clock::str(__('%Y-%m-%d %H:%M'), $rs->f('comment_dt'), to: App::core()->user()->getInfo('user_tz')) . '</td>' .
            ($this->can_view_ip ?
                '<td class="nowrap"><a href="' . App::core()->adminurl()->get('admin.comments', ['ip' => $rs->f('comment_ip')]) . '">' . $rs->f('comment_ip') . '</a></td>' : '') .
            '<td class="nowrap status">' . $this->img_status . '</td>' .
            '<td class="nowrap status"><a href="' . $comment_url . '">' .
            '<img src="?df=images/edit-mini.png" alt="" title="' . __('Edit this comment') . '" /> ' . __('Edit') . '</a></td>' .

                '</tr>';
        }

        echo '</table></div>';
    }
}
