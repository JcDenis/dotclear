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
use Dotclear\Core\RsExt\RsExtUser;
use Dotclear\Database\Param;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Action\Action\BlogAction;
use Dotclear\Process\Admin\Inventory\Inventory\BlogInventory;
use Dotclear\Process\Admin\Filter\Filter\BlogFilters;
use Dotclear\Helper\Html\Form;

/**
 * Admin blogs list page.
 *
 * @ingroup  Admin Blog Handler
 */
class Blogs extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?BlogAction
    {
        return App::core()->user()->isSuperAdmin() ? new BlogAction(App::core()->adminurl()->get('admin.blogs')) : null;
    }

    protected function getFilterInstance(): ?BlogFilters
    {
        return new BlogFilters();
    }

    protected function getInventoryInstance(): ?BlogInventory
    {
        $param = $this->filter->getParams();

        // --BEHAVIOR-- adminGetBlogs, Param
        App::core()->behavior('adminGetBlogs')->call($param);

        $count = App::core()->blogs()->countBlogs(param: $param);
        $rs    = App::core()->blogs()->getBlogs(param: $param);

        $rsStatic = $rs->toStatic();
        if ('blog_upddt' != $this->filter->getValue(id: 'sortby') && 'blog_status' != $this->filter->getValue(id: 'sortby')) {
            // Sort blog list using lexical order if necessary
            $rsStatic->extend(new RsExtUser());
            // $rsStatic = $rsStatic->toExtStatic();
            $rsStatic->lexicalSort(($this->filter->getValue(id: 'sortby') == 'UPPER(blog_name)' ? 'blog_name' : 'blog_id'), $this->filter->getValue(id: 'order'));
        }

        return new BlogInventory($rs, $count);
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageHelp('core_blogs')
            ->setPageTitle(__('List of blogs'))
            ->setPageHead(
                $this->filter?->getFoldableJSCode() .
                App::core()->resource()->load('_blogs.js')
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
        if (App::core()->error()->flag()) {
            return;
        }

        if (App::core()->user()->isSuperAdmin()) {
            echo '<p class="top-add"><a class="button add" href="' . App::core()->adminurl()->get('admin.blog') . '">' . __('Create a new blog') . '</a></p>';
        }

        $this->filter?->displayHTMLForm(adminurl: 'admin.blogs');

        // Show blogs
        $this->inventory?->display(
            $this->filter->getValue(id: 'page'),
            $this->filter->getValue(id: 'nb'),
            (App::core()->user()->isSuperAdmin() ?
                '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-blogs">' : '') .

            '%s' .

            (App::core()->user()->isSuperAdmin() ?
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

                App::core()->adminurl()->getHiddenFormFields('admin.blogs', $this->filter->getEscapeValues(), true) .
                '</form>' : ''),
            $this->filter->isUnfolded()
        );
    }
}
