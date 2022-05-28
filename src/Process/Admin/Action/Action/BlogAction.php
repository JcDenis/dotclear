<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

// Dotclear\Process\Admin\Action\Action\BlogAction
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\GPC\GPCGroup;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\Form;
use Exception;

/**
 * Admin handler for action on selected blogs.
 *
 * @ingroup  Admin Blog Action
 */
class BlogAction extends DefaultBlogAction
{
    public function __construct(string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        // Action setup
        $this->redirect_fields = ['status', 'sortby', 'order', 'page', 'nb'];
        $this->field_entries   = 'blogs';
        $this->cb_title        = __('Blogs');
        $this->loadDefaults();
        App::core()->behavior()->call('adminBlogsActionsPage', $this);

        // Page setup
        $this
            ->setPageTitle(__('Blogs'))
            ->setPageType($this->in_plugin ? 'plugin' : 'full')
            ->setPageHead(App::core()->resource()->load('_blogs_actions.js'))
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Blogs')                                 => App::core()->adminurl()->get('admin.blogs'),
                __('Blogs actions')                         => '',
            ])
        ;
    }

    protected function loadDefaults(): void
    {
        $this->loadBlogsAction($this);
    }

    protected function error(Exception $e): void
    {
        App::core()->error()->add($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to blogs list') . '</a></p>');
    }

    public function getCheckboxes(): string
    {
        $ret = '';
        foreach ($this->entries as $id => $res) {
            $ret .= '<tr>' .
            '<td class="minimal">' . Form::checkbox(
                [$this->field_entries . '[]'],
                $id,
                [
                    'checked' => true,
                ]
            ) .
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

    protected function fetchEntries(GPCgroup $from): void
    {
        $param = new Param();
        if (!$from->empty('blogs')) {
            $param->set('blog_id', $from->array('blogs'));
        }

        $record = App::core()->blogs()->getBlogs(param: $param);
        while ($record->fetch()) {
            $this->entries[$record->f('blog_id')] = [
                'blog' => $record->f('blog_id'),
                'name' => $record->f('blog_name'),
            ];
        }
        $this->rs = $record;
    }
}
