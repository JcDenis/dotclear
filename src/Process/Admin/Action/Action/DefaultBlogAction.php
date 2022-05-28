<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

// Dotclear\Process\Admin\Action\Action\DefaultBlogAction
use Dotclear\App;
use Dotclear\Exception\InsufficientPermissions;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Helper\GPC\GPCGroup;
use Dotclear\Helper\Mapper\Strings;

/**
 * Admin handler for default action on selected blogs.
 *
 * @ingroup  Admin Blog Action
 */
abstract class DefaultBlogAction extends Action
{
    protected function loadBlogsAction(Action $ap): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            return;
        }

        $ap->addAction(
            [__('Status') => [
                __('Set online')     => 'online',
                __('Set offline')    => 'offline',
                __('Set as removed') => 'remove',
            ]],
            [$this, 'doChangeBlogStatus']
        );
        $ap->addAction(
            [__('Delete') => [
                __('Delete') => 'delete', ]],
            [$this, 'doDeleteBlog']
        );
    }

    protected function doChangeBlogStatus(Action $ap): void
    {
        $ids = new Strings($ap->getIDs());
        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No blog selected'));
        }

        // --BEHAVIOR-- adminBeforeBlogsStatusUpdate, Strings
        App::core()->behavior()->call('adminBeforeBlogsStatusUpdate', $ids);

        App::core()->blogs()->updBlogsStatus(
            ids: $ids,
            status: App::core()->blogs()->getBlogsStatusCode(name: $ap->getAction(), default: 1)
        );

        App::core()->notice()->addSuccessNotice(__('Selected blogs have been successfully updated.'));
        $ap->redirect(true);
    }

    protected function doDeleteBlog(Action $ap, GPCgroup $from): void
    {
        $ids = new Strings($ap->getIDs());
        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No blog selected'));
        }

        if (!App::core()->user()->checkPassword($from->string('pwd'))) {
            throw new InsufficientPermissions(__('Password verification failed'));
        }

        if ($ids->exists(App::core()->blog()->id)) {
            App::core()->notice()->addWarningNotice(__('The current blog cannot be deleted.'));
            $ids->remove(App::core()->blog()->id);
        }

        if ($ids->count()) {
            // --BEHAVIOR-- adminBeforeBlogsDelete, Strings
            App::core()->behavior()->call('adminBeforeBlogsDelete', $ids);

            App::core()->blogs()->delBlogs(ids: $ids);

            App::core()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        '%d blog has been successfully deleted',
                        '%d blogs have been successfully deleted',
                        $ids->count()
                    ),
                    $ids->count()
                )
            );
        }
        $ap->redirect(false);
    }
}
