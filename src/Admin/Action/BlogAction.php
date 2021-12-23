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

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Admin\Action;
use Dotclear\Admin\Action\DefaultBlogAction;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

class BlogAction extends Action
{
    public function __construct(Core $core, string $uri, array $redirect_args = [])
    {
        parent::__construct($core, $uri, $redirect_args);

        $this->redirect_fields = ['status', 'sortby', 'order', 'page', 'nb'];
        $this->field_entries   = 'blogs';
        $this->cb_title        = __('Blogs');
        $this->loadDefaults();
        $core->callBehavior('adminBlogsActionsPage', $this);
    }

    protected function loadDefaults()
    {
        // We could have added a behavior here, but we want default action
        // to be setup first
        DefaultBlogAction::BlogsAction($this->core, $this);
    }

    public function beginPage($breadcrumb = '', $head = '')
    {
        if ($this->in_plugin) {
            echo '<html><head><title>' . __('Blogs') . '</title>' .
            static::jsLoad('js/_blogs_actions.js') .
                $head .
                '</script></head><body>' .
                $breadcrumb;
        } else {
            $this->open(
                __('Blogs'),
                static::jsLoad('js/_blogs_actions.js') .
                $head,
                $breadcrumb
            );
        }
        echo '<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to blogs list') . '</a></p>';
    }

    public function endPage()
    {
        $this->close();
    }

    public function error(AdminException $e)
    {
        $this->core->error->add($e->getMessage());
        $this->beginPage($this->breadcrumb(
            [
                Html::escapeHTML($this->core->blog->name) => '',
                __('Blogs')                               => $this->core->adminurl->get('admin.blogs'),
                __('Blogs actions')                       => ''
            ])
        );
        $this->endPage();
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

        $bl = $this->core->getBlogs($params);
        while ($bl->fetch()) {
            $this->entries[$bl->blog_id] = [
                'blog' => $bl->blog_id,
                'name' => $bl->blog_name
            ];
        }
        $this->rs = $bl;
    }
}
