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
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\Mapper\Integers;
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
        $ids = new Integers($ap->getIDs());
        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No comment selected'));
        }

        App::core()->blog()->comments()->updCommentsStatus(
            ids: $ids,
            status: App::core()->blog()->comments()->getCommentsStatusCode(name: $ap->getAction(), default: 1)
        );

        App::core()->notice()->addSuccessNotice(__('Selected comments have been successfully updated.'));
        $ap->redirect(true);
    }

    public function doDeleteComment(Action $ap, array|ArrayObject $post): void
    {
        $ids = new Integers($ap->getIDs());
        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No comment selected'));
        }

        // --BEHAVIOR-- adminBeforeCommentsDelete, Integers
        App::core()->behavior()->call('adminBeforeCommentsDelete', $ids);

        App::core()->blog()->comments()->delComments(ids: $ids);
        App::core()->notice()->addSuccessNotice(__('Selected comments have been successfully deleted.'));
        $ap->redirect(false);
    }
}
