<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

use ArrayObject;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Admin handler for action on selected comments.
 *
 * \Dotclear\Process\Admin\Action\Action\CommentAction
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
        $this->setPageHead(dotclear()->resource()->load('_posts_actions.js'));
        $this->setPageBreadcrumb([
            Html::escapeHTML(dotclear()->blog()->name) => '',
            __('Comments')                             => dotclear()->adminurl()->get('admin.comments'),
            __('Comments actions')                     => '',
        ]);
    }

    protected function loadDefaults(): void
    {
        $this->loadCommentAction($this);
        dotclear()->behavior()->call('adminCommentsActionsPage', $this);
    }

    public function error(Exception $e): void
    {
        dotclear()->error()->add($e->getMessage());
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

    protected function fetchEntries(ArrayObject $from): void
    {
        $params = [];
        if (!empty($from['comments'])) {
            $comments = $from['comments'];

            foreach ($comments as $k => $v) {
                $comments[$k] = (int) $v;
            }

            $params['sql'] = 'AND C.comment_id IN(' . implode(',', $comments) . ') ';
        } else {
            $params['sql'] = 'AND 1=0 ';
        }

        if (!isset($from['full_content']) || empty($from['full_content'])) {
            $params['no_content'] = true;
        }
        $co = dotclear()->blog()->comments()->getComments($params);
        while ($co->fetch()) {
            $this->entries[$co->comment_id] = [
                'title'  => $co->post_title,
                'author' => $co->comment_author,
            ];
        }
        $this->rs = $co;
    }
}
