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

use Dotclear\Exception;

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
                $rs = dcCore()->getBlog($_POST['blog_id']);
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }

            if ($rs->isEmpty()) {
                dcCore()->error(__('No such blog ID'));
            } else {
                $this->blog_id   = $rs->blog_id;
                $this->blog_name = $rs->blog_name;
            }
        }

        # Delete the blog
        if (!dcCore()->error()->flag() && $this->blog_id && !empty($_POST['del'])) {
            if (!dcCore()->auth->checkPassword($_POST['pwd'])) {
                dcCore()->error(__('Password verification failed'));
            } else {
                try {
                    dcCore()->delBlog($this->blog_id);
                    dcCore()->notices->addSuccessNotice(sprintf(__('Blog "%s" successfully deleted'), Html::escapeHTML($this->blog_name)));

                    dcCore()->adminurl->redirect('admin.blogs');
                } catch (Exception $e) {
                    dcCore()->error($e->getMessage());
                }
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Delete a blog'))
            ->setPageBreadcrumb([
                __('System')        => '',
                __('Blogs')         => dcCore()->adminurl->get('admin.blogs'),
                __('Delete a blog') => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (dcCore()->error()->flag()) {
            return;
        }

        echo
        '<div class="warning-msg"><p><strong>' . __('Warning') . '</strong></p>' .
        '<p>' . sprintf(__('You are about to delete the blog %s. Every entry, comment and category will be deleted.'),
            '<strong>' . $this->blog_id . ' (' . $this->blog_name . ')</strong>') . '</p></div>' .
        '<p>' . __('Please give your password to confirm the blog deletion.') . '</p>';

        echo
        '<form action="' . dcCore()->adminurl->get('admin.blog.del') . '" method="post">' .
        '<div>' . dcCore()->formNonce() . '</div>' .
        '<p><label for="pwd">' . __('Your password:') . '</label> ' .
        Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .
        '<p><input type="submit" class="delete" name="del" value="' . __('Delete this blog') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        Form::hidden('blog_id', $this->blog_id) . '</p>' .
            '</form>';
    }
}
