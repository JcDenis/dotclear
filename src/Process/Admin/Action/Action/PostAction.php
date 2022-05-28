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
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\GPC\GPCGroup;
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

    protected function error(Exception $e)
    {
        App::core()->error()->add($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to entries list') . '</a></p>');
    }

    protected function fetchEntries(GPCgroup $from): void
    {
        $param = new Param();
        if (!$from->empty('entries')) {
            $entries = [];
            foreach ($from->array('entries') as $v) {
                $entries[] = (int) $v;
            }

            $param->push('sql', 'AND P.post_id IN(' . implode(',', $entries) . ') ');
        } else {
            $param->push('sql', 'AND 1=0 ');
        }

        if ($from->empty('full_content')) {
            $param->set('no_content', true);
        }

        if ($from->isset('post_type')) {
            $param->set('post_type', $from->string('post_type'));
        }

        $record = App::core()->blog()->posts()->getPosts(param: $param);
        while ($record->fetch()) {
            $this->entries[$record->fInt('post_id')] = $record->f('post_title');
        }
        $this->rs = $record;
    }
}
