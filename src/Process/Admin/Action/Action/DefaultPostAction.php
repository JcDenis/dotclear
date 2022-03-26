<?php
/**
 * @class Dotclear\Process\Admin\Action\Action\DefaultPostAction
 * @brief Dotclear admin handler for action page on selected entries
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action\Action;

use ArrayObject;

use Dotclear\Core\RsExt\RsExtUser;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;

abstract class DefaultPostAction extends Action
{
    public function loadPostAction(Action $ap): void
    {
        if (dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Schedule')        => 'schedule',
                    __('Mark as pending') => 'pending'
                ]],
                [$this, 'doChangePostStatus']
            );
        }
        $ap->addAction(
            [__('Mark') => [
                __('Mark as selected')   => 'selected',
                __('Mark as unselected') => 'unselected'
            ]],
            [$this, 'doUpdateSelectedPost']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change category') => 'category'
            ]],
            [$this, 'doChangePostCategory']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change language') => 'lang'
            ]],
            [$this, 'doChangePostLang']
        );
        if (dotclear()->user()->check('admin', dotclear()->blog()->id)) {
            $ap->addAction(
                [__('Change') => [
                    __('Change author') => 'author']],
                [$this, 'doChangePostAuthor']
            );
        }
        if (dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete']],
                [$this, 'doDeletePost']
            );
        }
    }

    public function doChangePostStatus(Action $ap, array|ArrayObject $post): void
    {
        $status = match ($ap->getAction()) {
            'unpublish' => 0,
            'schedule'  => -1,
            'pending'   => -2,
            default     => 1,
        };
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new AdminException(__('No entry selected'));
        }
        // Do not switch to scheduled already published entries
        if ($status == -1) {
            $rs           = $ap->getRS();
            $excluded_ids = [];
            if ($rs->rows()) {
                while ($rs->fetch()) {
                    if ((int) $rs->post_status === 1) {
                        $excluded_ids[] = (int) $rs->post_id;
                    }
                }
            }
            if (count($excluded_ids)) {
                $posts_ids = array_diff($posts_ids, $excluded_ids);
            }
        }
        if (count($posts_ids) === 0) {
            throw new AdminException(__('Published entries cannot be set to scheduled'));
        }
        // Set status of remaining entries
        dotclear()->blog()->posts()->updPostsStatus($posts_ids, $status);
        dotclear()->notice()->addSuccessNotice(sprintf(
            __(
                '%d entry has been successfully updated to status : "%s"',
                '%d entries have been successfully updated to status : "%s"',
                count($posts_ids)
            ),
            count($posts_ids),
            dotclear()->blog()->getPostStatus($status))
        );
        $ap->redirect(true);
    }

    public function doUpdateSelectedPost(Action $ap, array|ArrayObject $post): void
    {
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new AdminException(__('No entry selected'));
        }
        $action = $ap->getAction();
        dotclear()->blog()->posts()->updPostsSelected($posts_ids, $action == 'selected');
        if ($action == 'selected') {
            dotclear()->notice()->addSuccessNotice(sprintf(
                __(
                    '%d entry has been successfully marked as selected',
                    '%d entries have been successfully marked as selected',
                    count($posts_ids)
                ),
                count($posts_ids))
            );
        } else {
            dotclear()->notice()->addSuccessNotice(sprintf(
                __(
                    '%d entry has been successfully marked as unselected',
                    '%d entries have been successfully marked as unselected',
                    count($posts_ids)
                ),
                count($posts_ids))
            );
        }
        $ap->redirect(true);
    }

    public function doDeletePost(Action $ap, array|ArrayObject $post)
    {
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new AdminException(__('No entry selected'));
        }
        // Backward compatibility
        foreach ($posts_ids as $post_id) {
            # --BEHAVIOR-- adminBeforePostDelete
            dotclear()->behavior()->call('adminBeforePostDelete', (int) $post_id);
        }

        # --BEHAVIOR-- adminBeforePostsDelete
        dotclear()->behavior()->call('adminBeforePostsDelete', $posts_ids);

        dotclear()->blog()->posts()->delPosts($posts_ids);
        dotclear()->notice()->addSuccessNotice(sprintf(
            __(
                '%d entry has been successfully deleted',
                '%d entries have been successfully deleted',
                count($posts_ids)
            ),
            count($posts_ids))
        );

        $ap->redirect(false);
    }

    public function doChangePostCategory(Action $ap, array|ArrayObject $post)
    {
        if (isset($post['new_cat_id'])) {
            $posts_ids = $ap->getIDs();
            if (empty($posts_ids)) {
                throw new AdminException(__('No entry selected'));
            }
            $new_cat_id = (int) $post['new_cat_id'];
            if (!empty($post['new_cat_title']) && dotclear()->user()->check('categories', dotclear()->blog()->id)) {
                //to do: check for duplicate category and throw clean Exception
                $cur_cat            = dotclear()->con()->openCursor(dotclear()->prefix . 'category');
                $cur_cat->cat_title = $post['new_cat_title'];
                $cur_cat->cat_url   = '';
                $title              = $cur_cat->cat_title;

                $parent_cat = !empty($post['new_cat_parent']) ? $post['new_cat_parent'] : '';

                # --BEHAVIOR-- adminBeforeCategoryCreate
                dotclear()->behavior()->call('adminBeforeCategoryCreate', $cur_cat);

                $new_cat_id = dotclear()->blog()->categories()->addCategory($cur_cat, (int) $parent_cat);

                # --BEHAVIOR-- adminAfterCategoryCreate
                dotclear()->behavior()->call('adminAfterCategoryCreate', $cur_cat, $new_cat_id);
            }

            dotclear()->blog()->posts()->updPostsCategory($posts_ids, $new_cat_id);
            $title = dotclear()->blog()->categories()->getCategory($new_cat_id);
            dotclear()->notice()->addSuccessNotice(sprintf(
                __(
                    '%d entry has been successfully moved to category "%s"',
                    '%d entries have been successfully moved to category "%s"',
                    count($posts_ids)
                ),
                count($posts_ids),
                Html::escapeHTML($title->cat_title))
            );

            $ap->redirect(true);
        } else {
            $categories_combo = dotclear()->combo()->getCategoriesCombo(
                dotclear()->blog()->categories()->getCategories()
            );

            $ap->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name)      => '',
                $ap->getCallerTitle()                    => $ap->getRedirection(true),
                __('Change category for this selection') => ''
            ]);

            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<p><label for="new_cat_id" class="classic">' . __('Category:') . '</label> ' .
                Form::combo(['new_cat_id'], $categories_combo)
            );

            if (dotclear()->user()->check('categories', dotclear()->blog()->id)) {

                $ap->setPageContent(
                    '</p><div>' .
                    '<p id="new_cat">' . __('Create a new category for the post(s)') . '</p>' .
                    '<p><label for="new_cat_title">' . __('Title:') . '</label> ' .
                    Form::field('new_cat_title', 30, 255) . '</p>' .
                    '<p><label for="new_cat_parent">' . __('Parent:') . '</label> ' .
                    Form::combo('new_cat_parent', $categories_combo) . '</p>' .
                    '</div><p>'
                );
            }

            $ap->setPageContent(
                dotclear()->nonce()->form() .
                $ap->getHiddenFields() .
                Form::hidden(['action'], 'category') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }

    public function doChangePostAuthor(Action $ap, array|ArrayObject $post)
    {
        if (isset($post['new_auth_id']) && dotclear()->user()->check('admin', dotclear()->blog()->id)) {
            $new_user_id = $post['new_auth_id'];
            $posts_ids   = $ap->getIDs();
            if (empty($posts_ids)) {
                throw new AdminException(__('No entry selected'));
            }
            if (dotclear()->users()->getUser($new_user_id)->isEmpty()) {
                throw new AdminException(__('This user does not exist'));
            }

            $cur          = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
            $cur->user_id = $new_user_id;
            $cur->update('WHERE post_id ' . dotclear()->con()->in($posts_ids));
            dotclear()->notice()->addSuccessNotice(sprintf(
                __(
                    '%d entry has been successfully set to user "%s"',
                    '%d entries have been successfully set to user "%s"',
                    count($posts_ids)
                ),
                count($posts_ids),
                Html::escapeHTML($new_user_id))
            );

            $ap->redirect(true);
        } else {
            $usersList = [];
            if (dotclear()->user()->check('admin', dotclear()->blog()->id)) {
                $params = [
                    'limit' => 100,
                    'order' => 'nb_post DESC'
                ];
                $rs       = dotclear()->users()->getUsers($params);
                $rsStatic = $rs->toStatic();
                $rsStatic->extend(new RsExtUser());
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort('user_id');
                while ($rsStatic->fetch()) {
                    $usersList[] = $rsStatic->user_id;
                }
            }

            $ap->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name)    => '',
                $ap->getCallerTitle()                  => $ap->getRedirection(true),
                __('Change author for this selection') => ''
            ]);
            $ap->setPageHead(
                dotclear()->resource()->load('jquery/jquery.autocomplete.js') .
                dotclear()->resource()->json('users_list', $usersList)
            );
            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<p><label for="new_auth_id" class="classic">' . __('New author (author ID):') . '</label> ' .
                Form::field('new_auth_id', 20, 255) .

                dotclear()->nonce()->form() . $ap->getHiddenFields() .
                Form::hidden(['action'], 'author') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }

    public function doChangePostLang(Action $ap, array|ArrayObject $post)
    {
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new AdminException(__('No entry selected'));
        }
        if (isset($post['new_lang'])) {
            $new_lang       = $post['new_lang'];
            $cur            = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
            $cur->post_lang = $new_lang;
            $cur->update('WHERE post_id ' . dotclear()->con()->in($posts_ids));
            dotclear()->notice()->addSuccessNotice(sprintf(
                __(
                    '%d entry has been successfully set to language "%s"',
                    '%d entries have been successfully set to language "%s"',
                    count($posts_ids)
                ),
                count($posts_ids),
                Html::escapeHTML(L10n::getLanguageName($new_lang)))
            );
            $ap->redirect(true);
        } else {
            $rs         = dotclear()->blog()->posts()->getLangs(['order' => 'asc']);
            $all_langs  = L10n::getISOcodes(false, true);
            $lang_combo = ['' => '', __('Most used') => [], __('Available') => L10n::getISOcodes(true, true)];
            while ($rs->fetch()) {
                if (isset($all_langs[$rs->post_lang])) {
                    $lang_combo[__('Most used')][$all_langs[$rs->post_lang]] = $rs->post_lang;
                    unset($lang_combo[__('Available')][$all_langs[$rs->post_lang]]);
                } else {
                    $lang_combo[__('Most used')][$rs->post_lang] = $rs->post_lang;
                }
            }
            unset($all_langs, $rs);

            $ap->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name)      => '',
                $ap->getCallerTitle()                    => $ap->getRedirection(true),
                __('Change language for this selection') => ''
            ]);

            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<p><label for="new_lang" class="classic">' . __('Entry language:') . '</label> ' .
                Form::combo('new_lang', $lang_combo) .

                dotclear()->nonce()->form() . $ap->getHiddenFields() .
                Form::hidden(['action'], 'lang') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }
}
