<?php
/**
 * @class Dotclear\Process\Admin\Action\Action\PostAction
 * @brief Dotclear admin handler for action page on selected entries
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

use ArrayObject;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Action\Action\DefaultPostAction;

class PostAction extends DefaultPostAction
{
    public function __construct(string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        # Action setup
        $this->redirect_fields = ['user_id', 'cat_id', 'status',
            'selected', 'attachment', 'month', 'lang', 'sortby', 'order', 'page', 'nb'];
        $this->loadDefaults();

        # Page setup
        $this->setPageTitle(__('Posts'));
        $this->setPageType($this->in_plugin ? 'plugin' : 'full');
        $this->setPageHead(dotclear()->resource()->load('_posts_actions.js'));
        $this->setPageBreadcrumb([
            Html::escapeHTML(dotclear()->blog()->name) => '',
            $this->getCallerTitle()                    => $this->getRedirection(true),
            __('Posts actions')                        => ''
        ]);
    }

    protected function loadDefaults(): void
    {
        $this->loadPostAction($this);
        dotclear()->behavior()->call('adminPostsActionsPage', $this);
    }

    public function error(\Exception $e)
    {
        dotclear()->error()->add($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to entries list') . '</a></p>');
    }

    protected function fetchEntries(ArrayObject $from): void
    {
        $params = [];
        if (!empty($from['entries'])) {
            $entries = $from['entries'];

            foreach ($entries as $k => $v) {
                $entries[$k] = (int) $v;
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

        $posts = dotclear()->blog()->posts()->getPosts($params);
        while ($posts->fetch()) {
            $this->entries[$posts->post_id] = $posts->post_title;
        }
        $this->rs = $posts;
    }
}
