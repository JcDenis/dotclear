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
use Dotclear\App;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\ActionDescriptor;

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
            $ap->addAction(new ActionDescriptor(
                group: __('Status'),
                actions: App::core()->blog()->comments()->status()->getActions(),
                callback: [$this, 'doChangeCommentStatus'],
            ));
        }

        if (App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            $ap->addAction(new ActionDescriptor(
                group: __('Delete'),
                actions: [__('Delete') => 'delete'],
                callback: [$this, 'doDeleteComment'],
            ));
        }
    }

    protected function doChangeCommentStatus(Action $ap): void
    {
        $ids = new Integers($ap->getIDs());
        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No comment selected'));
        }

        App::core()->blog()->comments()->updateCommentsStatus(
            ids: $ids,
            status: App::core()->blog()->comments()->status()->getCode(id: $ap->getAction(), default: 1)
        );

        App::core()->notice()->addSuccessNotice(__('Selected comments have been successfully updated.'));
        $ap->redirect(true);
    }

    protected function doDeleteComment(Action $ap): void
    {
        $ids = new Integers($ap->getIDs());
        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No comment selected'));
        }

        App::core()->blog()->comments()->deleteComments(ids: $ids);
        App::core()->notice()->addSuccessNotice(__('Selected comments have been successfully deleted.'));
        $ap->redirect(false);
    }
}
