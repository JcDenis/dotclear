<?php
/**
 * @class Dotclear\Admin\Page\Action\Action\CommentAction
 * @brief Dotclear admin handler for action page on selected comments
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page\Action\Action;

use Dotclear\Exception\AdminException;

use Dotclear\Admin\Page\Action\Action;
use Dotclear\Admin\Page\Action\Action\DefaultCommentAction;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

class CommentAction extends Action
{
    public function __construct($uri, $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        $this->redirect_fields = ['type', 'author', 'status',
            'sortby', 'ip', 'order', 'page', 'nb', 'section'];
        $this->field_entries = 'comments';
        $this->cb_title      = __('Comments');
        $this->loadDefaults();

        # Page setup
        $this->setPageTitle(__('Comments'));
        $this->setPageType($this->in_plugin ? 'plugin' : null);
        $this->setPageHead(static::jsLoad('js/_posts_actions.js'));
        $this->setPageBreadcrumb([
            Html::escapeHTML(dotclear()->blog()->name) => '',
            __('Comments')                            => dotclear()->adminurl()->get('admin.comments'),
            __('Comments actions')                    => ''
        ]);
    }

    protected function loadDefaults()
    {
        // We could have added a behavior here, but we want default action
        // to be setup first
        DefaultCommentAction::CommentAction($this);
        dotclear()->behavior()->call('adminCommentsActionsPage', $this);
    }

    public function error(\Exception $e)
    {
        dotclear()->error()->add($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back') . '</a></p>');
    }

    /**
     * getcheckboxes -returns html code for selected entries
     *             as a table containing entries checkboxes
     *
     * @access public
     *
     * @return string the html code for checkboxes
     */
    public function getCheckboxes()
    {
        $ret = '<table class="posts-list"><tr>' .
        '<th colspan="2">' . __('Author') . '</th><th>' . __('Title') . '</th>' .
            '</tr>';
        foreach ($this->entries as $id => $title) {
            $ret .= '<tr><td class="minimal">' .
            Form::checkbox([$this->field_entries . '[]'], $id,
                [
                    'checked' => true
                ]) .
                '</td>' .
                '<td>' . $title['author'] . '</td><td>' . $title['title'] . '</td></tr>';
        }
        $ret .= '</table>';

        return $ret;
    }

    protected function fetchEntries($from)
    {
        $params = [];
        if (!empty($from['comments'])) {
            $comments = $from['comments'];

            foreach ($comments as $k => $v) {
                $comments[$k] = (integer) $v;
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
                'author' => $co->comment_author
            ];
        }
        $this->rs = $co;
    }
}
