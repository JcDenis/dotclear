<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use ArrayObject;
use Dotclear\Core\RsExt\RsExtUser;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Action\Action\BlogAction;
use Dotclear\Process\Admin\Inventory\Inventory\BlogInventory;
use Dotclear\Process\Admin\Filter\Filter\BlogFilter;
use Dotclear\Helper\Html\Form;

/**
 * Admin blogs list page.
 *
 * \Dotclear\Process\Admin\Handler\Blog
 *
 * @ingroup  Admin Blog Handler
 */
class Blogs extends AbstractPage
{
    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?BlogAction
    {
        return dotclear()->user()->isSuperAdmin() ? new BlogAction(dotclear()->adminurl()->get('admin.blogs')) : null;
    }

    protected function getFilterInstance(): ?BlogFilter
    {
        return new BlogFilter();
    }

    protected function getInventoryInstance(): ?BlogInventory
    {
        $params = $this->filter->params();
        $params = new ArrayObject($params);

        // --BEHAVIOR-- adminGetBlogs, ArrayObject
        dotclear()->behavior()->call('adminGetBlogs', $params);

        $counter  = dotclear()->blogs()->getBlogs($params, true);
        $rs       = dotclear()->blogs()->getBlogs($params);
        $nb_blog  = $counter->fInt();
        $rsStatic = $rs->toStatic();
        if (($this->filter->get('sortby') != 'blog_upddt') && ($this->filter->get('sortby') != 'blog_status')) {
            // Sort blog list using lexical order if necessary
            $rsStatic->extend(new RsExtUser());
            // $rsStatic = $rsStatic->toExtStatic();
            $rsStatic->lexicalSort(($this->filter->get('sortby') == 'UPPER(blog_name)' ? 'blog_name' : 'blog_id'), $this->filter->get('order'));
        }

        return new BlogInventory($rs, $counter->fInt());
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageHelp('core_blogs')
            ->setPageTitle(__('List of blogs'))
            ->setPageHead(
                $this->filter->js() .
                dotclear()->resource()->load('_blogs.js')
            )
            ->setPageBreadcrumb([
                __('System')        => '',
                __('List of blogs') => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (dotclear()->error()->flag()) {
            return;
        }

        if (dotclear()->user()->isSuperAdmin()) {
            echo '<p class="top-add"><a class="button add" href="' . dotclear()->adminurl()->get('admin.blog') . '">' . __('Create a new blog') . '</a></p>';
        }

        $this->filter->display('admin.blogs');

        // Show blogs
        $this->inventory->display(
            $this->filter->get('page'),
            $this->filter->get('nb'),
            (dotclear()->user()->isSuperAdmin() ?
                '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-blogs">' : '') .

            '%s' .

            (dotclear()->user()->isSuperAdmin() ?
                '<div class="two-cols clearfix">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected blogs action:') . '</label> ' .
                Form::combo(
                    'action',
                    $this->action->getCombo(),
                    ['class' => 'online', 'extra_html' => 'title="' . __('Actions') . '"']
                ) .
                '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
                '</div>' .

                '<p><label for="pwd" class="classic">' . __('Please give your password to confirm blog(s) deletion:') . '</label> ' .
                Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .

                dotclear()->adminurl()->getHiddenFormFields('admin.blogs', $this->filter->values(true), true) .
                '</form>' : ''),
            $this->filter->show()
        );
    }
}
