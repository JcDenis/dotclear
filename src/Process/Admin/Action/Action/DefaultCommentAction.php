<?php
/**
 * @class Dotclear\Process\Admin\Action\Action\DefaultCommentAction
 * @brief Dotclear admin handler for action page on selected comments
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
use Dotclear\Exception\AdminException;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Page\Page;

abstract class DefaultCommentAction extends Action
{
    protected function loadCommentAction(Action $ap): void
    {
        if (dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Mark as pending') => 'pending',
                    __('Mark as junk')    => 'junk'
                ]],
                [$this, 'doChangeCommentStatus']
            );
        }

        if (dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete']],
                [$this, 'doDeleteComment']
            );
        }
    }

    public function doChangeCommentStatus(Action $ap, array|ArrayObject $post): void
    {
        $co_ids = $ap->getIDs();
        if (empty($co_ids)) {
            throw new AdminException(__('No comment selected'));
        }

        $status  = match($ap->getAction()) {
            'unpublish' => 0,
            'pending'   => -1,
            'junk'      => -2,
            default     => 1,
        };

        dotclear()->blog()->comments()->updCommentsStatus($co_ids, $status);

        dotclear()->notice()->addSuccessNotice(__('Selected comments have been successfully updated.'));
        $ap->redirect(true);
    }

    public function doDeleteComment(Action $ap, array|ArrayObject $post): void
    {
        $co_ids = $ap->getIDs();
        if (empty($co_ids)) {
            throw new AdminException(__('No comment selected'));
        }
        // Backward compatibility
        foreach ($co_ids as $comment_id) {
            # --BEHAVIOR-- adminBeforeCommentDelete
            dotclear()->behavior()->call('adminBeforeCommentDelete', $comment_id);
        }

        # --BEHAVIOR-- adminBeforeCommentsDelete
        dotclear()->behavior()->call('adminBeforeCommentsDelete', $co_ids);

        dotclear()->blog()->comments()->delComments($co_ids);
        dotclear()->notice()->addSuccessNotice(__('Selected comments have been successfully deleted.'));
        $ap->redirect(false);
    }
}
