<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

// Dotclear\Process\Admin\Action\Action\DefaultPostAction
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtUser;
use Dotclear\Database\Param;
use Dotclear\Exception\AdminException;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\GPC\GPCGroup;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\ActionDescriptor;

/**
 * Admin handler for default action on selected entries.
 *
 * @ingroup  Admin Post Action
 */
abstract class DefaultPostAction extends Action
{
    protected function loadPostAction(Action $ap): void
    {
        if (App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            $this->addAction(new ActionDescriptor(
                group: __('Status'),
                actions: App::core()->blog()->posts()->status()->getActions(),
                callback: [$this, 'doChangePostStatus'],
            ));
        }
        $this->addAction(new ActionDescriptor(
            group: __('Mark'),
            actions: [
                __('Mark as selected')   => 'selected',
                __('Mark as unselected') => 'unselected',
            ],
            callback: [$this, 'doUpdateSelectedPost'],
        ));
        $this->addAction(new ActionDescriptor(
            group: __('Change'),
            actions: [__('Change category') => 'category'],
            callback: [$this, 'doChangePostCategory'],
        ));
        $this->addAction(new ActionDescriptor(
            group: __('Change'),
            actions: [__('Change language') => 'lang'],
            callback: [$this, 'doChangePostLang'],
        ));
        if (App::core()->user()->check('admin', App::core()->blog()->id)) {
            $this->addAction(new ActionDescriptor(
                group: __('Change'),
                actions: [__('Change author') => 'author'],
                callback: [$this, 'doChangePostAuthor'],
            ));
        }
        if (App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            $this->addAction(new ActionDescriptor(
                group: __('Delete'),
                actions: [__('Delete') => 'delete'],
                callback: [$this, 'doDeletePost'],
            ));
        }
    }

    protected function doChangePostStatus(Action $ap): void
    {
        $ids = $this->getCleanedIDs(ap: $ap);

        $status = App::core()->blog()->posts()->status()->getCode(id: $this->getAction(), default: 1);

        // Do not switch to scheduled already published entries
        if (-1 == $status) {
            $rs = $this->getRS();
            if ($rs->rows()) {
                while ($rs->fetch()) {
                    if (1 === $rs->fInt('post_status')) {
                        $ids->remove($rs->fInt('post_id'));
                    }
                }
            }
        }
        if (!$ids->count()) {
            throw new AdminException(__('Published entries cannot be set to scheduled'));
        }

        // Set status of remaining entries
        App::core()->blog()->posts()->updatePostsStatus(ids: $ids, status: $status);
        App::core()->notice()->addSuccessNotice(
            sprintf(
                __(
                    '%d entry has been successfully updated to status : "%s"',
                    '%d entries have been successfully updated to status : "%s"',
                    $ids->count()
                ),
                $ids->count(),
                App::core()->blog()->posts()->status()->getState(code: $status)
            )
        );
        $this->redirect(true);
    }

    protected function doUpdateSelectedPost(Action $ap): void
    {
        $ids = $this->getCleanedIDs(ap: $ap);

        $action = $this->getAction();
        App::core()->blog()->posts()->updatePostsSelected(ids: $ids, selected: 'selected' == $action);

        if ('selected' == $action) {
            App::core()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully marked as selected',
                        '%d entries have been successfully marked as selected',
                        $ids->count()
                    ),
                    $ids->count()
                )
            );
        } else {
            App::core()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully marked as unselected',
                        '%d entries have been successfully marked as unselected',
                        $ids->count()
                    ),
                    $ids->count()
                )
            );
        }
        $this->redirect(true);
    }

    protected function doDeletePost(Action $ap)
    {
        $ids = $this->getCleanedIDs(ap: $ap);

        App::core()->blog()->posts()->deletePosts(ids: $ids);
        App::core()->notice()->addSuccessNotice(
            sprintf(
                __(
                    '%d entry has been successfully deleted',
                    '%d entries have been successfully deleted',
                    $ids->count()
                ),
                $ids->count()
            )
        );

        $this->redirect(false);
    }

    protected function doChangePostCategory(Action $ap, GPCGroup $from)
    {
        if ($from->isset('new_cat_id')) {
            $ids = $this->getCleanedIDs(ap: $ap);
            $id  = $from->int('new_cat_id');

            // First create new category if required
            if (!empty($id) && App::core()->user()->check('categories', App::core()->blog()->id)) {
                // todo: check for duplicate category and throw clean Exception
                $cursor = App::core()->con()->openCursor(App::core()->prefix() . 'category');
                $cursor->setField('cat_title', $from->string('new_cat_title'));
                $cursor->setField('cat_url', '');

                $id = App::core()->blog()->categories()->createCategory(
                    cursor: $cursor,
                    parent: $from->int('new_cat_parent')
                );
            }

            App::core()->blog()->posts()->updatePostsCategory(ids: $ids, category: $id);

            $param = new Param();
            $param->set('cat_id', $id);

            $record = App::core()->blog()->categories()->getCategories(param: $param);
            App::core()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully moved to category "%s"',
                        '%d entries have been successfully moved to category "%s"',
                        $ids->count()
                    ),
                    $ids->count(),
                    Html::escapeHTML($record->f('cat_title'))
                )
            );

            $this->redirect(true);
        } else {
            $categories_combo = App::core()->combo()->getCategoriesCombo(
                App::core()->blog()->categories()->getCategories()
            );

            $this->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name)   => '',
                $this->getCallerTitle()                       => $this->getRedirection(true),
                __('Change category for this selection')      => '',
            ]);

            $this->setPageContent(
                '<form action="' . $this->getURI() . '" method="post">' .
                $this->getCheckboxes() .
                '<p><label for="new_cat_id" class="classic">' . __('Category:') . '</label> ' .
                Form::combo(['new_cat_id'], $categories_combo)
            );

            if (App::core()->user()->check('categories', App::core()->blog()->id)) {
                $this->setPageContent(
                    '</p><div>' .
                    '<p id="new_cat">' . __('Create a new category for the post(s)') . '</p>' .
                    '<p><label for="new_cat_title">' . __('Title:') . '</label> ' .
                    Form::field('new_cat_title', 30, 255) . '</p>' .
                    '<p><label for="new_cat_parent">' . __('Parent:') . '</label> ' .
                    Form::combo('new_cat_parent', $categories_combo) . '</p>' .
                    '</div><p>'
                );
            }

            $this->setPageContent(
                App::core()->nonce()->form() .
                $this->getHiddenFields() .
                Form::hidden(['action'], 'category') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }

    protected function doChangePostAuthor(Action $ap, GPCGroup $from)
    {
        if ($from->isset('new_auth_id')) {
            $ids = $this->getCleanedIDs(ap: $ap);

            App::core()->blog()->posts()->updatePostsAuthor(
                ids: $ids,
                author: $from->string('new_auth_id')
            );

            App::core()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully set to user "%s"',
                        '%d entries have been successfully set to user "%s"',
                        $ids->count()
                    ),
                    $ids->count(),
                    Html::escapeHTML($from->string('new_auth_id'))
                )
            );

            $this->redirect(true);
        } else {
            $usersList = [];
            if (App::core()->user()->check('admin', App::core()->blog()->id)) {
                $param = new Param();
                $param->set('limit', 100);
                $param->set('order', 'nb_post DESC');

                $rs       = App::core()->users()->getUsers(param: $param);
                $rsStatic = $rs->toStatic();
                $rsStatic->extend(new RsExtUser());
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort('user_id');
                while ($rsStatic->fetch()) {
                    $usersList[] = $rsStatic->f('user_id');
                }
            }

            $this->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                $this->getCallerTitle()                     => $this->getRedirection(true),
                __('Change author for this selection')      => '',
            ]);
            $this->setPageHead(
                App::core()->resource()->load('jquery/jquery.autocomplete.js') .
                App::core()->resource()->json('users_list', $usersList)
            );
            $this->setPageContent(
                '<form action="' . $this->getURI() . '" method="post">' .
                $this->getCheckboxes() .
                '<p><label for="new_auth_id" class="classic">' . __('New author (author ID):') . '</label> ' .
                Form::field('new_auth_id', 20, 255) .

                App::core()->nonce()->form() . $this->getHiddenFields() .
                Form::hidden(['action'], 'author') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }

    protected function doChangePostLang(Action $ap, GPCGroup $from)
    {
        if ($from->isset('new_lang')) {
            $ids = $this->getCleanedIDs(ap: $ap);

            App::core()->blog()->posts()->updatePostsLang(ids: $ids, lang: $from->string('new_lang'));

            App::core()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully set to language "%s"',
                        '%d entries have been successfully set to language "%s"',
                        $ids->count()
                    ),
                    $ids->count(),
                    Html::escapeHTML(L10n::getLanguageName($from->string('new_lang')))
                )
            );
            $this->redirect(true);
        } else {
            $param = new Param();
            $param->set('order', 'asc');
            $rs         = App::core()->blog()->posts()->getLangs(param: $param);
            $all_langs  = L10n::getISOcodes(false, true);
            $lang_combo = ['' => '', __('Most used') => [], __('Available') => L10n::getISOcodes(true, true)];
            while ($rs->fetch()) {
                if (isset($all_langs[$rs->f('post_lang')])) {
                    $lang_combo[__('Most used')][$all_langs[$rs->f('post_lang')]] = $rs->f('post_lang');
                    unset($lang_combo[__('Available')][$all_langs[$rs->f('post_lang')]]);
                } else {
                    $lang_combo[__('Most used')][$rs->f('post_lang')] = $rs->f('post_lang');
                }
            }
            unset($all_langs, $rs);

            $this->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name)   => '',
                $this->getCallerTitle()                       => $this->getRedirection(true),
                __('Change language for this selection')      => '',
            ]);

            $this->setPageContent(
                '<form action="' . $this->getURI() . '" method="post">' .
                $this->getCheckboxes() .
                '<p><label for="new_lang" class="classic">' . __('Entry language:') . '</label> ' .
                Form::combo('new_lang', $lang_combo) .

                App::core()->nonce()->form() . $this->getHiddenFields() .
                Form::hidden(['action'], 'lang') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }

    /**
     * Parse IDs into cleanded object.
     *
     * @param Action $ap The Action instance
     *
     * @return Integers The cleaned IDs
     */
    private function getCleanedIDs(Action $ap): Integers
    {
        $ids = new Integers($this->getIDs());
        if (!$ids->count()) {
            throw new MissingOrEmptyValue(__('No entry selected'));
        }

        return $ids;
    }
}
