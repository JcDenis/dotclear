<?php
/**
 * @class Dotclear\Admin\Action\BlogAction
 * @brief Dotclear admin handler for action page on selected entries
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Action;

use Dotclear\Exception\AdminException;

use Dotclear\Admin\Action;
use Dotclear\Admin\Action\DefaultBlogAction;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

class BlogAction extends Action
{
    public function __construct(string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        # Action setup
        $this->redirect_fields = ['status', 'sortby', 'order', 'page', 'nb'];
        $this->field_entries   = 'blogs';
        $this->cb_title        = __('Blogs');
        $this->loadDefaults();
        dotclear()->behavior()->call('adminBlogsActionsPage', $this);

        # Page setup
        $this
            ->setPageTitle(__('Blogs'))
            ->setPageType($this->in_plugin ? 'plugin' : null)
            ->setPageHead(static::jsLoad('js/_blogs_actions.js'))
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog->name) => '',
                __('Blogs')                               => dotclear()->adminurl->get('admin.blogs'),
                __('Blogs actions')                       => ''
            ]);
    }

    protected function loadDefaults()
    {
        // We could have added a behavior here, but we want default action
        // to be setup first
        DefaultBlogAction::BlogsAction($this);
    }

    public function error(Exception $e)
    {
        dotclear()->error($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to blogs list') . '</a></p>');
    }

    public function getCheckboxes()
    {
        $ret = '';
        foreach ($this->entries as $id => $res) {
            $ret .= '<tr>' .
            '<td class="minimal">' . Form::checkbox([$this->field_entries . '[]'], $id,
                [
                    'checked' => true
                ]) .
                '</td>' .
                '<td>' . $res['blog'] . '</td>' .
                '<td>' . $res['name'] . '</td>' .
                '</tr>';
        }

        return
        '<table class="blogs-list"><tr>' .
        '<th colspan="2">' . __('Blog id') . '</th><th>' . __('Blog name') . '</th>' .
            '</tr>' . $ret . '</table>';
    }

    protected function fetchEntries($from)
    {
        $params = [];
        if (!empty($from['blogs'])) {
            $params['blog_id'] = $from['blogs'];
        }

        $bl = dotclear()->getBlogs($params);
        while ($bl->fetch()) {
            $this->entries[$bl->blog_id] = [
                'blog' => $bl->blog_id,
                'name' => $bl->blog_name
            ];
        }
        $this->rs = $bl;
    }
}
