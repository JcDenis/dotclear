<?php
/**
 * @class Dotclear\Admin\Page\Blogs
 * @brief Dotclear admin blogs list page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use ArrayObject;


use Dotclear\Admin\Page;
use Dotclear\Admin\Action;
use Dotclear\Admin\Filter;
use Dotclear\Admin\Catalog;

use Dotclear\Admin\Action\BlogAction;
use Dotclear\Admin\Catalog\BlogCatalog;
use Dotclear\Admin\Filter\BlogFilter;

use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Blogs extends Page
{
    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?Action
    {
        return dotclear()->auth->isSuperAdmin() ? new BlogAction(dotclear()->adminurl->get('admin.blogs')) : null;
    }

    protected function getFilterInstance(): ?Filter
    {
        return new BlogFilter(dotclear());
    }

    protected function getCatalogInstance(): ?Catalog
    {
        $params = $this->filter->params();
        $params = new ArrayObject($params);

        # --BEHAVIOR-- adminGetBlogs, ArrayObject
        dotclear()->behavior()->call('adminGetBlogs', $params);

        $counter  = dotclear()->getBlogs($params, true);
        $rs       = dotclear()->getBlogs($params);
        $nb_blog  = $counter->f(0);
        $rsStatic = $rs->toStatic();
        if (($this->filter->sortby != 'blog_upddt') && ($this->filter->sortby != 'blog_status')) {
            # Sort blog list using lexical order if necessary
            $rsStatic->extend('Dotclear\\Core\\RsExt\\RsExtUser');
            $rsStatic = $rsStatic->toExtStatic();
            $rsStatic->lexicalSort(($this->filter->sortby == 'UPPER(blog_name)' ? 'blog_name' : 'blog_id'), $this->filter->order);
        }

        return new BlogCatalog($rs, $counter->f(0));
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageHelp('core_blogs')
            ->setPageTitle(__('List of blogs'))
            ->setPageHead(
                $this->filter->js() .
                static::jsLoad('js/_blogs.js')
            )
            ->setPageBreadcrumb([
                __('System')        => '',
                __('List of blogs') => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (dotclear()->error()->flag()) {
            return;
        }

        if (dotclear()->auth->isSuperAdmin()) {
            echo '<p class="top-add"><a class="button add" href="' . dotclear()->adminurl->get('admin.blog') . '">' . __('Create a new blog') . '</a></p>';
        }

        $this->filter->display('admin.blogs');

        # Show blogs
        $this->catalog->display($this->filter->page, $this->filter->nb,
            (dotclear()->auth->isSuperAdmin() ?
                '<form action="' . dotclear()->adminurl->get('admin.blogs') . '" method="post" id="form-blogs">' : '') .

            '%s' .

            (dotclear()->auth->isSuperAdmin() ?
                '<div class="two-cols clearfix">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected blogs action:') . '</label> ' .
                Form::combo('action', $this->action->getCombo(),
                    ['class' => 'online', 'extra_html' => 'title="' . __('Actions') . '"']) .
                dotclear()->formNonce() .
                '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
                '</div>' .

                '<p><label for="pwd" class="classic">' . __('Please give your password to confirm blog(s) deletion:') . '</label> ' .
                Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .

                dotclear()->adminurl->getHiddenFormFields('admin.blogs', $this->filter->values(true)) .
                '</form>' : ''),
            $this->filter->show()
        );
    }
}
