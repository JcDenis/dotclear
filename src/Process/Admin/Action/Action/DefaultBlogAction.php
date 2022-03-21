<?php
/**
 * @class Dotclear\Process\Admin\Action\Action\DefaultBlogAction
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

use Dotclear\Process\Admin\Action\Action;
use Dotclear\Exception\AdminException;

abstract class DefaultBlogAction extends Action
{
    public function loadBlogsAction(Action $ap): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            return;
        }

        $ap->addAction(
            [__('Status') => [
                __('Set online')     => 'online',
                __('Set offline')    => 'offline',
                __('Set as removed') => 'remove'
            ]],
            [$this, 'doChangeBlogStatus']
        );
        $ap->addAction(
            [__('Delete') => [
                __('Delete') => 'delete']],
            [$this, 'doDeleteBlog']
        );
    }

    public function doChangeBlogStatus(Action $ap, array|ArrayObject $post): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            return;
        }

        $action = $ap->getAction();
        $ids    = $ap->getIDs();
        if (empty($ids)) {
            throw new AdminException(__('No blog selected'));
        }
        switch ($action) {
            case 'online':
                $status = 1;

                break;
            case 'offline':
                $status = 0;

                break;
            case 'remove':
                $status = -1;

                break;
            default:
                $status = 1;

                break;
        }

        $cur              = dotclear()->con()->openCursor(dotclear()->prefix . 'blog');
        $cur->blog_status = $status;
        //$cur->blog_upddt = date('Y-m-d H:i:s');
        $cur->update('WHERE blog_id ' . dotclear()->con()->in($ids));

        dotclear()->notice()->addSuccessNotice(__('Selected blogs have been successfully updated.'));
        $ap->redirect(true);
    }

    public function doDeleteBlog(Action $ap, array|ArrayObject $post): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            return;
        }

        $ap_ids = $ap->getIDs();
        if (empty($ap_ids)) {
            throw new AdminException(__('No blog selected'));
        }

        if (!dotclear()->user()->checkPassword($_POST['pwd'])) {
            throw new AdminException(__('Password verification failed'));
        }

        $ids = [];
        foreach ($ap_ids as $id) {
            if ($id == dotclear()->blog()->id) {
                dotclear()->notice()->addWarningNotice(__('The current blog cannot be deleted.'));
            } else {
                $ids[] = $id;
            }
        }

        if (!empty($ids)) {
            # --BEHAVIOR-- adminBeforeBlogsDelete
            dotclear()->behavior()->call('adminBeforeBlogsDelete', $ids);

            foreach ($ids as $id) {
                dotclear()->blogs()->delBlog($id);
            }

            dotclear()->notice()->addSuccessNotice(sprintf(
                __(
                    '%d blog has been successfully deleted',
                    '%d blogs have been successfully deleted',
                    count($ids)
                ),
                count($ids))
            );
        }
        $ap->redirect(false);
    }
}
