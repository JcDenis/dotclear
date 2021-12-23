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

use Dotclear\Core\Core;

use Dotclear\Admin\Action;
use Dotclear\Admin\Page;

use Dotclear\Admin\Action\CommentAction;

class DefaultCommentAction
{
    public static function CommentAction(Core $core, CommentAction $ap)
    {
        if ($core->auth->check('publish,contentadmin', $core->blog->id)) {
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

        if ($core->auth->check('delete,contentadmin', $core->blog->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete']],
                [__NAMESPACE__ . '\\DefaultCommentAction', 'doDeleteComment']
            );
        }
/*
//!
        $ip_filter_active = true;
        if ($core->blog->settings->antispam->antispam_filters !== null) {
            $filters_opt = $core->blog->settings->antispam->antispam_filters;
            if (is_array($filters_opt)) {
                $ip_filter_active = isset($filters_opt['dcFilterIP']) && is_array($filters_opt['dcFilterIP']) && $filters_opt['dcFilterIP'][0] == 1;
            }
        }

        if ($ip_filter_active) {
            $blocklist_actions = [__('Blocklist IP') => 'blocklist'];
            if ($core->auth->isSuperAdmin()) {
                $blocklist_actions[__('Blocklist IP (global)')] = 'blocklist_global';
            }

            $ap->addAction(
                [__('IP address') => $blocklist_actions],
                [__NAMESPACE__ . '\\DefaultCommentAction', 'doBlocklistIP']
            );
        }
*/
    }

    public static function doChangeCommentStatus(Core $core, CommentAction $ap, $post)
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

        $core->blog->updCommentsStatus($co_ids, $status);

        Page::addSuccessNotice(__('Selected comments have been successfully updated.'));
        $ap->redirect(true);
    }

    public static function doDeleteComment(Core $core, CommentAction $ap, $post)
    {
        $co_ids = $ap->getIDs();
        if (empty($co_ids)) {
            throw new AdminException(__('No comment selected'));
        }
        // Backward compatibility
        foreach ($co_ids as $comment_id) {
            # --BEHAVIOR-- adminBeforeCommentDelete
            $core->callBehavior('adminBeforeCommentDelete', $comment_id);
        }

        # --BEHAVIOR-- adminBeforeCommentsDelete
        $core->callBehavior('adminBeforeCommentsDelete', $co_ids);

        $core->blog->delComments($co_ids);
        Page::addSuccessNotice(__('Selected comments have been successfully deleted.'));
        $ap->redirect(false);
    }

    public static function doBlocklistIP(Core $core, CommentAction $ap, $post)
    {
        $action = $ap->getAction();
        $co_ids = $ap->getIDs();
        if (empty($co_ids)) {
            throw new AdminException(__('No comment selected'));
        }

        $global = !empty($action) && $action == 'blocklist_global' && $core->auth->isSuperAdmin();
//!
        $ip_filter = new FilterIP($core);
        $rs        = $ap->getRS();
        while ($rs->fetch()) {
            $ip_filter->addIP('black', $rs->comment_ip, $global);
        }

        Page::addSuccessNotice(__('IP addresses for selected comments have been blocklisted.'));
        $ap->redirect(true);
    }
}
