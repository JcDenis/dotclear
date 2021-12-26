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

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Admin\Action;
use Dotclear\Admin\Action\DefaultPostAction;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

class PostAction extends Action
{
    public function __construct(Core $core, $uri, $redirect_args = [])
    {
        parent::__construct($core, $uri, $redirect_args);

        $this->redirect_fields = ['user_id', 'cat_id', 'status',
            'selected', 'attachment', 'month', 'lang', 'sortby', 'order', 'page', 'nb'];
        $this->loadDefaults();
    }

    protected function loadDefaults()
    {
        // We could have added a behavior here, but we want default action
        // to be setup first
        DefaultPostAction::PostAction($this->core, $this);
        $this->core->behaviors->call('adminPostsActionsPage', $this);
    }

    public function beginPage($breadcrumb = '', $head = '')
    {
        if ($this->in_plugin) {
            echo '<html><head><title>' . __('Posts') . '</title>' .
            static::jsLoad('js/_posts_actions.js') .
                $head .
                '</script></head><body>' .
                $breadcrumb;
        } else {
            $this->open(
                __('Posts'),
                static::jsLoad('js/_posts_actions.js') .
                $head,
                $breadcrumb
            );
        }
        echo '<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to entries list') . '</a></p>';
    }

    public function endPage()
    {
        if ($this->in_plugin) {
            echo '</body></html>';
        } else {
            $this->close();
        }
    }

    public function error(AdminException $e)
    {
        $this->core->error->add($e->getMessage());
        $this->beginPage($this->breadcrumb(
            [
                Html::escapeHTML($this->core->blog->name) => '',
                $this->getCallerTitle()                   => $this->getRedirection(true),
                __('Posts actions')                       => ''
            ])
        );
        $this->endPage();
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

        $posts = $this->core->blog->getPosts($params);
        while ($posts->fetch()) {
            $this->entries[$posts->post_id] = $posts->post_title;
        }
        $this->rs = $posts;
    }
}
