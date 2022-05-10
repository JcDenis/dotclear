<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

// Dotclear\Process\Admin\Action\Action\DefaultBlogAction
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\AdminException;
use Dotclear\Process\Admin\Action\Action;

/**
 * Admin handler for default action on selected blogs.
 *
 * @ingroup  Admin Blog Action
 */
abstract class DefaultBlogAction extends Action
{
    public function loadBlogsAction(Action $ap): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            return;
        }

        $ap->addAction(
            [__('Status') => [
                __('Set online')     => 'online',
                __('Set offline')    => 'offline',
                __('Set as removed') => 'remove',
            ]],
            [$this, 'doChangeBlogStatus']
        );
        $ap->addAction(
            [__('Delete') => [
                __('Delete') => 'delete', ]],
            [$this, 'doDeleteBlog']
        );
    }

    public function doChangeBlogStatus(Action $ap, array|ArrayObject $post): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            return;
        }

        $ids = $ap->getIDs();
        if (empty($ids)) {
            throw new AdminException(__('No blog selected'));
        }

        $status = match ($ap->getAction()) {
            'online'  => 1,
            'offline' => 0,
            'remove'  => -1,
            default   => 1,
        };

        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->from(App::core()->prefix . 'blog')
            ->set('blog_status = ' . $sql->quote($status))
            // ->set('blog_upddt = ' . $sql->quote(Clock::database()))
            ->where('blog_id' . $sql->in($ids))
            ->update()
        ;

        App::core()->notice()->addSuccessNotice(__('Selected blogs have been successfully updated.'));
        $ap->redirect(true);
    }

    public function doDeleteBlog(Action $ap, array|ArrayObject $post): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            return;
        }

        $ap_ids = $ap->getIDs();
        if (empty($ap_ids)) {
            throw new AdminException(__('No blog selected'));
        }

        if (!App::core()->user()->checkPassword($_POST['pwd'])) {
            throw new AdminException(__('Password verification failed'));
        }

        $ids = [];
        foreach ($ap_ids as $id) {
            if (App::core()->blog()->id == $id) {
                App::core()->notice()->addWarningNotice(__('The current blog cannot be deleted.'));
            } else {
                $ids[] = $id;
            }
        }

        if (!empty($ids)) {
            // --BEHAVIOR-- adminBeforeBlogsDelete
            App::core()->behavior()->call('adminBeforeBlogsDelete', $ids);

            foreach ($ids as $id) {
                App::core()->blogs()->delBlog($id);
            }

            App::core()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        '%d blog has been successfully deleted',
                        '%d blogs have been successfully deleted',
                        count($ids)
                    ),
                    count($ids)
                )
            );
        }
        $ap->redirect(false);
    }
}
