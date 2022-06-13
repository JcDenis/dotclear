<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

// Dotclear\Process\Admin\Action\Action\CommentAction
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\GPC\GPCGroup;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Admin handler for action on selected comments.
 *
 * @ingroup  Admin Comment Action
 */
class CommentAction extends DefaultCommentAction
{
    public function __construct(string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        $this->redirect_fields = ['type', 'author', 'status',
            'sortby', 'ip', 'order', 'page', 'nb', 'section', ];
        $this->field_entries = 'comments';
        $this->cb_title      = __('Comments');
        $this->loadDefaults();

        // Page setup
        $this->setPageTitle(__('Comments'));
        $this->setPageType($this->in_plugin ? 'plugin' : 'full');
        $this->setPageHead(App::core()->resource()->load('_posts_actions.js'));
        $this->setPageBreadcrumb([
            Html::escapeHTML(App::core()->blog()->name) => '',
            __('Comments')                              => App::core()->adminurl()->get('admin.comments'),
            __('Comments actions')                      => '',
        ]);
    }

    protected function loadDefaults(): void
    {
        $this->loadCommentAction($this);
        App::core()->behavior()->call('adminCommentsActionsPage', $this);
    }

    protected function error(Exception $e): void
    {
        App::core()->error()->add($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back') . '</a></p>');
    }

    public function getCheckboxes(): string
    {
        $ret = '<table class="posts-list"><tr>' .
        '<th colspan="2">' . __('Author') . '</th><th>' . __('Title') . '</th>' .
            '</tr>';
        foreach ($this->entries as $id => $title) {
            $ret .= '<tr><td class="minimal">' .
            Form::checkbox(
                [$this->field_entries . '[]'],
                $id,
                [
                    'checked' => true,
                ]
            ) .
                '</td>' .
                '<td>' . $title['author'] . '</td><td>' . $title['title'] . '</td></tr>';
        }
        $ret .= '</table>';

        return $ret;
    }

    protected function fetchEntries(GPCGroup $from): void
    {
        $param = new Param();
        if (!$from->empty('comments')) {
            $comments = [];
            foreach ($from->array('comments') as $v) {
                $comments[] = (int) $v;
            }

            $param->push('sql', ' AND C.comment_id IN(' . implode(',', $comments) . ') ');
        } else {
            $param->push('sql', ' AND 1=0 ');
        }

        if ($from->empty('full_content')) {
            $param->set('no_content', true);
        }

        $record = App::core()->blog()->comments()->getComments(param: $param);
        while ($record->fetch()) {
            $this->entries[$record->integer('comment_id')] = [
                'title'  => $record->field('post_title'),
                'author' => $record->field('comment_author'),
            ];
        }
        $this->rs = $record;
    }
}
