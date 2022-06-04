<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Blog
use Dotclear\App;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin blog page.
 *
 * @ingroup  Admin Blog Handler
 */
class Blog extends AbstractPage
{
    private $blog_id   = '';
    private $blog_name = '';
    private $blog_desc = '';
    private $blog_url  = '';

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        // If there's a blog id, process blog pref
        if (!GPC::request()->empty('id')) {
            $blog_pref = new BlogPref($this->handler, false);
            $blog_pref->pageProcess();

            return null;
        }

        // Page setup
        $this
            ->setPageHelp('core_blog_new')
            ->setPageTitle(__('New blog'))
            ->setPageHead(
                App::core()->resource()->confirmClose('blog-form')
            )
            ->setPageBreadcrumb([
                __('System')   => '',
                __('Blogs')    => App::core()->adminurl()->get('admin.blogs'),
                __('New blog') => '',
            ])
        ;

        // Create a blog
        if (!GPC::post()->isset('id') && !GPC::post()->empty('create')) {
            $cur                                         = App::core()->con()->openCursor(App::core()->prefix() . 'blog');
            $cur->setField('blog_id', $this->blog_id     = GPC::post()->string('blog_id'));
            $cur->setField('blog_url', $this->blog_url   = GPC::post()->string('blog_url'));
            $cur->setField('blog_name', $this->blog_name = GPC::post()->string('blog_name'));
            $cur->setField('blog_desc', $this->blog_desc = GPC::post()->string('blog_desc'));

            try {
                App::core()->blogs()->createBlog(cursor: $cur);

                // Default settings and override some
                $settings = new Settings(blog: $cur->getField('blog_id'));
                $system   = $settings->getGroup('system');
                $system->putSetting('lang', App::core()->user()->getInfo('user_lang'));
                $system->putSetting('blog_timezone', App::core()->user()->getInfo('user_tz'));
                $system->putSetting('url_scan', '?' == substr($this->blog_url, -1) ? 'query_string' : 'path_info');

                App::core()->notice()->addSuccessNotice(sprintf(__('Blog "%s" successfully created'), Html::escapeHTML($cur->getField('blog_name'))));
                App::core()->adminurl()->redirect('admin.blog', ['id' => $cur->getField('blog_id'), 'edit_blog_mode' => 1]);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        return true;
    }

    protected function getPageContent(): void
    {
        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="blog-form">' .

        '<p><label class="required" for="blog_id"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog ID:') . '</label> ' .
        Form::field(
            'blog_id',
            30,
            32,
            [
                'default'    => Html::escapeHTML($this->blog_id),
                'extra_html' => 'required placeholder="' . __('Blog ID') . '"',
            ]
        ) . '</p>' .
        '<p class="form-note">' . __('At least 2 characters using letters, numbers or symbols.') . '</p> ';

        echo '<p><label class="required" for="blog_name"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog name:') . '</label> ' .
        Form::field(
            'blog_name',
            30,
            255,
            [
                'default'    => Html::escapeHTML($this->blog_name),
                'extra_html' => 'required placeholder="' . __('Blog name') . '" lang="' . App::core()->user()->getInfo('user_lang') . '" ' .
                    'spellcheck="true"',
            ]
        ) . '</p>' .

        '<p><label class="required" for="blog_url"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog URL:') . '</label> ' .
        Form::url(
            'blog_url',
            [
                'size'       => 30,
                'default'    => Html::escapeHTML($this->blog_url),
                'extra_html' => 'required placeholder="' . __('Blog URL') . '"',
            ]
        ) . '</p>' .

        '<p class="area"><label for="blog_desc">' . __('Blog description:') . '</label> ' .
        Form::textarea(
            'blog_desc',
            60,
            5,
            [
                'default'    => Html::escapeHTML($this->blog_desc),
                'extra_html' => 'lang="' . App::core()->user()->getInfo('user_lang') . '" spellcheck="true"',
            ]
        ) . '</p>' .

        '<p><input type="submit" accesskey="s" name="create" value="' . __('Create') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        App::core()->adminurl()->getHiddenFormFields('admin.blog', [], true) .
        '</p>' .
        '</form>';
    }
}
