<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\BlogDel
use Dotclear\App;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Admin blog deletion page.
 *
 * @ingroup  Admin Blog Handler
 */
class BlogDel extends AbstractPage
{
    private $blog_id   = '';
    private $blog_name = '';

    protected function getPermissions(): string|bool
    {
        return '';
    }

    protected function getPagePrepend(): ?bool
    {
        // search the blog
        $rs = null;
        if (!empty($_POST['blog_id'])) {
            try {
                $rs = App::core()->blogs()->getBlog($_POST['blog_id']);

                if ($rs->isEmpty()) {
                    App::core()->error()->add(__('No such blog ID'));
                } else {
                    $this->blog_id   = $rs->f('blog_id');
                    $this->blog_name = $rs->f('blog_name');
                }
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Delete the blog
        if (!App::core()->error()->flag() && $this->blog_id && !empty($_POST['del'])) {
            if (!App::core()->user()->checkPassword($_POST['pwd'])) {
                App::core()->error()->add(__('Password verification failed'));
            } else {
                try {
                    App::core()->blogs()->delBlog($this->blog_id);
                    App::core()->notice()->addSuccessNotice(sprintf(__('Blog "%s" successfully deleted'), Html::escapeHTML($this->blog_name)));

                    App::core()->adminurl()->redirect('admin.blogs');
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Delete a blog'))
            ->setPageBreadcrumb([
                __('System')        => '',
                __('Blogs')         => App::core()->adminurl()->get('admin.blogs'),
                __('Delete a blog') => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (App::core()->error()->flag()) {
            return;
        }

        echo '<div class="warning-msg"><p><strong>' . __('Warning') . '</strong></p>' .
        '<p>' . sprintf(
            __('You are about to delete the blog %s. Every entry, comment and category will be deleted.'),
            '<strong>' . $this->blog_id . ' (' . $this->blog_name . ')</strong>'
        ) . '</p></div>' .
        '<p>' . __('Please give your password to confirm the blog deletion.') . '</p>';

        echo '<form action="' . App::core()->adminurl()->root() . '" method="post">' .
        '<p><label for="pwd">' . __('Your password:') . '</label> ' .
        Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .
        '<p><input type="submit" class="delete" name="del" value="' . __('Delete this blog') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        Form::hidden('blog_id', $this->blog_id) .
        App::core()->adminurl()->getHiddenFormFields('admin.blog.del', [], true) . '</p>' .
            '</form>';
    }
}
