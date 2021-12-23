<?php
/**
 * @class Dotclear\Admin\Page\Blog
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
use Dotclear\Core\Settings;

use Dotclear\Admin\Page;
use Dotclear\Admin\Page\BlogPref;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Blog extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->checkSuper();

        $blog_id   = '';
        $blog_url  = '';
        $blog_name = '';
        $blog_desc = '';

        # Create a blog
        if (!isset($_POST['id']) && (isset($_POST['create']))) {
            $cur       = $this->core->con->openCursor($this->core->prefix . 'blog');
            $blog_id   = $cur->blog_id   = $_POST['blog_id'];
            $blog_url  = $cur->blog_url  = $_POST['blog_url'];
            $blog_name = $cur->blog_name = $_POST['blog_name'];
            $blog_desc = $cur->blog_desc = $_POST['blog_desc'];

            try {
                # --BEHAVIOR-- adminBeforeBlogCreate
                $this->core->callBehavior('adminBeforeBlogCreate', $cur, $blog_id);

                $this->core->addBlog($cur);

                # Default settings and override some
                $blog_settings = new Settings($this->core, $cur->blog_id);
                $blog_settings->addNamespace('system');
                $blog_settings->system->put('lang', $this->core->auth->getInfo('user_lang'));
                $blog_settings->system->put('blog_timezone', $this->core->auth->getInfo('user_tz'));

                if (substr($blog_url, -1) == '?') {
                    $blog_settings->system->put('url_scan', 'query_string');
                } else {
                    $blog_settings->system->put('url_scan', 'path_info');
                }

                # --BEHAVIOR-- adminAfterBlogCreate
                $core->callBehavior('adminAfterBlogCreate', $cur, $blog_id, $blog_settings);

                static::addSuccessNotice(sprintf(__('Blog "%s" successfully created'), html::escapeHTML($cur->blog_name)));
                $this->core->adminurl->redirect('admin.blog', ['id' => $cur->blog_id, 'edit_blog_mode' => 1]);
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        if (!empty($_REQUEST['id'])) {
            define('EDIT_BLOG_MODE', true);
            new BlogPref($core);
            return;
        } else {
            $this->open(__('New blog'), static::jsConfirmClose('blog-form'),
                $this->breadcrumb(
                    [
                        __('System')   => '',
                        __('Blogs')    => $this->core->adminurl->get('admin.blogs'),
                        __('New blog') => ''
                    ])
            );

            echo
            '<form action="' . $this->core->adminurl->get('admin.blog') . '" method="post" id="blog-form">' .

            '<div>' . $this->core->formNonce() . '</div>' .
            '<p><label class="required" for="blog_id"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog ID:') . '</label> ' .
            Form::field('blog_id', 30, 32,
                [
                    'default'    => Html::escapeHTML($blog_id),
                    'extra_html' => 'required placeholder="' . __('Blog ID') . '"'
                ]
            ) . '</p>' .
            '<p class="form-note">' . __('At least 2 characters using letters, numbers or symbols.') . '</p> ';

            echo
            '<p><label class="required" for="blog_name"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog name:') . '</label> ' .
            Form::field('blog_name', 30, 255,
                [
                    'default'    => Html::escapeHTML($blog_name),
                    'extra_html' => 'required placeholder="' . __('Blog name') . '" lang="' . $this->core->auth->getInfo('user_lang') . '" ' .
                        'spellcheck="true"'
                ]
            ) . '</p>' .

            '<p><label class="required" for="blog_url"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog URL:') . '</label> ' .
            Form::url('blog_url',
                [
                    'size'       => 30,
                    'default'    => Html::escapeHTML($blog_url),
                    'extra_html' => 'required placeholder="' . __('Blog URL') . '"'
                ]
            ) . '</p>' .

            '<p class="area"><label for="blog_desc">' . __('Blog description:') . '</label> ' .
            Form::textarea('blog_desc', 60, 5,
                [
                    'default'    => Html::escapeHTML($blog_desc),
                    'extra_html' => 'lang="' . $core->auth->getInfo('user_lang') . '" spellcheck="true"'
                ]) . '</p>' .

            '<p><input type="submit" accesskey="s" name="create" value="' . __('Create') . '" />' .
            ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';

            $this->helpBlock('core_blog_new');
            $this->close();
        }
    }
}
