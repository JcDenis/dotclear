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
use ArrayObject;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Action\Action;
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
            ->setPageHead(dotclear()->resource()->Load('_posts_actions.js'))
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Pages')                                => $this->getRedirection(true),
                __('Pages actions')                        => '',
            ])
        ;
    }

    public function error(Exception $e): void
    {
        dotclear()->error()->add($e->getMessage());
        $this->setPageContent('<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to pages list') . '</a></p>');
    }

    public function loadDefaults(): void
    {
        if (dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            $this->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Schedule')        => 'schedule',
                    __('Mark as pending') => 'pending',
                ]],
                [$this, 'doChangePostStatus']
            );
        }
        if (dotclear()->user()->check('admin', dotclear()->blog()->id)) {
            $this->addAction(
                [__('Change') => [
                    __('Change author') => 'author', ]],
                [$this, 'doChangePostAuthor']
            );
        }
        if (dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            $this->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete', ]],
                [$this, 'doDeletePost']
            );
        }

        $this->actions['reorder'] = [$this, 'doReorderPages'];

        dotclear()->behavior()->call('adminPagesActionsPage', $this);
    }

    public function getPagePrepend(): ?bool
    {
        // fake action for pages reordering
        if (!empty($this->from['reorder'])) {
            $this->from['action'] = 'reorder';
        }
        $this->from['post_type'] = 'page';

        return parent::getPagePrepend();
    }

    public function doReorderPages(Action $ap, array|ArrayObject $post): void
    {
        foreach ($post['order'] as $post_id => $value) {
            if (!dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
                throw new AdminException(__('You are not allowed to change this entry status'));
            }

            $sql = new UpdateStatement(__METHOD__);

            // If user can only publish, we need to check the post's owner
            if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
                $sql->and('user_id = ' . $sql->quote(dotclear()->user()->userID()));
            }

            $sql
                ->sets([
                    'post_position = ' . ((int) $value - 1),
                    'post_upddt = ' . $sql->quote(date('Y-m-d H:i:s')),
                ])
                ->where('blog_id = ' . $sql->quote(dotclear()->blog()->id))
                ->and('post_id' . $sql->in($post_id))
                ->from(dotclear()->prefix . 'post')
                ->update()
            ;

            dotclear()->blog()->triggerBlog();
        }

        dotclear()->notice()->addSuccessNotice(__('Selected pages have been successfully reordered.'));
        $ap->redirect(false);
    }
}
