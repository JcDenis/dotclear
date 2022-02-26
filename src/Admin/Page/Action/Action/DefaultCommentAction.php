<?php
/**
 * @class Dotclear\Admin\Page\Action\Action\DefaultCommentAction
 * @brief Dotclear admin handler for action page on selected comments
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page\Action\Action;

use Dotclear\Admin\Page\Action\Action;
use Dotclear\Admin\Page\Page;
use Dotclear\Exception\AdminException;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class DefaultCommentAction
{
    public static function CommentAction(Action $ap): void
    {
        if (dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Mark as pending') => 'pending',
                    __('Mark as junk')    => 'junk'
                ]],
                [__CLASS__, 'doChangeCommentStatus']
            );
        }

        if (dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete']],
                [__CLASS__, 'doDeleteComment']
            );
        }
    }

    public static function doChangeCommentStatus(Action $ap, array $post): void
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

        dotclear()->blog()->comments()->updCommentsStatus($co_ids, $status);

        dotclear()->notice()->addSuccessNotice(__('Selected comments have been successfully updated.'));
        $ap->redirect(true);
    }

    public static function doDeleteComment(Action $ap, array $post): void
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
