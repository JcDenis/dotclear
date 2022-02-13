<?php
/**
 * @class Dotclear\Admin\Action\CommentAction
 * @brief Dotclear admin handler for action page on selected comments
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Action;

use Dotclear\Exception\AdminException;

use Dotclear\Admin\Action;
use Dotclear\Admin\Notices;
use Dotclear\Admin\Page;

class DefaultCommentAction
{
    public static function CommentAction(Action $ap)
    {
        if (dotclear()->auth->check('publish,contentadmin', dotclear()->blog->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Mark as pending') => 'pending',
                    __('Mark as junk')    => 'junk'
                ]],
                [__NAMESPACE__ . '\\DefaultCommentAction', 'doChangeCommentStatus']
            );
        }

        if (dotclear()->auth->check('delete,contentadmin', dotclear()->blog->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete']],
                [__NAMESPACE__ . '\\DefaultCommentAction', 'doDeleteComment']
            );
        }
    }

    public static function doChangeCommentStatus(Action $ap, $post)
    {
        $action = $ap->getAction();
        $co_ids = $ap->getIDs();
        if (empty($co_ids)) {
            throw new AdminException(__('No comment selected'));
        }
        switch ($action) {
            case 'unpublish':
                $status = 0;

                break;
            case 'pending':
                $status = -1;

                break;
            case 'junk':
                $status = -2;

                break;
            default:
                $status = 1;

                break;
        }

        dotclear()->blog->updCommentsStatus($co_ids, $status);

        dotclear()->notices->addSuccessNotice(__('Selected comments have been successfully updated.'));
        $ap->redirect(true);
    }

    public static function doDeleteComment(Action $ap, $post)
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

        dotclear()->blog->delComments($co_ids);
        dotclear()->notices->addSuccessNotice(__('Selected comments have been successfully deleted.'));
        $ap->redirect(false);
    }
}
