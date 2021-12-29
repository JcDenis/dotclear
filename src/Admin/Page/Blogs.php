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

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

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
    protected function getPermissions(): string
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?Action
    {
        return $this->core->auth->isSuperAdmin() ? new BlogAction($this->core, $this->core->adminurl->get('admin.blogs')) : null;
    }

    protected function getFilterInstance(): ?Filter
    {
        return new BlogFilter($this->core);
    }

    protected function getCatalogInstance(): ?Catalog
    {
        $params = $this->filter->params();
        $params = new ArrayObject($params);

        # --BEHAVIOR-- before:Admin:Blogs:getblogs, ArrayObject
        $this->core->behaviors->call('before:Admin:Blogs:getblogs', $params);

        $counter  = $this->core->getBlogs($params, true);
        $rs       = $this->core->getBlogs($params);
        $nb_blog  = $counter->f(0);
        $rsStatic = $rs->toStatic();
        if (($this->filter->sortby != 'blog_upddt') && ($this->filter->sortby != 'blog_status')) {
            # Sort blog list using lexical order if necessary
            $rsStatic->extend('Dotclear\\Core\\RsExt\\rsExtUser');
            $rsStatic = $rsStatic->toExtStatic();
            $rsStatic->lexicalSort(($this->filter->sortby == 'UPPER(blog_name)' ? 'blog_name' : 'blog_id'), $this->filter->order);
        }

        return new BlogCatalog($this->core, $rs, $counter->f(0));
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
        if ($this->core->error->flag()) {
            return;
        }

        if ($this->core->auth->isSuperAdmin()) {
            echo '<p class="top-add"><a class="button add" href="' . $this->core->adminurl->get('admin.blog') . '">' . __('Create a new blog') . '</a></p>';
        }

        $this->filter->display('admin.blogs');

        # Show blogs
        $this->catalog->display($this->filter->page, $this->filter->nb,
            ($this->core->auth->isSuperAdmin() ?
                '<form action="' . $this->core->adminurl->get('admin.blogs') . '" method="post" id="form-blogs">' : '') .

            '%s' .

            ($this->core->auth->isSuperAdmin() ?
                '<div class="two-cols clearfix">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected blogs action:') . '</label> ' .
                Form::combo('action', $this->action->getCombo(),
                    ['class' => 'online', 'extra_html' => 'title="' . __('Actions') . '"']) .
                $this->core->formNonce() .
                '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
                '</div>' .

                '<p><label for="pwd" class="classic">' . __('Please give your password to confirm blog(s) deletion:') . '</label> ' .
                Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .

                $this->core->adminurl->getHiddenFormFields('admin.blogs', $this->filter->values(true)) .
                '</form>' : ''),
            $this->filter->show()
        );
    }
}
