<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

// Dotclear\Process\Admin\Action\Action\DefaultCommentAction
use ArrayObject;
use Dotclear\App;
use Dotclear\Exception\AdminException;
use Dotclear\Process\Admin\Action\Action;

/**
 * Admin handler for default action on selected comments.
 *
 * @ingroup  Admin Comment Action
 */
abstract class DefaultCommentAction extends Action
{
    protected function loadCommentAction(Action $ap): void
    {
        if (App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Mark as pending') => 'pending',
                    __('Mark as junk')    => 'junk',
                ]],
                [$this, 'doChangeCommentStatus']
            );
        }

        if (App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete', ]],
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

        $status = match ($ap->getAction()) {
            'unpublish' => 0,
            'pending'   => -1,
            'junk'      => -2,
            default     => 1,
        };

        App::core()->blog()->comments()->updCommentsStatus($co_ids, $status);

        App::core()->notice()->addSuccessNotice(__('Selected comments have been successfully updated.'));
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
            // --BEHAVIOR-- adminBeforeCommentDelete
            App::core()->behavior()->call('adminBeforeCommentDelete', $comment_id);
        }

        // --BEHAVIOR-- adminBeforeCommentsDelete
        App::core()->behavior()->call('adminBeforeCommentsDelete', $co_ids);

        App::core()->blog()->comments()->delComments($co_ids);
        App::core()->notice()->addSuccessNotice(__('Selected comments have been successfully deleted.'));
        $ap->redirect(false);
    }
}
