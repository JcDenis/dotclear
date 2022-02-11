<?php
/**
 * @class Dotclear\Admin\Page\BlogDel
 * @brief Dotclear admin blog deletion page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;


use Dotclear\Admin\Page;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class BlogDel extends Page
{
    private $blog_id   = '';
    private $blog_name = '';

    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        # search the blog
        $rs = null;
        if (!empty($_POST['blog_id'])) {
            try {
                $rs = dotclear()->getBlog($_POST['blog_id']);
            } catch (\Exception $e) {
                dotclear()->error($e->getMessage());
            }

            if ($rs->isEmpty()) {
                dotclear()->error(__('No such blog ID'));
            } else {
                $this->blog_id   = $rs->blog_id;
                $this->blog_name = $rs->blog_name;
            }
        }

        # Delete the blog
        if (!dotclear()->error()->flag() && $this->blog_id && !empty($_POST['del'])) {
            if (!dotclear()->auth->checkPassword($_POST['pwd'])) {
                dotclear()->error(__('Password verification failed'));
            } else {
                try {
                    dotclear()->delBlog($this->blog_id);
                    dotclear()->notices->addSuccessNotice(sprintf(__('Blog "%s" successfully deleted'), Html::escapeHTML($this->blog_name)));

                    dotclear()->adminurl->redirect('admin.blogs');
                } catch (\Exception $e) {
                    dotclear()->error($e->getMessage());
                }
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Delete a blog'))
            ->setPageBreadcrumb([
                __('System')        => '',
                __('Blogs')         => dotclear()->adminurl->get('admin.blogs'),
                __('Delete a blog') => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (dotclear()->error()->flag()) {
            return;
        }

        echo
        '<div class="warning-msg"><p><strong>' . __('Warning') . '</strong></p>' .
        '<p>' . sprintf(__('You are about to delete the blog %s. Every entry, comment and category will be deleted.'),
            '<strong>' . $this->blog_id . ' (' . $this->blog_name . ')</strong>') . '</p></div>' .
        '<p>' . __('Please give your password to confirm the blog deletion.') . '</p>';

        echo
        '<form action="' . dotclear()->adminurl->get('admin.blog.del') . '" method="post">' .
        '<div>' . dotclear()->formNonce() . '</div>' .
        '<p><label for="pwd">' . __('Your password:') . '</label> ' .
        Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .
        '<p><input type="submit" class="delete" name="del" value="' . __('Delete this blog') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        Form::hidden('blog_id', $this->blog_id) . '</p>' .
            '</form>';
    }
}
