<?php
/**
 * @class Dotclear\Plugin\Pages\Admin\PagesAction
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Admin;

use ArrayObject;

use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\Action\PostAction;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Html;

class PagesAction extends PostAction
{
    public function __construct(string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);
        $this->redirect_fields = [];
        $this->caller_title    = __('Pages');

        # Page setup
        $this
            ->setPageTitle(__('Blogs'))
            ->setPageType($this->in_plugin ? 'plugin' : null)
            ->setPageHead(dotclear()->resource()->Load('_posts_actions.js'))
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Pages')                                => $this->getRedirection(true),
                __('Pages actions')                        => ''
            ]);
    }

    public function error(\Exception $e): void
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

            $strReq = "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            'AND post_id ' . dotclear()->con()->in($post_id);

            #If user can only publish, we need to check the post's owner
            if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
                $strReq .= "AND user_id = '" . dotclear()->con()->escape(dotclear()->user()->userID()) . "' ";
            }

            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

            $cur->post_position = (int) $value - 1;
            $cur->post_upddt    = date('Y-m-d H:i:s');

            $cur->update($strReq);
            dotclear()->blog()->triggerBlog();
        }

        dotclear()->notice()->addSuccessNotice(__('Selected pages have been successfully reordered.'));
        $ap->redirect(false);
    }
}
