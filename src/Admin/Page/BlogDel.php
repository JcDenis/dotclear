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

use Dotclear\Admin\Page;
use Dotclear\Admin\Notices;

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
                $rs = $this->core->getBlog($_POST['blog_id']);
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }

            if ($rs->isEmpty()) {
                $this->core->error->add(__('No such blog ID'));
            } else {
                $this->blog_id   = $rs->blog_id;
                $this->blog_name = $rs->blog_name;
            }
        }

        # Delete the blog
        if (!$this->core->error->flag() && $this->blog_id && !empty($_POST['del'])) {
            if (!$this->core->auth->checkPassword($_POST['pwd'])) {
                $this->core->error->add(__('Password verification failed'));
            } else {
                try {
                    $this->core->delBlog($this->blog_id);
                    $this->core->notices->addSuccessNotice(sprintf(__('Blog "%s" successfully deleted'), html::escapeHTML($this->blog_name)));

                    $this->core->adminurl->redirect('admin.blogs');
                } catch (Exception $e) {
                    $this->core->error->add($e->getMessage());
                }
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Delete a blog'))
            ->setPageBreadcrumb([
                __('System')        => '',
                __('Blogs')         => $this->core->adminurl->get('admin.blogs'),
                __('Delete a blog') => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if ($this->core->error->flag()) {
            return;
        }

        echo
        '<div class="warning-msg"><p><strong>' . __('Warning') . '</strong></p>' .
        '<p>' . sprintf(__('You are about to delete the blog %s. Every entry, comment and category will be deleted.'),
            '<strong>' . $this->blog_id . ' (' . $this->blog_name . ')</strong>') . '</p></div>' .
        '<p>' . __('Please give your password to confirm the blog deletion.') . '</p>';

        echo
        '<form action="' . $this->core->adminurl->get('admin.blog.del') . '" method="post">' .
        '<div>' . $this->core->formNonce() . '</div>' .
        '<p><label for="pwd">' . __('Your password:') . '</label> ' .
        form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .
        '<p><input type="submit" class="delete" name="del" value="' . __('Delete this blog') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        form::hidden('blog_id', $this->blog_id) . '</p>' .
            '</form>';
    }
}
