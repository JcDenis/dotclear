<?php
/**
 * @note Dotclear\Process\Admin\Handler\BlogDel
 * @brief Dotclear admin blog deletion page
 *
 * @ingroup  Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Exception;

class BlogDel extends AbstractPage
{
    private $blog_id   = '';
    private $blog_name = '';

    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        // search the blog
        $rs = null;
        if (!empty($_POST['blog_id'])) {
            try {
                $rs = dotclear()->blogs()->getBlog($_POST['blog_id']);

                if ($rs->isEmpty()) {
                    dotclear()->error()->add(__('No such blog ID'));
                } else {
                    $this->blog_id   = $rs->f('blog_id');
                    $this->blog_name = $rs->f('blog_name');
                }
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        // Delete the blog
        if (!dotclear()->error()->flag() && $this->blog_id && !empty($_POST['del'])) {
            if (!dotclear()->user()->checkPassword($_POST['pwd'])) {
                dotclear()->error()->add(__('Password verification failed'));
            } else {
                try {
                    dotclear()->blogs()->delBlog($this->blog_id);
                    dotclear()->notice()->addSuccessNotice(sprintf(__('Blog "%s" successfully deleted'), Html::escapeHTML($this->blog_name)));

                    dotclear()->adminurl()->redirect('admin.blogs');
                } catch (Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Delete a blog'))
            ->setPageBreadcrumb([
                __('System')        => '',
                __('Blogs')         => dotclear()->adminurl()->get('admin.blogs'),
                __('Delete a blog') => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (dotclear()->error()->flag()) {
            return;
        }

        echo '<div class="warning-msg"><p><strong>' . __('Warning') . '</strong></p>' .
        '<p>' . sprintf(
            __('You are about to delete the blog %s. Every entry, comment and category will be deleted.'),
            '<strong>' . $this->blog_id . ' (' . $this->blog_name . ')</strong>'
        ) . '</p></div>' .
        '<p>' . __('Please give your password to confirm the blog deletion.') . '</p>';

        echo '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
        '<p><label for="pwd">' . __('Your password:') . '</label> ' .
        Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .
        '<p><input type="submit" class="delete" name="del" value="' . __('Delete this blog') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        Form::hidden('blog_id', $this->blog_id) .
        dotclear()->adminurl()->getHiddenFormFields('admin.blog.del', [], true) . '</p>' .
            '</form>';
    }
}
