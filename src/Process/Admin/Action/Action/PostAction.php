<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

// Dotclear\Process\Admin\Action\Action\PostAction
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Admin handler for action on selected entries.
 *
 * @ingroup  Admin Post Action
 */
class PostAction extends DefaultPostAction
{
    public function __construct(string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        // Action setup
        $this->redirect_fields = ['user_id', 'cat_id', 'status',
            'selected', 'attachment', 'month', 'lang', 'sortby', 'order', 'page', 'nb', ];
        $this->loadDefaults();

        // Page setup
        $this->setPageTitle(__('Posts'));
        $this->setPageType($this->in_plugin ? 'plugin' : 'full');
        $this->setPageHead(App::core()->resource()->load('_posts_actions.js'));
        $this->setPageBreadcrumb([
            Html::escapeHTML(App::core()->blog()->name) => '',
            $this->getCallerTitle()                     => $this->getRedirection(true),
            __('Posts actions')                         => '',
        ]);
    }

    protected function loadDefaults(): void
    {
        $this->loadPostAction($this);
        App::core()->behavior()->call('adminPostsActionsPage', $this);
    }

    public function error(Exception $e)
    {
        App::core()->error()->add($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to entries list') . '</a></p>');
    }

    protected function fetchEntries(ArrayObject $from): void
    {
        $param = new Param();
        if (!empty($from['entries'])) {
            $entries = $from['entries'];

            foreach ($entries as $k => $v) {
                $entries[$k] = (int) $v;
            }

            $param->push('sql', 'AND P.post_id IN(' . implode(',', $entries) . ') ');
        } else {
            $param->push('sql', 'AND 1=0 ');
        }

        if (!isset($from['full_content']) || empty($from['full_content'])) {
            $param->set('no_content', true);
        }

        if (isset($from['post_type'])) {
            $param->set('post_type', $from['post_type']);
        }

        $posts = App::core()->blog()->posts()->getPosts(param: $param);
        while ($posts->fetch()) {
            $this->entries[$posts->fInt('post_id')] = $posts->f('post_title');
        }
        $this->rs = $posts;
    }
}
