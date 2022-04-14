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
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\AdminException;
use Dotclear\Process\Admin\Action\Action;

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
            ->from(dotclear()->prefix . 'blog')
            ->set('blog_status = ' . $sql->quote($status))
            //->set('blog_upddt = ' . $sql->quote(date('Y-m-d H:i:s')))
            ->where('blog_id' . $sql->in($ids))
            ->update();

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
