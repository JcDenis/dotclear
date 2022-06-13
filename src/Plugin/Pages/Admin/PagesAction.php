<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Admin;

// Dotclear\Plugin\Pages\Admin\PagesAction
use Dotclear\App;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPCGroup;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\ActionDescriptor;
use Dotclear\Process\Admin\Action\Action\PostAction;
use Exception;

/**
 * Admin action for plugin Pages.
 *
 * @ingroup  Plugin Pages Action
 */
class PagesAction extends PostAction
{
    public function __construct(string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);
        $this->redirect_fields = [];
        $this->caller_title    = __('Pages');

        // Page setup
        $this
            ->setPageTitle(__('Blogs'))
            ->setPageType($this->in_plugin ? 'plugin' : 'full')
            ->setPageHead(App::core()->resource()->Load('_posts_actions.js'))
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Pages')                                 => $this->getRedirection(true),
                __('Pages actions')                         => '',
            ])
        ;
    }

    public function error(Exception $e): void
    {
        App::core()->error()->add($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to pages list') . '</a></p>');
    }

    public function loadDefaults(): void
    {
        if (App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            $this->addAction(new ActionDescriptor(
                group: __('Status'),
                actions: [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Schedule')        => 'schedule',
                    __('Mark as pending') => 'pending',
                ],
                callback: [$this, 'doChangePostStatus'],
            ));
        }
        if (App::core()->user()->check('admin', App::core()->blog()->id)) {
            $this->addAction(new ActionDescriptor(
                group: __('Change'),
                actions: [__('Change author') => 'author'],
                callback: [$this, 'doChangePostAuthor'],
            ));
        }
        if (App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            $this->addAction(new ActionDescriptor(
                group: __('Delete'),
                actions: [__('Delete') => 'delete'],
                callback: [$this, 'doDeletePost'],
            ));
        }

        $this->addAction(new ActionDescriptor(
            hidden: true,
            actions: ['reorder' => 'reorder'],
            callback: [$this, 'doReorderPages'],
        ));

        App::core()->behavior()->call('adminPagesActionsPage', $this);
    }

    public function getPagePrepend(): ?bool
    {
        // fake action for pages reordering
        $from = $this->from->dump();

        if (!$this->from->empty('reorder')) {
            $from['action'] = 'reorder';
        }
        $from['post_type'] = 'page';

        $this->from = new GPCGroup($from);

        return parent::getPagePrepend();
    }

    protected function doReorderPages(Action $ap, GPCGroup $from): void
    {
        foreach ($from->array('order') as $post_id => $value) {
            if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
                throw new AdminException(__('You are not allowed to change this entry status'));
            }

            $sql = new UpdateStatement();

            // If user can only publish, we need to check the post's owner
            if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
                $sql->and('user_id = ' . $sql->quote(App::core()->user()->userID()));
            }

            $sql->sets([
                'post_position = ' . ((int) $value - 1),
                'post_upddt = ' . $sql->quote(Clock::database()),
            ]);
            $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
            $sql->and('post_id' . $sql->in($post_id));
            $sql->from(App::core()->prefix() . 'post');

            $sql->update();

            App::core()->blog()->triggerBlog();
        }

        App::core()->notice()->addSuccessNotice(__('Selected pages have been successfully reordered.'));
        $ap->redirect(false);
    }
}
