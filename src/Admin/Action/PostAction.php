<?php
/**
 * @class Dotclear\Admin\Action\PostAction
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
use Dotclear\Admin\Action\DefaultPostAction;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

class PostAction extends Action
{
    public function __construct($uri, $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        # Action setup
        $this->redirect_fields = ['user_id', 'cat_id', 'status',
            'selected', 'attachment', 'month', 'lang', 'sortby', 'order', 'page', 'nb'];
        $this->loadDefaults();

        # Page setup
        $this->setPageTitle(__('Posts'));
        $this->setPageType($this->in_plugin ? 'plugin' : null);
        $this->setPageHead(static::jsLoad('js/_posts_actions.js'));
        $this->setPageBreadcrumb([
            Html::escapeHTML(dotclear()->blog->name) => '',
            $this->getCallerTitle()                   => $this->getRedirection(true),
            __('Posts actions')                       => ''
        ]);
    }

    protected function loadDefaults()
    {
        // We could have added a behavior here, but we want default action
        // to be setup first
        DefaultPostAction::PostAction($this);
        dotclear()->behaviors->call('adminPostsActionsPage', $this);
    }

    public function error(Exception $e)
    {
        dotclear()->error($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to entries list') . '</a></p>');
    }

    protected function fetchEntries($from)
    {
        $params = [];
        if (!empty($from['entries'])) {
            $entries = $from['entries'];

            foreach ($entries as $k => $v) {
                $entries[$k] = (integer) $v;
            }

            $params['sql'] = 'AND P.post_id IN(' . implode(',', $entries) . ') ';
        } else {
            $params['sql'] = 'AND 1=0 ';
        }

        if (!isset($from['full_content']) || empty($from['full_content'])) {
            $params['no_content'] = true;
        }

        if (isset($from['post_type'])) {
            $params['post_type'] = $from['post_type'];
        }

        $posts = dotclear()->blog->getPosts($params);
        while ($posts->fetch()) {
            $this->entries[$posts->post_id] = $posts->post_title;
        }
        $this->rs = $posts;
    }
}
