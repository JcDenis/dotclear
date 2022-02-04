<?php
/**
 * @class Dotclear\Admin\Action\DefaultPostAction
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

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Admin\Action;

use Dotclear\Utils\L10n;
use Dotclear\Html\Form;
use Dotclear\Html\Html;

class DefaultPostAction
{
    public static function PostAction(Action $ap)
    {
        if (dcCore()->auth->check('publish,contentadmin', dcCore()->blog->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Schedule')        => 'schedule',
                    __('Mark as pending') => 'pending'
                ]],
                [__NAMESPACE__ . '\\DefaultPostAction', 'doChangePostStatus']
            );
        }
        $ap->addAction(
            [__('Mark') => [
                __('Mark as selected')   => 'selected',
                __('Mark as unselected') => 'unselected'
            ]],
            [__NAMESPACE__ . '\\DefaultPostAction', 'doUpdateSelectedPost']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change category') => 'category'
            ]],
            [__NAMESPACE__ . '\\DefaultPostAction', 'doChangePostCategory']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change language') => 'lang'
            ]],
            [__NAMESPACE__ . '\\DefaultPostAction', 'doChangePostLang']
        );
        if (dcCore()->auth->check('admin', dcCore()->blog->id)) {
            $ap->addAction(
                [__('Change') => [
                    __('Change author') => 'author']],
                [__NAMESPACE__ . '\\DefaultPostAction', 'doChangePostAuthor']
            );
        }
        if (dcCore()->auth->check('delete,contentadmin', dcCore()->blog->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete']],
                [__NAMESPACE__ . '\\DefaultPostAction', 'doDeletePost']
            );
        }
    }

    public static function doChangePostStatus(Action $ap, $post)
    {
        switch ($ap->getAction()) {
            case 'unpublish':
                $status = 0;

                break;
            case 'schedule':
                $status = -1;

                break;
            case 'pending':
                $status = -2;

                break;
            default:
                $status = 1;

                break;
        }
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
        dcCore()->blog->updPostsStatus($posts_ids, $status);
        dcCore()->notices->addSuccessNotice(sprintf(
            __(
                '%d entry has been successfully updated to status : "%s"',
                '%d entries have been successfully updated to status : "%s"',
                count($posts_ids)
            ),
            count($posts_ids),
            dcCore()->blog->getPostStatus($status))
        );
        $ap->redirect(true);
    }

    public static function doUpdateSelectedPost(Action $ap, $post)
    {
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new AdminException(__('No entry selected'));
        }
        $action = $ap->getAction();
        dcCore()->blog->updPostsSelected($posts_ids, $action == 'selected');
        if ($action == 'selected') {
            dcCore()->notices->addSuccessNotice(sprintf(
                __(
                    '%d entry has been successfully marked as selected',
                    '%d entries have been successfully marked as selected',
                    count($posts_ids)
                ),
                count($posts_ids))
            );
        } else {
            dcCore()->notices->addSuccessNotice(sprintf(
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

    public static function doDeletePost(Action $ap, $post)
    {
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new AdminException(__('No entry selected'));
        }
        // Backward compatibility
        foreach ($posts_ids as $post_id) {
            # --BEHAVIOR-- adminBeforePostDelete
            dcCore()->behaviors->call('adminBeforePostDelete', (integer) $post_id);
        }

        # --BEHAVIOR-- adminBeforePostsDelete
        dcCore()->behaviors->call('adminBeforePostsDelete', $posts_ids);

        dcCore()->blog->delPosts($posts_ids);
        dcCore()->notices->addSuccessNotice(sprintf(
            __(
                '%d entry has been successfully deleted',
                '%d entries have been successfully deleted',
                count($posts_ids)
            ),
            count($posts_ids))
        );

        $ap->redirect(false);
    }

    public static function doChangePostCategory(Action $ap, $post)
    {
        if (isset($post['new_cat_id'])) {
            $posts_ids = $ap->getIDs();
            if (empty($posts_ids)) {
                throw new AdminException(__('No entry selected'));
            }
            $new_cat_id = (int) $post['new_cat_id'];
            if (!empty($post['new_cat_title']) && dcCore()->auth->check('categories', dcCore()->blog->id)) {
                //to do: check for duplicate category and throw clean Exception
                $cur_cat            = dcCore()->con->openCursor(dcCore()->prefix . 'category');
                $cur_cat->cat_title = $post['new_cat_title'];
                $cur_cat->cat_url   = '';
                $title              = $cur_cat->cat_title;

                $parent_cat = !empty($post['new_cat_parent']) ? $post['new_cat_parent'] : '';

                # --BEHAVIOR-- adminBeforeCategoryCreate
                dcCore()->behaviors->call('adminBeforeCategoryCreate', $cur_cat);

                $new_cat_id = dcCore()->blog->addCategory($cur_cat, (integer) $parent_cat);

                # --BEHAVIOR-- adminAfterCategoryCreate
                dcCore()->behaviors->call('adminAfterCategoryCreate', $cur_cat, $new_cat_id);
            }

            dcCore()->blog->updPostsCategory($posts_ids, $new_cat_id);
            $title = dcCore()->blog->getCategory($new_cat_id);
            dcCore()->notices->addSuccessNotice(sprintf(
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
            $categories_combo = $this->core->combos->getCategoriesCombo(
                dcCore()->blog->getCategories()
            );

            $ap->setPageBreadcrumb([
                Html::escapeHTML(dcCore()->blog->name)      => '',
                $ap->getCallerTitle()                    => $ap->getRedirection(true),
                __('Change category for this selection') => ''
            ]);

            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<p><label for="new_cat_id" class="classic">' . __('Category:') . '</label> ' .
                Form::combo(['new_cat_id'], $categories_combo)
            );

            if (dcCore()->auth->check('categories', dcCore()->blog->id)) {

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
                dcCore()->formNonce() .
                $ap->getHiddenFields() .
                Form::hidden(['action'], 'category') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }

    public static function doChangePostAuthor(Action $ap, $post)
    {
        if (isset($post['new_auth_id']) && dcCore()->auth->check('admin', dcCore()->blog->id)) {
            $new_user_id = $post['new_auth_id'];
            $posts_ids   = $ap->getIDs();
            if (empty($posts_ids)) {
                throw new AdminException(__('No entry selected'));
            }
            if (dcCore()->getUser($new_user_id)->isEmpty()) {
                throw new AdminException(__('This user does not exist'));
            }

            $cur          = dcCore()->con->openCursor(dcCore()->prefix . 'post');
            $cur->user_id = $new_user_id;
            $cur->update('WHERE post_id ' . dcCore()->con->in($posts_ids));
            dcCore()->notices->addSuccessNotice(sprintf(
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
            if (dcCore()->auth->check('admin', dcCore()->blog->id)) {
                $params = [
                    'limit' => 100,
                    'order' => 'nb_post DESC'
                ];
                $rs       = dcCore()->getUsers($params);
                $rsStatic = $rs->toStatic();
                $rsStatic->extend('Dotclear\\Core\\RsExt\\RsExtUser');
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort('user_id');
                while ($rsStatic->fetch()) {
                    $usersList[] = $rsStatic->user_id;
                }
            }

            $ap->setPageBreadcrumb([
                Html::escapeHTML(dcCore()->blog->name)    => '',
                $ap->getCallerTitle()                  => $ap->getRedirection(true),
                __('Change author for this selection') => ''
            ]);
            $ap->setPageHead(
                Action::jsLoad('js/jquery/jquery.autocomplete.js') .
                Action::jsJson('users_list', $usersList)
            );
            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<p><label for="new_auth_id" class="classic">' . __('New author (author ID):') . '</label> ' .
                Form::field('new_auth_id', 20, 255) .

                dcCore()->formNonce() . $ap->getHiddenFields() .
                Form::hidden(['action'], 'author') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }

    public static function doChangePostLang(Action $ap, $post)
    {
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new AdminException(__('No entry selected'));
        }
        if (isset($post['new_lang'])) {
            $new_lang       = $post['new_lang'];
            $cur            = dcCore()->con->openCursor(dcCore()->prefix . 'post');
            $cur->post_lang = $new_lang;
            $cur->update('WHERE post_id ' . dcCore()->con->in($posts_ids));
            dcCore()->notices->addSuccessNotice(sprintf(
                __(
                    '%d entry has been successfully set to language "%s"',
                    '%d entries have been successfully set to language "%s"',
                    count($posts_ids)
                ),
                count($posts_ids),
                Html::escapeHTML(l10n::getLanguageName($new_lang)))
            );
            $ap->redirect(true);
        } else {
            $rs         = dcCore()->blog->getLangs(['order' => 'asc']);
            $all_langs  = L10n::getISOcodes(false, true);
            $lang_combo = ['' => '', __('Most used') => [], __('Available') => l10n::getISOcodes(true, true)];
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
                Html::escapeHTML(dcCore()->blog->name)      => '',
                $ap->getCallerTitle()                    => $ap->getRedirection(true),
                __('Change language for this selection') => ''
            ]);

            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<p><label for="new_lang" class="classic">' . __('Entry language:') . '</label> ' .
                Form::combo('new_lang', $lang_combo) .

                dcCore()->formNonce() . $ap->getHiddenFields() .
                Form::hidden(['action'], 'lang') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>'
            );
        }
    }
}
