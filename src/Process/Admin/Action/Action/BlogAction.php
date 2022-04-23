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
use ArrayObject;
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
        dotclear()->behavior()->call('adminBlogsActionsPage', $this);

        // Page setup
        $this
            ->setPageTitle(__('Blogs'))
            ->setPageType($this->in_plugin ? 'plugin' : 'full')
            ->setPageHead(dotclear()->resource()->load('_blogs_actions.js'))
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Blogs')                                => dotclear()->adminurl()->get('admin.blogs'),
                __('Blogs actions')                        => '',
            ])
        ;
    }

    protected function loadDefaults(): void
    {
        $this->loadBlogsAction($this);
    }

    public function error(Exception $e): void
    {
        dotclear()->error()->add($e->getMessage());
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

    protected function fetchEntries(ArrayObject $from): void
    {
        $params = [];
        if (!empty($from['blogs'])) {
            $params['blog_id'] = $from['blogs'];
        }

        $rs = dotclear()->blogs()->getBlogs($params);
        while ($rs->fetch()) {
            $this->entries[$rs->f('blog_id')] = [
                'blog' => $rs->f('blog_id'),
                'name' => $rs->f('blog_name'),
            ];
        }
        $this->rs = $rs;
    }
}
