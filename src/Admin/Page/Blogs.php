<?php
/**
 * @class Dotclear\Admin\Page\Blogs
 * @brief Dotclear admin home page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;
use Dotclear\Admin\Action\BlogAction;
use Dotclear\Admin\Catalog\BlogCatalog;
use Dotclear\Admin\Filter\BlogFilter;

use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Blogs extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->check('usage,contentadmin');

        /* Actions
        -------------------------------------------------------- */
        $blogs_actions_page = null;
        if ($this->core->auth->isSuperAdmin()) {
            $blogs_actions_page = new BlogAction($this->core, $this->core->adminurl->get('admin.blogs'));
            if ($blogs_actions_page->process()) {
                return;
            }
        }

        /* Filters
        -------------------------------------------------------- */
        $blog_filter = new BlogFilter($this->core);

        # get list params
        $params = $blog_filter->params();

        /* List
        -------------------------------------------------------- */
        $blog_list = null;

        try {
            # --BEHAVIOR-- adminGetBlogs
            $params = new \ArrayObject($params);
            $this->core->callBehavior('adminGetBlogs', $params);

            $counter  = $this->core->getBlogs($params, true);
            $rs       = $this->core->getBlogs($params);
            $nb_blog  = $counter->f(0);
            $rsStatic = $rs->toStatic();
            if (($blog_filter->sortby != 'blog_upddt') && ($blog_filter->sortby != 'blog_status')) {
                # Sort blog list using lexical order if necessary
                $rsStatic->extend('Dotclear\\Core\\RsExt\\rsExtUser');
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort(($blog_filter->sortby == 'UPPER(blog_name)' ? 'blog_name' : 'blog_id'), $blog_filter->order);
            }
            $blog_list = new BlogCatalog($this->core, $rs, $counter->f(0));
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }

        /* DISPLAY
        -------------------------------------------------------- */

        $this->open(__('List of blogs'),
            $blog_filter->js() . static::jsLoad('js/_blogs.js'),
            $this->breadcrumb(
                [
                    __('System')        => '',
                    __('List of blogs') => ''
                ])
        );

        if (!$this->core->error->flag()) {
            if ($this->core->auth->isSuperAdmin()) {
                echo '<p class="top-add"><a class="button add" href="' . $this->core->adminurl->get('admin.blog') . '">' . __('Create a new blog') . '</a></p>';
            }

            $blog_filter->display('admin.blogs');

            # Show blogs
            $blog_list->display($blog_filter->page, $blog_filter->nb,
                ($this->core->auth->isSuperAdmin() ?
                    '<form action="' . $this->core->adminurl->get('admin.blogs') . '" method="post" id="form-blogs">' : '') .

                '%s' .

                ($this->core->auth->isSuperAdmin() ?
                    '<div class="two-cols clearfix">' .
                    '<p class="col checkboxes-helpers"></p>' .

                    '<p class="col right"><label for="action" class="classic">' . __('Selected blogs action:') . '</label> ' .
                    Form::combo('action', $blogs_actions_page->getCombo(),
                        ['class' => 'online', 'extra_html' => 'title="' . __('Actions') . '"']) .
                    $core->formNonce() .
                    '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
                    '</div>' .

                    '<p><label for="pwd" class="classic">' . __('Please give your password to confirm blog(s) deletion:') . '</label> ' .
                    Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .

                    $this->core->adminurl->getHiddenFormFields('admin.blogs', $blog_filter->values(true)) .
                    '</form>' : ''),
                $blog_filter->show()
            );
        }

        $this->helpBlock('core_blogs');
        $this->close();
    }
}
