<?php
/**
 * @class Dotclear\Process\Admin\Handler\Blog
 * @brief Dotclear admin blog page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Process\Admin\Page\Page;
use Dotclear\Process\Admin\Handler\BlogPref;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;

class Blog extends Page
{
    private $blog_id   = '';
    private $blog_name = '';
    private $blog_desc = '';
    private $blog_url  = '';

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        # If there's a blog id, process blog pref
        if (!empty($_REQUEST['id'])) {
            $blog_pref = new BlogPref($this->handler, false);
            $blog_pref->pageProcess();

            return null;
        }

        # Page setup
        $this
            ->setPageHelp('core_blog_new')
            ->setPageTitle(__('New blog'))
            ->setPageHead(
                dotclear()->resource()->confirmClose('blog-form')
            )
            ->setPageBreadcrumb([
                __('System')   => '',
                __('Blogs')    => dotclear()->adminurl()->get('admin.blogs'),
                __('New blog') => ''
            ])
        ;

        # Create a blog
        if (!isset($_POST['id']) && isset($_POST['create'])) {
            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'blog');
            $cur->setField('blog_id', $this->blog_id = $_POST['blog_id']);
            $cur->setField('blog_url', $this->blog_url = $_POST['blog_url']);
            $cur->setField('blog_name', $this->blog_name = $_POST['blog_name']);
            $cur->setField('blog_desc', $this->blog_desc = $_POST['blog_desc']);

            try {
                # --BEHAVIOR-- adminBeforeBlogCreate
                dotclear()->behavior()->call('adminBeforeBlogCreate', $cur, $this->blog_id);

                dotclear()->blogs()->addBlog($cur);

                # Default settings and override some
                $blog_settings = new Settings($cur->getField('blog_id'));
                $blog_settings->get('system')->put('lang', dotclear()->user()->getInfo('user_lang'));
                $blog_settings->get('system')->put('blog_timezone', dotclear()->user()->getInfo('user_tz'));

                if ('?' == substr($this->blog_url, -1)) {
                    $blog_settings->get('system')->put('url_scan', 'query_string');
                } else {
                    $blog_settings->get('system')->put('url_scan', 'path_info');
                }

                # --BEHAVIOR-- adminAfterBlogCreate
                dotclear()->behavior()->call('adminAfterBlogCreate', $cur, $this->blog_id, $blog_settings);

                dotclear()->notice()->addSuccessNotice(sprintf(__('Blog "%s" successfully created'), Html::escapeHTML($cur->getField('blog_name'))));
                dotclear()->adminurl()->redirect('admin.blog', ['id' => $cur->getField('blog_id'), 'edit_blog_mode' => 1]);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        return true;
    }

    protected function getPageContent(): void
    {
        echo
        '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="blog-form">' .

        '<p><label class="required" for="blog_id"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog ID:') . '</label> ' .
        Form::field('blog_id', 30, 32,
            [
                'default'    => Html::escapeHTML($this->blog_id),
                'extra_html' => 'required placeholder="' . __('Blog ID') . '"'
            ]
        ) . '</p>' .
        '<p class="form-note">' . __('At least 2 characters using letters, numbers or symbols.') . '</p> ';

        echo
        '<p><label class="required" for="blog_name"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog name:') . '</label> ' .
        Form::field('blog_name', 30, 255,
            [
                'default'    => Html::escapeHTML($this->blog_name),
                'extra_html' => 'required placeholder="' . __('Blog name') . '" lang="' . dotclear()->user()->getInfo('user_lang') . '" ' .
                    'spellcheck="true"'
            ]
        ) . '</p>' .

        '<p><label class="required" for="blog_url"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog URL:') . '</label> ' .
        Form::url('blog_url',
            [
                'size'       => 30,
                'default'    => Html::escapeHTML($this->blog_url),
                'extra_html' => 'required placeholder="' . __('Blog URL') . '"'
            ]
        ) . '</p>' .

        '<p class="area"><label for="blog_desc">' . __('Blog description:') . '</label> ' .
        Form::textarea('blog_desc', 60, 5,
            [
                'default'    => Html::escapeHTML($this->blog_desc),
                'extra_html' => 'lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"'
            ]) . '</p>' .

        '<p><input type="submit" accesskey="s" name="create" value="' . __('Create') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        dotclear()->adminurl()->getHiddenFormFields('admin.blog', [], true) .
        '</p>' .
        '</form>';
    }
}
