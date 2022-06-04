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
            [__('Status') => App::core()->blogs()->status()->getActions()],
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

        App::core()->blogs()->updateBlogsStatus(
            ids: $ids,
            status: App::core()->blogs()->status()->getCode(id: $ap->getAction(), default: 1)
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
            App::core()->blogs()->deleteBlogs(ids: $ids);

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
