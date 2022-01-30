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

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Admin\Action;
use Dotclear\Admin\Notices;
use Dotclear\Admin\Page;

class DefaultCommentAction
{
    public static function CommentAction(Action $ap)
    {
        if (dcCore()->auth->check('publish,contentadmin', dcCore()->blog->id)) {
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

        if (dcCore()->auth->check('delete,contentadmin', dcCore()->blog->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete']],
                [__NAMESPACE__ . '\\DefaultCommentAction', 'doDeleteComment']
            );
        }
/*
//!
        $ip_filter_active = true;
        if (dcCore()->blog->settings->antispam->antispam_filters !== null) {
            $filters_opt = dcCore()->blog->settings->antispam->antispam_filters;
            if (is_array($filters_opt)) {
                $ip_filter_active = isset($filters_opt['dcFilterIP']) && is_array($filters_opt['dcFilterIP']) && $filters_opt['dcFilterIP'][0] == 1;
            }
        }

        if ($ip_filter_active) {
            $blocklist_actions = [__('Blocklist IP') => 'blocklist'];
            if (dcCore()->auth->isSuperAdmin()) {
                $blocklist_actions[__('Blocklist IP (global)')] = 'blocklist_global';
            }

            $ap->addAction(
                [__('IP address') => $blocklist_actions],
                [__NAMESPACE__ . '\\DefaultCommentAction', 'doBlocklistIP']
            );
        }
*/
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

        dcCore()->blog->updCommentsStatus($co_ids, $status);

        dcCore()->notices->addSuccessNotice(__('Selected comments have been successfully updated.'));
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
            dcCore()->behaviors->call('adminBeforeCommentDelete', $comment_id);
        }

        # --BEHAVIOR-- adminBeforeCommentsDelete
        dcCore()->behaviors->call('adminBeforeCommentsDelete', $co_ids);

        dcCore()->blog->delComments($co_ids);
        dcCore()->notices->addSuccessNotice(__('Selected comments have been successfully deleted.'));
        $ap->redirect(false);
    }

    public static function doBlocklistIP(Action $ap, $post)
    {
        $action = $ap->getAction();
        $co_ids = $ap->getIDs();
        if (empty($co_ids)) {
            throw new AdminException(__('No comment selected'));
        }

        $global = !empty($action) && $action == 'blocklist_global' && dcCore()->auth->isSuperAdmin();
//!
        $ip_filter = new FilterIP();
        $rs        = $ap->getRS();
        while ($rs->fetch()) {
            $ip_filter->addIP('black', $rs->comment_ip, $global);
        }

        dcCore()->notices->addSuccessNotice(__('IP addresses for selected comments have been blocklisted.'));
        $ap->redirect(true);
    }
}