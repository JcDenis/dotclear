<?php
/**
 * @class Dotclear\Admin\Page\Post
 * @brief Dotclear admin post page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use ArrayObject;

use Dotclear\Exception\AdminException;

use Dotclear\Core\Media;
use Dotclear\Core\Trackback;

use Dotclear\Admin\Page;
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
    private $post_id            = null;
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
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ? bool
    {
        $page_title = __('New post');

        $this->post_format        = dotclear()->auth->getOption('post_format');
        $this->post_editor        = dotclear()->auth->getOption('editor');
        $this->post_lang          = dotclear()->auth->getInfo('user_lang');
        $this->post_status        = dotclear()->auth->getInfo('user_post_status');
        $this->post_open_comment  = dotclear()->blog->settings->system->allow_comments;
        $this->post_open_tb       = dotclear()->blog->settings->system->allow_trackbacks;

        $this->can_view_ip   = dotclear()->auth->check('contentadmin', dotclear()->blog->id);
        $this->can_edit_post = dotclear()->auth->check('usage,contentadmin', dotclear()->blog->id);
        $this->can_publish   = dotclear()->auth->check('publish,contentadmin', dotclear()->blog->id);

        $this->post_headlink = '<link rel="%s" title="%s" href="' . dotclear()->adminurl->get('admin.post', ['id' => '%s'], '&amp;', true) . '" />';
        $this->post_link     = '<a href="' . dotclear()->adminurl->get('admin.post', ['id' => '%s'], '&amp;', true) . '" title="%s">%s</a>';
        $next_headlink     = $prev_headlink     = null;

        # If user can't publish
        if (!$this->can_publish) {
            $this->post_status = -2;
        }

        $this->img_status_pattern = '<img class="img_select_option" alt="%1$s" title="%1$s" src="?df=images/%2$s" />';

        $this->trackback      = new Trackback();

        # Get entry informations

        if (!empty($_REQUEST['id'])) {
            $page_title = __('Edit post');

            $params['post_id'] = (int) $_REQUEST['id'];

            $this->post = dotclear()->blog->getPosts($params);

            if ($this->post->isEmpty()) {
                dotclear()->error(__('This entry does not exist.'));
                $this->can_view_page = false;
            } else {
                $this->post_id            = (int) $this->post->post_id;
                $this->cat_id             = $this->post->cat_id;
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
                $this->post_selected      = (bool) $this->post->post_selected;
                $this->post_open_comment  = (bool) $this->post->post_open_comment;
                $this->post_open_tb       = (bool) $this->post->post_open_tb;

                $this->can_edit_post = $this->post->isEditable();
                $this->can_delete    = $this->post->isDeletable();

                $next_rs = dotclear()->blog->getNextPost($this->post, 1);
                $prev_rs = dotclear()->blog->getNextPost($this->post, -1);

                if ($next_rs !== null) {
                    $this->next_link = sprintf(
                        $this->post_link,
                        $next_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        __('Next entry') . '&nbsp;&#187;'
                    );
                    $next_headlink = sprintf(
                        $this->post_headlink,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        $next_rs->post_id
                    );
                }

                if ($prev_rs !== null) {
                    $this->prev_link = sprintf(
                        $this->post_link,
                        $prev_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        '&#171;&nbsp;' . __('Previous entry')
                    );
                    $prev_headlink = sprintf(
                        $this->post_headlink,
                        'previous',
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        $prev_rs->post_id
                    );
                }

                try {
                    dotclear()->mediaInstance();
                } catch (\Exception $e) {
                    dotclear()->error($e->getMessage());
                }

                # Sanitize trackbacks excerpt
                $this->tb_excerpt = empty($_POST['tb_excerpt']) ?
                $this->post_excerpt_xhtml . ' ' . $this->post_content_xhtml :
                $_POST['tb_excerpt'];
                $this->tb_excerpt = Html::decodeEntities(Html::clean($this->tb_excerpt));
                $this->tb_excerpt = Text::cutString(Html::escapeHTML($this->tb_excerpt), 255);
                $this->tb_excerpt = preg_replace('/\s+/ms', ' ', $this->tb_excerpt);
            }
        }
        if (isset($_REQUEST['section']) && $_REQUEST['section'] == 'trackbacks') {
            $anchor = 'trackbacks';
        } else {
            $anchor = 'comments';
        }

        $this->comments_actions = new CommentAction(dotclear()->adminurl->get('admin.post'), ['id' => $this->post_id, '_ANCHOR' => $anchor, 'section' => $anchor]);
        if ($this->comments_actions->pageProcess()) {
            return null;
        }

        # Ping blogs
        if (!empty($_POST['ping'])) {
            if (!empty($_POST['tb_urls']) && $this->post_id && $this->post_status == 1 && $this->can_edit_post) {
                $this->tb_urls       = $_POST['tb_urls'];
                $this->tb_urls       = str_replace("\r", '', $this->tb_urls);
                $tb_post_title = Html::escapeHTML(trim(Html::clean($this->post_title)));
                $tb_post_url   = $this->post->getURL();

                foreach (explode("\n", $this->tb_urls) as $tb_url) {
                    try {
                        # --BEHAVIOR-- adminBeforePingTrackback
                        dotclear()->behaviors->call('adminBeforePingTrackback', $tb_url, $this->post_id, $tb_post_title, $this->tb_excerpt, $tb_post_url);

                        $this->trackback->ping($tb_url, $this->post_id, $tb_post_title, $this->tb_excerpt, $tb_post_url);
                    } catch (\Exception $e) {
                        dotclear()->error($e->getMessage());
                    }
                }

                if (!dotclear()->error()->flag()) {
                    dotclear()->notices->addSuccessNotice(__('All pings sent.'));
                    dotclear()->adminurl->redirect(
                        'admin.post',
                        ['id' => $this->post_id, 'tb' => '1']
                    );
                }
            }
        }

        # Format excerpt and content
        elseif (!empty($_POST) && $this->can_edit_post) {
            $this->post_format  = $_POST['post_format'];
            $this->post_excerpt = $_POST['post_excerpt'];
            $this->post_content = $_POST['post_content'];

            $this->post_title = $_POST['post_title'];

            $this->cat_id = (int) $_POST['cat_id'];

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
                    dotclear()->error($e->getMessage());
                }
            }

            $this->post_open_comment = !empty($_POST['post_open_comment']);
            $this->post_open_tb      = !empty($_POST['post_open_tb']);
            $this->post_selected     = !empty($_POST['post_selected']);
            $this->post_lang         = $_POST['post_lang'];
            $this->post_password     = !empty($_POST['post_password']) ? $_POST['post_password'] : null;

            $this->post_notes = $_POST['post_notes'];

            if (isset($_POST['post_url'])) {
                $this->post_url = $_POST['post_url'];
            }

            dotclear()->blog->setPostContent(
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
                dotclear()->behaviors->call('adminBeforePostDelete', $this->post_id);
                dotclear()->blog->delPost($this->post_id);
                dotclear()->adminurl->redirect('admin.posts');
            } catch (\Exception $e) {
                dotclear()->error($e->getMessage());
            }
        }

        # Create or update post
        if (!empty($_POST) && !empty($_POST['save']) && $this->can_edit_post && !$this->bad_dt) {
            # Create category
            if (!empty($_POST['new_cat_title']) && dotclear()->auth->check('categories', dotclear()->blog->id)) {
                $cur_cat            = dotclear()->con->openCursor(dotclear()->prefix . 'category');
                $cur_cat->cat_title = $_POST['new_cat_title'];
                $cur_cat->cat_url   = '';

                $parent_cat = !empty($_POST['new_cat_parent']) ? $_POST['new_cat_parent'] : '';

                # --BEHAVIOR-- adminBeforeCategoryCreate
                dotclear()->behaviors->call('adminBeforeCategoryCreate', $cur_cat);

                $this->cat_id = dotclear()->blog->addCategory($cur_cat, (int) $parent_cat);

                # --BEHAVIOR-- adminAfterCategoryCreate
                dotclear()->behaviors->call('adminAfterCategoryCreate', $cur_cat, $this->cat_id);
            }

            $cur = dotclear()->con->openCursor(dotclear()->prefix . 'post');

            $cur->cat_id             = ($this->cat_id ?: null);
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
            $cur->post_selected      = (int) $this->post_selected;
            $cur->post_open_comment  = (int) $this->post_open_comment;
            $cur->post_open_tb       = (int) $this->post_open_tb;

            if (isset($_POST['post_url'])) {
                $cur->post_url = $this->post_url;
            }

            # Update post
            if ($this->post_id) {
                try {
                    # --BEHAVIOR-- adminBeforePostUpdate
                    dotclear()->behaviors->call('adminBeforePostUpdate', $cur, $this->post_id);

                    dotclear()->blog->updPost($this->post_id, $cur);

                    # --BEHAVIOR-- adminAfterPostUpdate
                    dotclear()->behaviors->call('adminAfterPostUpdate', $cur, $this->post_id);
                    dotclear()->notices->addSuccessNotice(sprintf(__('The post "%s" has been successfully updated'), Html::escapeHTML(trim(Html::clean($cur->post_title)))));
                    dotclear()->adminurl->redirect(
                        'admin.post',
                        ['id' => $this->post_id]
                    );
                } catch (\Exception $e) {
                    dotclear()->error($e->getMessage());
                }
            } else {
                $cur->user_id = dotclear()->auth->userID();

                try {
                    # --BEHAVIOR-- adminBeforePostCreate
                    dotclear()->behaviors->call('adminBeforePostCreate', $cur);

                    $return_id = dotclear()->blog->addPost($cur);

                    # --BEHAVIOR-- adminAfterPostCreate
                    dotclear()->behaviors->call('adminAfterPostCreate', $cur, $return_id);

                    dotclear()->notices->addSuccessNotice(__('Entry has been successfully created.'));
                    dotclear()->adminurl->redirect(
                        'admin.post',
                        ['id' => $return_id]
                    );
                } catch (\Exception $e) {
                    dotclear()->error($e->getMessage());
                }
            }
        }

        # Page setup
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
            switch ($this->post_status) {
                case 1:
                    $this->img_status = sprintf($this->img_status_pattern, __('Published'), 'check-on.png');

                    break;
                case 0:
                    $this->img_status = sprintf($this->img_status_pattern, __('Unpublished'), 'check-off.png');

                    break;
                case -1:
                    $this->img_status = sprintf($this->img_status_pattern, __('Scheduled'), 'scheduled.png');

                    break;
                case -2:
                    $this->img_status = sprintf($this->img_status_pattern, __('Pending'), 'check-wrn.png');

                    break;
                default:
                    $this->img_status = '';
            }
            $edit_entry_str  = __('&ldquo;%s&rdquo;');
            $page_title_edit = sprintf($edit_entry_str, Html::escapeHTML(trim(Html::clean($this->post_title)))) . ' ' . $this->img_status;
        } else {
            $page_title_edit = '';
        }

        $this->setPageHead(
            static::jsModal() .
            static::jsMetaEditor()
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
                $this->setPageHead(dotclear()->behaviors->call(
                    'adminPostEditor',
                    $p_edit,
                    'post',
                    ['#post_excerpt', '#post_content', '#comment_content'],
                    $this->post_format
                ));
            } else {
                $this->setPageHead(dotclear()->behaviors->call(
                    'adminPostEditor',
                    $p_edit,
                    'post',
                    ['#post_excerpt', '#post_content'],
                    $this->post_format
                ));
                $this->setPageHead(dotclear()->behaviors->call(
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
                static::jsLoad('js/_post.js') .
                static::jsConfirmClose('entry-form', 'comment-form') .
                # --BEHAVIOR-- adminPostHeaders
                dotclear()->behaviors->call('adminPostHeaders') .
                static::jsPageTabs($default_tab) .
                $next_headlink . "\n" . $prev_headlink
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog->name)         => '',
                __('Posts')                                        => dotclear()->adminurl->get('admin.posts'),
                ($this->post_id ? $page_title_edit : $page_title) => '',
            ], [
                'x-frame-allow' => dotclear()->blog->url,
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        $categories_combo = dotclear()->combos->getCategoriesCombo(
            dotclear()->blog->getCategories()
        );

        $status_combo = dotclear()->combos->getPostStatusesCombo();

        $rs         = dotclear()->blog->getLangs(['order' => 'asc']);
        $lang_combo = dotclear()->combos->getLangsCombo($rs, true);

        $core_formaters    = dotclear()->getFormaters();
        $available_formats = ['' => ''];
        foreach ($core_formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $available_formats[$format] = $format;
            }
        }

        if (!empty($_GET['upd'])) {
            dotclear()->notices->success(__('Entry has been successfully updated.'));
        } elseif (!empty($_GET['crea'])) {
            dotclear()->notices->success(__('Entry has been successfully created.'));
        } elseif (!empty($_GET['attached'])) {
            dotclear()->notices->success(__('File has been successfully attached.'));
        } elseif (!empty($_GET['rmattach'])) {
            dotclear()->notices->success(__('Attachment has been successfully removed.'));
        }

        if (!empty($_GET['creaco'])) {
            dotclear()->notices->success(__('Comment has been successfully created.'));
        }
        if (!empty($_GET['tbsent'])) {
            dotclear()->notices->success(__('All pings sent.'));
        }

        # XHTML conversion
        if (!empty($_GET['xconv'])) {
            $this->post_excerpt = $this->post_excerpt_xhtml;
            $this->post_content = $this->post_content_xhtml;
            $this->post_format  = 'xhtml';

            dotclear()->notices->message(__('Don\'t forget to validate your XHTML conversion by saving your post.'));
        }

        if ($this->post_id && $this->post->post_status == 1) {
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

            # --BEHAVIOR-- adminPostNavLinks
            dotclear()->behaviors->call('adminPostNavLinks', $this->post ?? null, 'post');

            echo '</p>';
        }

        # Exit if we cannot view page
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
                            'default' => Html::escapeHTML(Dt::str('%Y-%m-%d\T%H:%M', strtotime($this->post_dt))),
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
                        '<a id="convert-xhtml" class="button' . ($this->post_id && $this->post_format != 'wiki' ? ' hide' : '') . '" href="' .
                        dotclear()->adminurl->get('admin.post', ['id' => $this->post_id, 'xconv' => '1']) .
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
                        (dotclear()->auth->check('categories', dotclear()->blog->id) ?
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
                        (dotclear()->blog->settings->system->allow_comments ?
                            ($this->isContributionAllowed($this->post_id, strtotime($this->post_dt), true) ?
                                '' :
                                '<p class="form-note warn">' .
                                __('Warning: Comments are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' .
                            __('Comments are not accepted on this blog so far.') . '</p>') .
                        '<p><label for="post_open_tb" class="classic">' .
                        Form::checkbox('post_open_tb', 1, $this->post_open_tb) . ' ' .
                        __('Accept trackbacks') . '</label></p>' .
                        (dotclear()->blog->settings->system->allow_trackbacks ?
                            ($this->isContributionAllowed($this->post_id, strtotime($this->post_dt), false) ?
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
                        (int) dotclear()->auth->getOption('edit_size'),
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
            dotclear()->behaviors->call('adminPostFormItems', $main_items, $sidebar_items, $this->post ?? null, 'post');

            echo '<div class="multi-part" title="' . ($this->post_id ? __('Edit post') : __('New post')) .
            sprintf(' &rsaquo; %s', $this->post_format) . '" id="edit-entry">';
            echo '<form action="' . dotclear()->adminurl->get('admin.post') . '" method="post" id="entry-form">';
            echo '<div id="entry-wrapper">';
            echo '<div id="entry-content"><div class="constrained">';

            echo '<h3 class="out-of-screen-if-js">' . __('Edit post') . '</h3>';

            foreach ($main_items as $id => $item) {
                echo $item;
            }

            # --BEHAVIOR-- adminPostForm (may be deprecated)
            dotclear()->behaviors->call('adminPostForm', $this->post ?? null, 'post');

            echo
            '<p class="border-top">' .
            ($this->post_id ? Form::hidden('id', $this->post_id) : '') .
            '<input type="submit" value="' . __('Save') . ' (s)" ' .
                'accesskey="s" name="save" /> ';
            if ($this->post_id) {
                $preview_url = dotclear()->blog->url . dotclear()->url->getURLFor('preview', dotclear()->auth->userID() . '/' .
                    Http::browserUID(dotclear()->config()->master_key . dotclear()->auth->userID() . dotclear()->auth->cryptLegacy(dotclear()->auth->userID())) .
                    '/' . $this->post->post_url);

                dotclear()->auth->user_prefs->addWorkspace('interface');
                $blank_preview = dotclear()->auth->user_prefs->interface->blank_preview;

                $preview_class  = $blank_preview ? '' : ' modal';
                $preview_target = $blank_preview ? '' : ' target="_blank"';

                echo '<a id="post-preview" href="' . $preview_url . '" class="button' . $preview_class . '" accesskey="p"' . $preview_target . '>' . __('Preview') . ' (p)' . '</a>';
                echo ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';
            } else {
                echo
                '<a id="post-cancel" href="' . dotclear()->adminurl->get('admin.home') . '" class="button" accesskey="c">' . __('Cancel') . ' (c)</a>';
            }

            echo($this->can_delete ? ' <input type="submit" class="delete" value="' . __('Delete') . '" name="delete" />' : '') .
            dotclear()->formNonce() .
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
            dotclear()->behaviors->call('adminPostFormSidebar', $this->post ?? null, 'post');
            echo '</div>'; // End #entry-sidebar

            echo '</form>';

            # --BEHAVIOR-- adminPostForm
            dotclear()->behaviors->call('adminPostAfterForm', $this->post ?? null, 'post');

            echo '</div>';
        }

        if ($this->post_id) {
            /* Comments
            -------------------------------------------------------- */

            $params = ['post_id' => $this->post_id, 'order' => 'comment_dt ASC'];

            $comments = dotclear()->blog->getComments(array_merge($params, ['comment_trackback' => 0]));

            echo
            '<div id="comments" class="clear multi-part" title="' . __('Comments') . '">';
            $combo_action = $this->comments_actions->getCombo();
            $has_action   = !empty($combo_action) && !$comments->isEmpty();
            echo
            '<p class="top-add"><a class="button add" href="#comment-form">' . __('Add a comment') . '</a></p>';

            if ($has_action) {
                echo '<form action="' . dotclear()->adminurl->get('admin.post') . '" id="form-comments" method="post">';
            }

            echo '<h3>' . __('Comments') . '</h3>';
            if (!$comments->isEmpty()) {
                $this->showComments($comments, $has_action, false);
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
                Form::hidden(['id'], $this->post_id) .
                dotclear()->formNonce() .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                    '</div>' .
                    '</form>';
            }
            /* Add a comment
            -------------------------------------------------------- */

            echo
            '<div class="fieldset clear">' .
            '<h3>' . __('Add a comment') . '</h3>' .

            '<form action="' . dotclear()->adminurl->get('admin.comment') . '" method="post" id="comment-form">' .
            '<div class="constrained">' .
            '<p><label for="comment_author" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Name:') . '</label>' .
            Form::field('comment_author', 30, 255, [
                'default'    => Html::escapeHTML(dotclear()->auth->getInfo('user_cn')),
                'extra_html' => 'required placeholder="' . __('Author') . '"',
            ]) .
            '</p>' .

            '<p><label for="comment_email">' . __('Email:') . '</label>' .
            Form::email('comment_email', 30, 255, Html::escapeHTML(dotclear()->auth->getInfo('user_email'))) .
            '</p>' .

            '<p><label for="comment_site">' . __('Web site:') . '</label>' .
            Form::url('comment_site', 30, 255, Html::escapeHTML(dotclear()->auth->getInfo('user_url'))) .
            '</p>' .

            '<p class="area"><label for="comment_content" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' .
            __('Comment:') . '</label> ' .
            Form::textarea(
                'comment_content',
                50,
                8,
                [
                    'extra_html' => 'required placeholder="' . __('Comment') . '" lang="' . dotclear()->auth->getInfo('user_lang') .
                        '" spellcheck="true"',
                ]
            ) .
            '</p>' .

            '<p>' .
            Form::hidden('post_id', $this->post_id) .
            dotclear()->formNonce() .
            '<input type="submit" name="add" value="' . __('Save') . '" /></p>' .
            '</div>' . #constrained

            '</form>' .
            '</div>' . #add comment
            '</div>'; #comments
        }

        if ($this->post_id && $this->post_status == 1) {
            /* Trackbacks
            -------------------------------------------------------- */

            $params     = ['post_id' => $this->post_id, 'order' => 'comment_dt ASC'];
            $this->trackbacks = dotclear()->blog->getComments(array_merge($params, ['comment_trackback' => 1]));

            # Actions combo box
            $combo_action = $this->comments_actions->getCombo();
            $has_action   = !empty($combo_action) && !$this->trackbacks->isEmpty();

            if (!empty($_GET['tb_auto'])) {
                $this->tb_urls = implode("\n", $this->trackback->discover($this->post_excerpt_xhtml . ' ' . $this->post_content_xhtml));
            }

            # Display tab
            echo
            '<div id="trackbacks" class="clear multi-part" title="' . __('Trackbacks') . '">';

            # tracbacks actions
            if ($has_action) {
                echo '<form action="' . dotclear()->adminurl->get('admin.post') . '" id="form-trackbacks" method="post">';
            }

            echo '<h3>' . __('Trackbacks received') . '</h3>';

            if (!$this->trackbacks->isEmpty()) {
                $this->showComments($this->trackbacks, $has_action, true);
            } else {
                echo '<p>' . __('No trackback') . '</p>';
            }

            if ($has_action) {
                echo
                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected trackbacks action:') . '</label> ' .
                Form::combo('action', $combo_action) .
                Form::hidden('id', $this->post_id) .
                Form::hidden(['section'], 'trackbacks') .
                dotclear()->formNonce() .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                    '</div>' .
                    '</form>';
            }

            /* Add trackbacks
            -------------------------------------------------------- */
            if ($this->can_edit_post && $this->post->post_status) {
                echo
                    '<div class="fieldset clear">';

                echo
                '<h3>' . __('Ping blogs') . '</h3>' .
                '<form action="' . dotclear()->adminurl->get('admin.post', ['id' => $this->post_id]) . '" id="trackback-form" method="post">' .
                '<p><label for="tb_urls" class="area">' . __('URLs to ping:') . '</label>' .
                Form::textarea('tb_urls', 60, 5, $this->tb_urls) .
                '</p>' .

                '<p><label for="tb_excerpt" class="area">' . __('Excerpt to send:') . '</label>' .
                Form::textarea('tb_excerpt', 60, 5, $this->tb_excerpt) . '</p>' .

                '<p>' .
                dotclear()->formNonce() .
                '<input type="submit" name="ping" value="' . __('Ping blogs') . '" />' .
                    (empty($_GET['tb_auto']) ?
                    '&nbsp;&nbsp;<a class="button" href="' .
                    dotclear()->adminurl->get('admin.post', ['id' => $this->post_id, 'tb_auto' => 1, 'tb' => 1]) .
                    '">' . __('Auto discover ping URLs') . '</a>'
                    : '') .
                    '</p>' .
                    '</form>';

                $pings = $this->trackback->getPostPings($this->post_id);

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
    }

    # Controls comments or trakbacks capabilities
     protected function isContributionAllowed($id, $dt, $com = true)
     {
        if (!$id) {
            return true;
        }
        if ($com) {
            if ((dotclear()->blog->settings->system->comments_ttl == 0) || (time() - dotclear()->blog->settings->system->comments_ttl * 86400 < $dt)) {
                return true;
            }
        } else {
            if ((dotclear()->blog->settings->system->trackbacks_ttl == 0) || (time() - dotclear()->blog->settings->system->trackbacks_ttl * 86400 < $dt)) {
                return true;
            }
        }

        return false;
    }

    # Show comments or trackbacks
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
            $comment_url = dotclear()->adminurl->get('admin.comment', ['id' => $rs->comment_id]);

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
                    'extra_html' => 'title="' . ($tb ? __('select this trackback') : __('select this comment') . '"'),
                ]
            ) : '') . '</td>' .
            '<td class="maximal">' . Html::escapeHTML($rs->comment_author) . '</td>' .
            '<td class="nowrap">' . Dt::dt2str(__('%Y-%m-%d %H:%M'), $rs->comment_dt) . '</td>' .
            ($this->can_view_ip ?
                '<td class="nowrap"><a href="' . dotclear()->adminurl->get('admin.comments', ['ip' => $rs->comment_ip]) . '">' . $rs->comment_ip . '</a></td>' : '') .
            '<td class="nowrap status">' . $this->img_status . '</td>' .
            '<td class="nowrap status"><a href="' . $comment_url . '">' .
            '<img src="?df=images/edit-mini.png" alt="" title="' . __('Edit this comment') . '" /> ' . __('Edit') . '</a></td>' .

                '</tr>';
        }

        echo '</table></div>';
    }
}
