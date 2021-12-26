<?php
/**
 * @class Dotclear\Admin\Page\Post
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
use Dotclear\Core\Media;
use Dotclear\Core\Trackback;

use Dotclear\Admin\Page;
use Dotclear\Admin\Combos;
use Dotclear\Admin\Action\CommentAction;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Utils\Dt;
use Dotclear\Utils\Text;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Post extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->check('usage,contentadmin');

        $show_ip = $core->auth->check('contentadmin', $core->blog->id);

        $post_id            = '';
        $cat_id             = '';
        $post_dt            = '';
        $post_format        = $core->auth->getOption('post_format');
        $post_editor        = $core->auth->getOption('editor');
        $post_password      = '';
        $post_url           = '';
        $post_lang          = $core->auth->getInfo('user_lang');
        $post_title         = '';
        $post_excerpt       = '';
        $post_excerpt_xhtml = '';
        $post_content       = '';
        $post_content_xhtml = '';
        $post_notes         = '';
        $post_status        = $core->auth->getInfo('user_post_status');
        $post_selected      = false;
        $post_open_comment  = $core->blog->settings->system->allow_comments;
        $post_open_tb       = $core->blog->settings->system->allow_trackbacks;

        $page_title = __('New post');

        $can_view_page = true;
        $can_edit_post = $core->auth->check('usage,contentadmin', $core->blog->id);
        $can_publish   = $core->auth->check('publish,contentadmin', $core->blog->id);
        $can_delete    = false;

        $post_headlink = '<link rel="%s" title="%s" href="' . $core->adminurl->get('admin.post', ['id' => '%s'], '&amp;', true) . '" />';
        $post_link     = '<a href="' . $core->adminurl->get('admin.post', ['id' => '%s'], '&amp;', true) . '" title="%s">%s</a>';
        $next_link     = $prev_link     = $next_headlink     = $prev_headlink     = null;

        # If user can't publish
        if (!$can_publish) {
            $post_status = -2;
        }

        # Getting categories
        $categories_combo = Combos::getCategoriesCombo(
            $core->blog->getCategories()
        );

        $status_combo = Combos::getPostStatusesCombo();

        $img_status_pattern = '<img class="img_select_option" alt="%1$s" title="%1$s" src="?df=images/%2$s" />';

        # Formats combo
        $core_formaters    = $core->getFormaters();
        $available_formats = ['' => ''];
        foreach ($core_formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $available_formats[$format] = $format;
            }
        }

        # Languages combo
        $rs         = $core->blog->getLangs(['order' => 'asc']);
        $lang_combo = Combos::getLangsCombo($rs, true);

        # Validation flag
        $bad_dt = false;

        # Trackbacks
        $TB      = new Trackback($core);
        $tb_urls = $tb_excerpt = '';

        # Get entry informations
        $post = null;

        if (!empty($_REQUEST['id'])) {
            $page_title = __('Edit post');

            $params['post_id'] = $_REQUEST['id'];

            $post = $core->blog->getPosts($params);

            if ($post->isEmpty()) {
                $core->error->add(__('This entry does not exist.'));
                $can_view_page = false;
            } else {
                $post_id            = $post->post_id;
                $cat_id             = $post->cat_id;
                $post_dt            = date('Y-m-d H:i', strtotime($post->post_dt));
                $post_format        = $post->post_format;
                $post_password      = $post->post_password;
                $post_url           = $post->post_url;
                $post_lang          = $post->post_lang;
                $post_title         = $post->post_title;
                $post_excerpt       = $post->post_excerpt;
                $post_excerpt_xhtml = $post->post_excerpt_xhtml;
                $post_content       = $post->post_content;
                $post_content_xhtml = $post->post_content_xhtml;
                $post_notes         = $post->post_notes;
                $post_status        = $post->post_status;
                $post_selected      = (bool) $post->post_selected;
                $post_open_comment  = (bool) $post->post_open_comment;
                $post_open_tb       = (bool) $post->post_open_tb;

                $can_edit_post = $post->isEditable();
                $can_delete    = $post->isDeletable();

                $next_rs = $core->blog->getNextPost($post, 1);
                $prev_rs = $core->blog->getNextPost($post, -1);

                if ($next_rs !== null) {
                    $next_link = sprintf(
                        $post_link,
                        $next_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        __('Next entry') . '&nbsp;&#187;'
                    );
                    $next_headlink = sprintf(
                        $post_headlink,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        $next_rs->post_id
                    );
                }

                if ($prev_rs !== null) {
                    $prev_link = sprintf(
                        $post_link,
                        $prev_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        '&#171;&nbsp;' . __('Previous entry')
                    );
                    $prev_headlink = sprintf(
                        $post_headlink,
                        'previous',
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        $prev_rs->post_id
                    );
                }

                try {
                    $core->media = new Media($core);
                } catch (Exception $e) {
                    $core->error->add($e->getMessage());
                }

                # Sanitize trackbacks excerpt
                $tb_excerpt = empty($_POST['tb_excerpt']) ?
                $post_excerpt_xhtml . ' ' . $post_content_xhtml :
                $_POST['tb_excerpt'];
                $tb_excerpt = Html::decodeEntities(Html::clean($tb_excerpt));
                $tb_excerpt = Text::cutString(Html::escapeHTML($tb_excerpt), 255);
                $tb_excerpt = preg_replace('/\s+/ms', ' ', $tb_excerpt);
            }
        }
        if (isset($_REQUEST['section']) && $_REQUEST['section'] == 'trackbacks') {
            $anchor = 'trackbacks';
        } else {
            $anchor = 'comments';
        }

        $comments_actions_page = new CommentAction($core, $core->adminurl->get('admin.post'), ['id' => $post_id, '_ANCHOR' => $anchor, 'section' => $anchor]);

        if ($comments_actions_page->process()) {
            return;
        }

        # Ping blogs
        if (!empty($_POST['ping'])) {
            if (!empty($_POST['tb_urls']) && $post_id && $post_status == 1 && $can_edit_post) {
                $tb_urls       = $_POST['tb_urls'];
                $tb_urls       = str_replace("\r", '', $tb_urls);
                $tb_post_title = Html::escapeHTML(trim(Html::clean($post_title)));
                $tb_post_url   = $post->getURL();

                foreach (explode("\n", $tb_urls) as $tb_url) {
                    try {
                        # --BEHAVIOR-- adminBeforePingTrackback
                        $core->callBehavior('adminBeforePingTrackback', $tb_url, $post_id, $tb_post_title, $tb_excerpt, $tb_post_url);

                        $TB->ping($tb_url, $post_id, $tb_post_title, $tb_excerpt, $tb_post_url);
                    } catch (Exception $e) {
                        $core->error->add($e->getMessage());
                    }
                }

                if (!$core->error->flag()) {
                    static::addSuccessNotice(__('All pings sent.'));
                    $core->adminurl->redirect(
                        'admin.post',
                        ['id' => $post_id, 'tb' => '1']
                    );
                }
            }
        }

        # Format excerpt and content
        elseif (!empty($_POST) && $can_edit_post) {
            $post_format  = $_POST['post_format'];
            $post_excerpt = $_POST['post_excerpt'];
            $post_content = $_POST['post_content'];

            $post_title = $_POST['post_title'];

            $cat_id = (int) $_POST['cat_id'];

            if (isset($_POST['post_status'])) {
                $post_status = (int) $_POST['post_status'];
            }

            if (empty($_POST['post_dt'])) {
                $post_dt = '';
            } else {
                try {
                    $post_dt = strtotime($_POST['post_dt']);
                    if ($post_dt == false || $post_dt == -1) {
                        $bad_dt = true;

                        throw new AdminException(__('Invalid publication date'));
                    }
                    $post_dt = date('Y-m-d H:i', $post_dt);
                } catch (Exception $e) {
                    $core->error->add($e->getMessage());
                }
            }

            $post_open_comment = !empty($_POST['post_open_comment']);
            $post_open_tb      = !empty($_POST['post_open_tb']);
            $post_selected     = !empty($_POST['post_selected']);
            $post_lang         = $_POST['post_lang'];
            $post_password     = !empty($_POST['post_password']) ? $_POST['post_password'] : null;

            $post_notes = $_POST['post_notes'];

            if (isset($_POST['post_url'])) {
                $post_url = $_POST['post_url'];
            }

            $core->blog->setPostContent(
                $post_id,
                $post_format,
                $post_lang,
                $post_excerpt,
                $post_excerpt_xhtml,
                $post_content,
                $post_content_xhtml
            );
        }

        # Delete post
        if (!empty($_POST['delete']) && $can_delete) {
            try {
                # --BEHAVIOR-- adminBeforePostDelete
                $core->callBehavior('adminBeforePostDelete', $post_id);
                $core->blog->delPost($post_id);
                $core->adminurl->redirect('admin.posts');
            } catch (Exception $e) {
                $core->error->add($e->getMessage());
            }
        }

        # Create or update post
        if (!empty($_POST) && !empty($_POST['save']) && $can_edit_post && !$bad_dt) {
            # Create category
            if (!empty($_POST['new_cat_title']) && $core->auth->check('categories', $core->blog->id)) {
                $cur_cat            = $core->con->openCursor($core->prefix . 'category');
                $cur_cat->cat_title = $_POST['new_cat_title'];
                $cur_cat->cat_url   = '';

                $parent_cat = !empty($_POST['new_cat_parent']) ? $_POST['new_cat_parent'] : '';

                # --BEHAVIOR-- adminBeforeCategoryCreate
                $core->callBehavior('adminBeforeCategoryCreate', $cur_cat);

                $cat_id = $core->blog->addCategory($cur_cat, (int) $parent_cat);

                # --BEHAVIOR-- adminAfterCategoryCreate
                $core->callBehavior('adminAfterCategoryCreate', $cur_cat, $cat_id);
            }

            $cur = $core->con->openCursor($core->prefix . 'post');

            $cur->cat_id             = ($cat_id ?: null);
            $cur->post_dt            = $post_dt ? date('Y-m-d H:i:00', strtotime($post_dt)) : '';
            $cur->post_format        = $post_format;
            $cur->post_password      = $post_password;
            $cur->post_lang          = $post_lang;
            $cur->post_title         = $post_title;
            $cur->post_excerpt       = $post_excerpt;
            $cur->post_excerpt_xhtml = $post_excerpt_xhtml;
            $cur->post_content       = $post_content;
            $cur->post_content_xhtml = $post_content_xhtml;
            $cur->post_notes         = $post_notes;
            $cur->post_status        = $post_status;
            $cur->post_selected      = (int) $post_selected;
            $cur->post_open_comment  = (int) $post_open_comment;
            $cur->post_open_tb       = (int) $post_open_tb;

            if (isset($_POST['post_url'])) {
                $cur->post_url = $post_url;
            }

            # Update post
            if ($post_id) {
                try {
                    # --BEHAVIOR-- adminBeforePostUpdate
                    $core->callBehavior('adminBeforePostUpdate', $cur, $post_id);

                    $core->blog->updPost($post_id, $cur);

                    # --BEHAVIOR-- adminAfterPostUpdate
                    $core->callBehavior('adminAfterPostUpdate', $cur, $post_id);
                    static::addSuccessNotice(sprintf(__('The post "%s" has been successfully updated'), Html::escapeHTML(trim(Html::clean($cur->post_title)))));
                    $core->adminurl->redirect(
                        'admin.post',
                        ['id' => $post_id]
                    );
                } catch (Exception $e) {
                    $core->error->add($e->getMessage());
                }
            } else {
                $cur->user_id = $core->auth->userID();

                try {
                    # --BEHAVIOR-- adminBeforePostCreate
                    $core->callBehavior('adminBeforePostCreate', $cur);

                    $return_id = $core->blog->addPost($cur);

                    # --BEHAVIOR-- adminAfterPostCreate
                    $core->callBehavior('adminAfterPostCreate', $cur, $return_id);

                    static::addSuccessNotice(__('Entry has been successfully created.'));
                    $core->adminurl->redirect(
                        'admin.post',
                        ['id' => $return_id]
                    );
                } catch (Exception $e) {
                    $core->error->add($e->getMessage());
                }
            }
        }

        # Getting categories
        $categories_combo = Combos::getCategoriesCombo(
            $core->blog->getCategories()
        );
        /* DISPLAY
        -------------------------------------------------------- */
        $default_tab = 'edit-entry';
        if (!$can_edit_post) {
            $default_tab = '';
        }
        if (!empty($_GET['co'])) {
            $default_tab = 'comments';
        } elseif (!empty($_GET['tb'])) {
            $default_tab = 'trackbacks';
        }

        if ($post_id) {
            switch ($post_status) {
                case 1:
                    $img_status = sprintf($img_status_pattern, __('Published'), 'check-on.png');

                    break;
                case 0:
                    $img_status = sprintf($img_status_pattern, __('Unpublished'), 'check-off.png');

                    break;
                case -1:
                    $img_status = sprintf($img_status_pattern, __('Scheduled'), 'scheduled.png');

                    break;
                case -2:
                    $img_status = sprintf($img_status_pattern, __('Pending'), 'check-wrn.png');

                    break;
                default:
                    $img_status = '';
            }
            $edit_entry_str  = __('&ldquo;%s&rdquo;');
            $page_title_edit = sprintf($edit_entry_str, Html::escapeHTML(trim(Html::clean($post_title)))) . ' ' . $img_status;
        } else {
            $img_status      = '';
            $page_title_edit = '';
        }

        $admin_post_behavior = '';
        if ($post_editor) {
            $p_edit = $c_edit = '';
            if (!empty($post_editor[$post_format])) {
                $p_edit = $post_editor[$post_format];
            }
            if (!empty($post_editor['xhtml'])) {
                $c_edit = $post_editor['xhtml'];
            }
            if ($p_edit == $c_edit) {
                $admin_post_behavior .= $core->callBehavior(
                    'adminPostEditor',
                    $p_edit,
                    'post',
                    ['#post_excerpt', '#post_content', '#comment_content'],
                    $post_format
                );
            } else {
                $admin_post_behavior .= $core->callBehavior(
                    'adminPostEditor',
                    $p_edit,
                    'post',
                    ['#post_excerpt', '#post_content'],
                    $post_format
                );
                $admin_post_behavior .= $core->callBehavior(
                    'adminPostEditor',
                    $c_edit,
                    'comment',
                    ['#comment_content'],
                    'xhtml'
                );
            }
        }

        $this->open(
            $page_title . ' - ' . __('Posts'),
            static::jsModal() .
            static::jsMetaEditor() .
            $admin_post_behavior .
            static::jsLoad('js/_post.js') .
            static::jsConfirmClose('entry-form', 'comment-form') .
            # --BEHAVIOR-- adminPostHeaders
            $core->callBehavior('adminPostHeaders') .
            static::jsPageTabs($default_tab) .
            $next_headlink . "\n" . $prev_headlink,
            $this->breadcrumb(
                [
                    Html::escapeHTML($core->blog->name)         => '',
                    __('Posts')                                 => $core->adminurl->get('admin.posts'),
                    ($post_id ? $page_title_edit : $page_title) => '',
                ]
            ),
            [
                'x-frame-allow' => $core->blog->url,
            ]
        );

        if (!empty($_GET['upd'])) {
            static::success(__('Entry has been successfully updated.'));
        } elseif (!empty($_GET['crea'])) {
            static::success(__('Entry has been successfully created.'));
        } elseif (!empty($_GET['attached'])) {
            static::success(__('File has been successfully attached.'));
        } elseif (!empty($_GET['rmattach'])) {
            static::success(__('Attachment has been successfully removed.'));
        }

        if (!empty($_GET['creaco'])) {
            static::success(__('Comment has been successfully created.'));
        }
        if (!empty($_GET['tbsent'])) {
            static::success(__('All pings sent.'));
        }

        # XHTML conversion
        if (!empty($_GET['xconv'])) {
            $post_excerpt = $post_excerpt_xhtml;
            $post_content = $post_content_xhtml;
            $post_format  = 'xhtml';

            static::message(__('Don\'t forget to validate your XHTML conversion by saving your post.'));
        }

        if ($post_id && $post->post_status == 1) {
            echo '<p><a class="onblog_link outgoing" href="' . $post->getURL() . '" title="' . Html::escapeHTML(trim(Html::clean($post_title))) . '">' . __('Go to this entry on the site') . ' <img src="?df=images/outgoing-link.svg" alt="" /></a></p>';
        }
        if ($post_id) {
            echo '<p class="nav_prevnext">';
            if ($prev_link) {
                echo $prev_link;
            }
            if ($next_link && $prev_link) {
                echo ' | ';
            }
            if ($next_link) {
                echo $next_link;
            }

            # --BEHAVIOR-- adminPostNavLinks
            $core->callBehavior('adminPostNavLinks', $post ?? null, 'post');

            echo '</p>';
        }

        # Exit if we cannot view page
        if (!$can_view_page) {
            $this->helpBlock('core_post');
            $this->close();
            exit;
        }

        /* Post form if we can edit post
        -------------------------------------------------------- */
        if ($can_edit_post) {
            $sidebar_items = new \ArrayObject([
                'status-box' => [
                    'title' => __('Status'),
                    'items' => [
                        'post_status' => '<p class="entry-status"><label for="post_status">' . __('Entry status') . ' ' . $img_status . '</label>' .
                        Form::combo(
                            'post_status',
                            $status_combo,
                            ['default' => $post_status, 'class' => 'maximal', 'disabled' => !$can_publish]
                        ) .
                        '</p>',
                        'post_dt' => '<p><label for="post_dt">' . __('Publication date and hour') . '</label>' .
                        Form::datetime('post_dt', [
                            'default' => Html::escapeHTML(Dt::str('%Y-%m-%d\T%H:%M', strtotime($post_dt))),
                            'class'   => ($bad_dt ? 'invalid' : ''),
                        ]) .
                        '</p>',
                        'post_lang' => '<p><label for="post_lang">' . __('Entry language') . '</label>' .
                        Form::combo('post_lang', $lang_combo, $post_lang) .
                        '</p>',
                        'post_format' => '<div>' .
                        '<h5 id="label_format"><label for="post_format" class="classic">' . __('Text formatting') . '</label></h5>' .
                        '<p>' . Form::combo('post_format', $available_formats, $post_format, 'maximal') . '</p>' .
                        '<p class="format_control control_no_xhtml">' .
                        '<a id="convert-xhtml" class="button' . ($post_id && $post_format != 'wiki' ? ' hide' : '') . '" href="' .
                        $core->adminurl->get('admin.post', ['id' => $post_id, 'xconv' => '1']) .
                        '">' .
                        __('Convert to XHTML') . '</a></p></div>', ], ],
                'metas-box' => [
                    'title' => __('Filing'),
                    'items' => [
                        'post_selected' => '<p><label for="post_selected" class="classic">' .
                        Form::checkbox('post_selected', 1, $post_selected) . ' ' .
                        __('Selected entry') . '</label></p>',
                        'cat_id' => '<div>' .
                        '<h5 id="label_cat_id">' . __('Category') . '</h5>' .
                        '<p><label for="cat_id">' . __('Category:') . '</label>' .
                        Form::combo('cat_id', $categories_combo, $cat_id, 'maximal') .
                        '</p>' .
                        ($core->auth->check('categories', $core->blog->id) ?
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
                        Form::checkbox('post_open_comment', 1, $post_open_comment) . ' ' .
                        __('Accept comments') . '</label></p>' .
                        ($core->blog->settings->system->allow_comments ?
                            ($this->isContributionAllowed($post_id, strtotime($post_dt), true) ?
                                '' :
                                '<p class="form-note warn">' .
                                __('Warning: Comments are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' .
                            __('Comments are not accepted on this blog so far.') . '</p>') .
                        '<p><label for="post_open_tb" class="classic">' .
                        Form::checkbox('post_open_tb', 1, $post_open_tb) . ' ' .
                        __('Accept trackbacks') . '</label></p>' .
                        ($core->blog->settings->system->allow_trackbacks ?
                            ($this->isContributionAllowed($post_id, strtotime($post_dt), false) ?
                                '' :
                                '<p class="form-note warn">' .
                                __('Warning: Trackbacks are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' . __('Trackbacks are not accepted on this blog so far.') . '</p>') .
                        '</div>',
                        'post_password' => '<p><label for="post_password">' . __('Password') . '</label>' .
                        Form::field('post_password', 10, 32, Html::escapeHTML($post_password), 'maximal') .
                        '</p>',
                        'post_url' => '<div class="lockable">' .
                        '<p><label for="post_url">' . __('Edit basename') . '</label>' .
                        Form::field('post_url', 10, 255, Html::escapeHTML($post_url), 'maximal') .
                        '</p>' .
                        '<p class="form-note warn">' .
                        __('Warning: If you set the URL manually, it may conflict with another entry.') .
                        '</p></div>',
                    ], ], ]);

            $main_items = new \ArrayObject(
                [
                    'post_title' => '<p class="col">' .
                    '<label class="required no-margin bold" for="post_title"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label>' .
                    Form::field('post_title', 20, 255, [
                        'default'    => Html::escapeHTML($post_title),
                        'class'      => 'maximal',
                        'extra_html' => 'required placeholder="' . __('Title') . '" lang="' . $post_lang . '" spellcheck="true"',
                    ]) .
                    '</p>',

                    'post_excerpt' => '<p class="area" id="excerpt-area"><label for="post_excerpt" class="bold">' . __('Excerpt:') . ' <span class="form-note">' .
                    __('Introduction to the post.') . '</span></label> ' .
                    Form::textarea(
                        'post_excerpt',
                        50,
                        5,
                        [
                            'default'    => Html::escapeHTML($post_excerpt),
                            'extra_html' => 'lang="' . $post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',

                    'post_content' => '<p class="area" id="content-area"><label class="required bold" ' .
                    'for="post_content"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Content:') . '</label> ' .
                    Form::textarea(
                        'post_content',
                        50,
                        $core->auth->getOption('edit_size'),
                        [
                            'default'    => Html::escapeHTML($post_content),
                            'extra_html' => 'required placeholder="' . __('Content') . '" lang="' . $post_lang . '" spellcheck="true"',
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
                            'default'    => Html::escapeHTML($post_notes),
                            'extra_html' => 'lang="' . $post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',
                ]
            );

            # --BEHAVIOR-- adminPostFormItems
            $core->callBehavior('adminPostFormItems', $main_items, $sidebar_items, $post ?? null, 'post');

            echo '<div class="multi-part" title="' . ($post_id ? __('Edit post') : __('New post')) .
            sprintf(' &rsaquo; %s', $post_format) . '" id="edit-entry">';
            echo '<form action="' . $core->adminurl->get('admin.post') . '" method="post" id="entry-form">';
            echo '<div id="entry-wrapper">';
            echo '<div id="entry-content"><div class="constrained">';

            echo '<h3 class="out-of-screen-if-js">' . __('Edit post') . '</h3>';

            foreach ($main_items as $id => $item) {
                echo $item;
            }

            # --BEHAVIOR-- adminPostForm (may be deprecated)
            $core->callBehavior('adminPostForm', $post ?? null, 'post');

            echo
            '<p class="border-top">' .
            ($post_id ? Form::hidden('id', $post_id) : '') .
            '<input type="submit" value="' . __('Save') . ' (s)" ' .
                'accesskey="s" name="save" /> ';
            if ($post_id) {
                $preview_url = $core->blog->url . $core->url->getURLFor('preview', $core->auth->userID() . '/' .
                    Http::browserUID(DOTCLEAR_MASTER_KEY . $core->auth->userID() . $core->auth->cryptLegacy($core->auth->userID())) .
                    '/' . $post->post_url);

                $core->auth->user_prefs->addWorkspace('interface');
                $blank_preview = $core->auth->user_prefs->interface->blank_preview;

                $preview_class  = $blank_preview ? '' : ' modal';
                $preview_target = $blank_preview ? '' : ' target="_blank"';

                echo '<a id="post-preview" href="' . $preview_url . '" class="button' . $preview_class . '" accesskey="p"' . $preview_target . '>' . __('Preview') . ' (p)' . '</a>';
                echo ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';
            } else {
                echo
                '<a id="post-cancel" href="' . $core->adminurl->get('admin.home') . '" class="button" accesskey="c">' . __('Cancel') . ' (c)</a>';
            }

            echo($can_delete ? ' <input type="submit" class="delete" value="' . __('Delete') . '" name="delete" />' : '') .
            $core->formNonce() .
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

            # --BEHAVIOR-- adminPostFormSidebar (may be deprecated)
            $core->callBehavior('adminPostFormSidebar', $post ?? null, 'post');
            echo '</div>'; // End #entry-sidebar

            echo '</form>';

            # --BEHAVIOR-- adminPostForm
            $core->callBehavior('adminPostAfterForm', $post ?? null, 'post');

            echo '</div>';
        }

        if ($post_id) {
            /* Comments
            -------------------------------------------------------- */

            $params = ['post_id' => $post_id, 'order' => 'comment_dt ASC'];

            $comments = $core->blog->getComments(array_merge($params, ['comment_trackback' => 0]));

            echo
            '<div id="comments" class="clear multi-part" title="' . __('Comments') . '">';
            $combo_action = $comments_actions_page->getCombo();
            $has_action   = !empty($combo_action) && !$comments->isEmpty();
            echo
            '<p class="top-add"><a class="button add" href="#comment-form">' . __('Add a comment') . '</a></p>';

            if ($has_action) {
                echo '<form action="' . $core->adminurl->get('admin.post') . '" id="form-comments" method="post">';
            }

            echo '<h3>' . __('Comments') . '</h3>';
            if (!$comments->isEmpty()) {
                $this->showComments($comments, $has_action, false, $show_ip);
            } else {
                echo '<p>' . __('No comments') . '</p>';
            }

            if ($has_action) {
                echo
                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
                Form::combo('action', $combo_action) .
                Form::hidden(['section'], 'comments') .
                Form::hidden(['id'], $post_id) .
                $core->formNonce() .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                    '</div>' .
                    '</form>';
            }
            /* Add a comment
            -------------------------------------------------------- */

            echo
            '<div class="fieldset clear">' .
            '<h3>' . __('Add a comment') . '</h3>' .

            '<form action="' . $core->adminurl->get('admin.comment') . '" method="post" id="comment-form">' .
            '<div class="constrained">' .
            '<p><label for="comment_author" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Name:') . '</label>' .
            Form::field('comment_author', 30, 255, [
                'default'    => Html::escapeHTML($core->auth->getInfo('user_cn')),
                'extra_html' => 'required placeholder="' . __('Author') . '"',
            ]) .
            '</p>' .

            '<p><label for="comment_email">' . __('Email:') . '</label>' .
            Form::email('comment_email', 30, 255, Html::escapeHTML($core->auth->getInfo('user_email'))) .
            '</p>' .

            '<p><label for="comment_site">' . __('Web site:') . '</label>' .
            Form::url('comment_site', 30, 255, Html::escapeHTML($core->auth->getInfo('user_url'))) .
            '</p>' .

            '<p class="area"><label for="comment_content" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' .
            __('Comment:') . '</label> ' .
            Form::textarea(
                'comment_content',
                50,
                8,
                [
                    'extra_html' => 'required placeholder="' . __('Comment') . '" lang="' . $core->auth->getInfo('user_lang') .
                        '" spellcheck="true"',
                ]
            ) .
            '</p>' .

            '<p>' .
            Form::hidden('post_id', $post_id) .
            $core->formNonce() .
            '<input type="submit" name="add" value="' . __('Save') . '" /></p>' .
            '</div>' . #constrained

            '</form>' .
            '</div>' . #add comment
            '</div>'; #comments
        }

        if ($post_id && $post_status == 1) {
            /* Trackbacks
            -------------------------------------------------------- */

            $params     = ['post_id' => $post_id, 'order' => 'comment_dt ASC'];
            $trackbacks = $core->blog->getComments(array_merge($params, ['comment_trackback' => 1]));

            # Actions combo box
            $combo_action = $comments_actions_page->getCombo();
            $has_action   = !empty($combo_action) && !$trackbacks->isEmpty();

            if (!empty($_GET['tb_auto'])) {
                $tb_urls = implode("\n", $TB->discover($post_excerpt_xhtml . ' ' . $post_content_xhtml));
            }

            # Display tab
            echo
            '<div id="trackbacks" class="clear multi-part" title="' . __('Trackbacks') . '">';

            # tracbacks actions
            if ($has_action) {
                echo '<form action="' . $core->adminurl->get('admin.post') . '" id="form-trackbacks" method="post">';
            }

            echo '<h3>' . __('Trackbacks received') . '</h3>';

            if (!$trackbacks->isEmpty()) {
                $this->showComments($trackbacks, $has_action, true, $show_ip);
            } else {
                echo '<p>' . __('No trackback') . '</p>';
            }

            if ($has_action) {
                echo
                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected trackbacks action:') . '</label> ' .
                Form::combo('action', $combo_action) .
                Form::hidden('id', $post_id) .
                Form::hidden(['section'], 'trackbacks') .
                $core->formNonce() .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                    '</div>' .
                    '</form>';
            }

            /* Add trackbacks
            -------------------------------------------------------- */
            if ($can_edit_post && $post->post_status) {
                echo
                    '<div class="fieldset clear">';

                echo
                '<h3>' . __('Ping blogs') . '</h3>' .
                '<form action="' . $core->adminurl->get('admin.post', ['id' => $post_id]) . '" id="trackback-form" method="post">' .
                '<p><label for="tb_urls" class="area">' . __('URLs to ping:') . '</label>' .
                Form::textarea('tb_urls', 60, 5, $tb_urls) .
                '</p>' .

                '<p><label for="tb_excerpt" class="area">' . __('Excerpt to send:') . '</label>' .
                Form::textarea('tb_excerpt', 60, 5, $tb_excerpt) . '</p>' .

                '<p>' .
                $core->formNonce() .
                '<input type="submit" name="ping" value="' . __('Ping blogs') . '" />' .
                    (empty($_GET['tb_auto']) ?
                    '&nbsp;&nbsp;<a class="button" href="' .
                    $core->adminurl->get('admin.post', ['id' => $post_id, 'tb_auto' => 1, 'tb' => 1]) .
                    '">' . __('Auto discover ping URLs') . '</a>'
                    : '') .
                    '</p>' .
                    '</form>';

                $pings = $TB->getPostPings($post_id);

                if (!$pings->isEmpty()) {
                    echo '<h3>' . __('Previously sent pings') . '</h3>';

                    echo '<ul class="nice">';
                    while ($pings->fetch()) {
                        echo
                        '<li>' . Dt::dt2str(__('%Y-%m-%d %H:%M'), $pings->ping_dt) . ' - ' .
                        $pings->ping_url . '</li>';
                    }
                    echo '</ul>';
                }

                echo '</div>';
            }

            echo '</div>'; #trackbacks
        }

        $this->helpBlock('core_post', 'core_trackbacks', 'core_wiki');
        $this->close();
    }

    # Controls comments or trakbacks capabilities
     protected function isContributionAllowed($id, $dt, $com = true)
     {
        if (!$id) {
            return true;
        }
        if ($com) {
            if (($this->core->blog->settings->system->comments_ttl == 0) || (time() - $this->core->blog->settings->system->comments_ttl * 86400 < $dt)) {
                return true;
            }
        } else {
            if (($this->core->blog->settings->system->trackbacks_ttl == 0) || (time() - $this->core->blog->settings->system->trackbacks_ttl * 86400 < $dt)) {
                return true;
            }
        }

        return false;
    }

    # Show comments or trackbacks
    protected function showComments($rs, $has_action, $tb = false, $show_ip = true)
    {
        echo
        '<div class="table-outer">' .
        '<table class="comments-list"><tr>' .
        '<th colspan="2" class="first">' . __('Author') . '</th>' .
        '<th>' . __('Date') . '</th>' .
        ($show_ip ? '<th class="nowrap">' . __('IP address') . '</th>' : '') .
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
            $comment_url = $this->core->adminurl->get('admin.comment', ['id' => $rs->comment_id]);

            $img        = '<img alt="%1$s" title="%1$s" src="?df=images/%2$s" />';
            $img_status = '';
            $sts_class  = '';
            switch ($rs->comment_status) {
                case 1:
                    $img_status = sprintf($img, __('Published'), 'check-on.png');
                    $sts_class  = 'sts-online';

                    break;
                case 0:
                    $img_status = sprintf($img, __('Unpublished'), 'check-off.png');
                    $sts_class  = 'sts-offline';

                    break;
                case -1:
                    $img_status = sprintf($img, __('Pending'), 'check-wrn.png');
                    $sts_class  = 'sts-pending';

                    break;
                case -2:
                    $img_status = sprintf($img, __('Junk'), 'junk.png');
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
                    'extra_html' => 'title="' . ($tb ? __('select this trackback') : __('select this comment') . '"'),
                ]
            ) : '') . '</td>' .
            '<td class="maximal">' . Html::escapeHTML($rs->comment_author) . '</td>' .
            '<td class="nowrap">' . Dt::dt2str(__('%Y-%m-%d %H:%M'), $rs->comment_dt) . '</td>' .
            ($show_ip ?
                '<td class="nowrap"><a href="' . $this->core->adminurl->get('admin.comments', ['ip' => $rs->comment_ip]) . '">' . $rs->comment_ip . '</a></td>' : '') .
            '<td class="nowrap status">' . $img_status . '</td>' .
            '<td class="nowrap status"><a href="' . $comment_url . '">' .
            '<img src="?df=images/edit-mini.png" alt="" title="' . __('Edit this comment') . '" /> ' . __('Edit') . '</a></td>' .

                '</tr>';
        }

        echo '</table></div>';
    }
}
