<?php
/**
 * @class Dotclear\Admin\Action\DefaultBlogAction
 * @brief Dotclear admin handler for action page on selected entries
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Action;

use function Dotclear\core;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Admin\Action;
use Dotclear\Admin\Notices;

class DefaultBlogAction
{
    public static function BlogsAction(Action $ap): void
    {
        if (!core()->auth->isSuperAdmin()) {
            return;
        }

        $ap->addAction(
            [__('Status') => [
                __('Set online')     => 'online',
                __('Set offline')    => 'offline',
                __('Set as removed') => 'remove'
            ]],
            [__NAMESPACE__ . '\\DefaultBlogAction', 'doChangeBlogStatus']
        );
        $ap->addAction(
            [__('Delete') => [
                __('Delete') => 'delete']],
            [__NAMESPACE__ . '\\DefaultBlogAction', 'doDeleteBlog']
        );
    }

    public static function doChangeBlogStatus(Action $ap, $post): void
    {
        if (!core()->auth->isSuperAdmin()) {
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

        $cur              = core()->con->openCursor(core()->prefix . 'blog');
        $cur->blog_status = $status;
        //$cur->blog_upddt = date('Y-m-d H:i:s');
        $cur->update('WHERE blog_id ' . core()->con->in($ids));

        core()->notices->addSuccessNotice(__('Selected blogs have been successfully updated.'));
        $ap->redirect(true);
    }

    public static function doDeleteBlog(Action $ap, $post): void
    {
        if (!core()->auth->isSuperAdmin()) {
            return;
        }

        $ap_ids = $ap->getIDs();
        if (empty($ap_ids)) {
            throw new AdminException(__('No blog selected'));
        }

        if (!core()->auth->checkPassword($_POST['pwd'])) {
            throw new AdminException(__('Password verification failed'));
        }

        $ids = [];
        foreach ($ap_ids as $id) {
            if ($id == core()->blog->id) {
                core()->notices->addWarningNotice(__('The current blog cannot be deleted.'));
            } else {
                $ids[] = $id;
            }
        }

        if (!empty($ids)) {
            # --BEHAVIOR-- adminBeforeBlogsDelete
            core()->behaviors->call('adminBeforeBlogsDelete', $ids);

            foreach ($ids as $id) {
                core()->delBlog($id);
            }

            core()->notices->addSuccessNotice(sprintf(
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
