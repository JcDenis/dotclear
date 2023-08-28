<?php
/**
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Backend\Action;

use ArrayObject;
use dcBlog;
use dcCategories;
use dcCore;
use Dotclear\Core\Backend\Combos;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Core;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;
use Exception;

class ActionsPostsDefault
{
    /**
     * Set posts actions
     *
     * @param      ActionsPosts  $ap
     */
    public static function adminPostsActionsPage(ActionsPosts $ap)
    {
        if (Core::auth()->check(Core::auth()->makePermissions([
            Core::auth()::PERMISSION_PUBLISH,
            Core::auth()::PERMISSION_CONTENT_ADMIN,
        ]), Core::blog()->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Schedule')        => 'schedule',
                    __('Mark as pending') => 'pending',
                ]],
                [self::class, 'doChangePostStatus']
            );
        }
        if (Core::auth()->check(Core::auth()->makePermissions([
            Core::auth()::PERMISSION_PUBLISH,
            Core::auth()::PERMISSION_CONTENT_ADMIN,
        ]), Core::blog()->id)) {
            $ap->addAction(
                [__('First publication') => [
                    __('Never published')   => 'never',
                    __('Already published') => 'already',
                ]],
                [self::class, 'doChangePostFirstPub']
            );
        }
        $ap->addAction(
            [__('Mark') => [
                __('Mark as selected')   => 'selected',
                __('Mark as unselected') => 'unselected',
            ]],
            [self::class, 'doUpdateSelectedPost']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change category') => 'category',
            ]],
            [self::class, 'doChangePostCategory']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change language') => 'lang',
            ]],
            [self::class, 'doChangePostLang']
        );
        if (Core::auth()->check(Core::auth()->makePermissions([
            Core::auth()::PERMISSION_ADMIN,
        ]), Core::blog()->id)) {
            $ap->addAction(
                [__('Change') => [
                    __('Change author') => 'author', ]],
                [self::class, 'doChangePostAuthor']
            );
        }
        if (Core::auth()->check(Core::auth()->makePermissions([
            Core::auth()::PERMISSION_DELETE,
            Core::auth()::PERMISSION_CONTENT_ADMIN,
        ]), Core::blog()->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete', ]],
                [self::class, 'doDeletePost']
            );
        }
    }

    /**
     * Does a change post status.
     *
     * @param      ActionsPosts  $ap
     *
     * @throws     Exception             (description)
     */
    public static function doChangePostStatus(ActionsPosts $ap)
    {
        $status = match ($ap->getAction()) {
            'unpublish' => dcBlog::POST_UNPUBLISHED,
            'schedule'  => dcBlog::POST_SCHEDULED,
            'pending'   => dcBlog::POST_PENDING,
            default     => dcBlog::POST_PUBLISHED,
        };

        $ids = $ap->getIDs();
        if (empty($ids)) {
            throw new Exception(__('No entry selected'));
        }

        // Do not switch to scheduled already published entries
        if ($status === dcBlog::POST_SCHEDULED) {
            $rs           = $ap->getRS();
            $excluded_ids = [];
            if ($rs->rows()) {
                while ($rs->fetch()) {
                    if ((int) $rs->post_status === dcBlog::POST_PUBLISHED) {
                        $excluded_ids[] = (int) $rs->post_id;
                    }
                }
            }
            if (count($excluded_ids)) {
                $ids = array_diff($ids, $excluded_ids);
            }
        }
        if (count($ids) === 0) {
            throw new Exception(__('Published entries cannot be set to scheduled'));
        }

        // Set status of remaining entries
        Core::blog()->updPostsStatus($ids, $status);

        Notices::addSuccessNotice(
            sprintf(
                __(
                    '%d entry has been successfully updated to status : "%s"',
                    '%d entries have been successfully updated to status : "%s"',
                    count($ids)
                ),
                count($ids),
                Core::blog()->getPostStatus($status)
            )
        );
        $ap->redirect(true);
    }

    /**
     * Does a change post status.
     *
     * @param      ActionsPosts  $ap
     *
     * @throws     Exception             (description)
     */
    public static function doChangePostFirstPub(ActionsPosts $ap)
    {
        $status = match ($ap->getAction()) {
            'never'   => 0,
            'already' => 1,
            default   => null,
        };

        if (!is_null($status)) {
            $ids = $ap->getIDs();
            if (empty($ids)) {
                throw new Exception(__('No entry selected'));
            }

            // Set first publication flag of entries
            Core::blog()->updPostsFirstPub($ids, $status);

            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully updated as: "%s"',
                        '%d entries have been successfully updated as: "%s"',
                        count($ids)
                    ),
                    count($ids),
                    $status ? __('Already published') : __('Never published')
                )
            );
        }
        $ap->redirect(true);
    }

    /**
     * Does an update selected post.
     *
     * @param      ActionsPosts  $ap
     *
     * @throws     Exception
     */
    public static function doUpdateSelectedPost(ActionsPosts $ap)
    {
        $ids = $ap->getIDs();
        if (empty($ids)) {
            throw new Exception(__('No entry selected'));
        }

        $action = $ap->getAction();
        Core::blog()->updPostsSelected($ids, $action === 'selected');
        if ($action == 'selected') {
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully marked as selected',
                        '%d entries have been successfully marked as selected',
                        count($ids)
                    ),
                    count($ids)
                )
            );
        } else {
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully marked as unselected',
                        '%d entries have been successfully marked as unselected',
                        count($ids)
                    ),
                    count($ids)
                )
            );
        }
        $ap->redirect(true);
    }

    /**
     * Does a delete post.
     *
     * @param      ActionsPosts  $ap
     *
     * @throws     Exception
     */
    public static function doDeletePost(ActionsPosts $ap)
    {
        $ids = $ap->getIDs();
        if (empty($ids)) {
            throw new Exception(__('No entry selected'));
        }
        // Backward compatibility
        foreach ($ids as $id) {
            # --BEHAVIOR-- adminBeforePostDelete -- int
            Core::behavior()->callBehavior('adminBeforePostDelete', (int) $id);
        }

        # --BEHAVIOR-- adminBeforePostsDelete -- array<int,string>
        Core::behavior()->callBehavior('adminBeforePostsDelete', $ids);

        Core::blog()->delPosts($ids);
        Notices::addSuccessNotice(
            sprintf(
                __(
                    '%d entry has been successfully deleted',
                    '%d entries have been successfully deleted',
                    count($ids)
                ),
                count($ids)
            )
        );

        $ap->redirect(false);
    }

    /**
     * Does a change post category.
     *
     * @param      ActionsPosts       $ap
     * @param      ArrayObject          $post   The parameters ($_POST)
     *
     * @throws     Exception             If no entry selected
     */
    public static function doChangePostCategory(ActionsPosts $ap, ArrayObject $post)
    {
        if (isset($post['new_cat_id'])) {
            $ids = $ap->getIDs();
            if (empty($ids)) {
                throw new Exception(__('No entry selected'));
            }
            $new_cat_id = (int) $post['new_cat_id'];
            if (!empty($post['new_cat_title']) && Core::auth()->check(Core::auth()->makePermissions([
                Core::auth()::PERMISSION_CATEGORIES,
            ]), Core::blog()->id)) {
                $cur_cat            = Core::con()->openCursor(Core::con()->prefix() . dcCategories::CATEGORY_TABLE_NAME);
                $cur_cat->cat_title = $post['new_cat_title'];
                $cur_cat->cat_url   = '';
                $title              = $cur_cat->cat_title;

                $parent_cat = !empty($post['new_cat_parent']) ? $post['new_cat_parent'] : '';

                # --BEHAVIOR-- adminBeforeCategoryCreate -- Cursor
                Core::behavior()->callBehavior('adminBeforeCategoryCreate', $cur_cat);

                $new_cat_id = (int) Core::blog()->addCategory($cur_cat, (int) $parent_cat);

                # --BEHAVIOR-- adminAfterCategoryCreate -- Cursor, string
                Core::behavior()->callBehavior('adminAfterCategoryCreate', $cur_cat, $new_cat_id);
            }

            Core::blog()->updPostsCategory($ids, $new_cat_id);
            $title = __('(No cat)');
            if ($new_cat_id) {
                $title = Core::blog()->getCategory($new_cat_id)->cat_title;
            }
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully moved to category "%s"',
                        '%d entries have been successfully moved to category "%s"',
                        count($ids)
                    ),
                    count($ids),
                    Html::escapeHTML($title)
                )
            );

            $ap->redirect(true);
        } else {
            $ap->beginPage(
                Page::breadcrumb(
                    [
                        Html::escapeHTML(Core::blog()->name) => '',
                        $ap->getCallerTitle()                       => $ap->getRedirection(true),
                        __('Change category for this selection')    => '',
                    ]
                )
            );
            # categories list
            # Getting categories
            $categories_combo = Combos::getCategoriesCombo(
                Core::blog()->getCategories()
            );

            $items = [
                $ap->checkboxes(),
                (new Para())
                    ->items([
                        (new Label(__('Category:'), Label::OUTSIDE_LABEL_BEFORE))
                            ->for('new_cat_id'),
                        (new Select('new_cat_id'))
                            ->items($categories_combo)
                            ->default(''),
                    ]),
            ];

            if (Core::auth()->check(Core::auth()->makePermissions([
                Core::auth()::PERMISSION_CATEGORIES,
            ]), Core::blog()->id)) {
                $items[] = (new Div())
                    ->items([
                        (new Text('p', __('Create a new category for the post(s)')))
                            ->id('new_cat'),
                        (new Para())
                            ->items([
                                (new Label(__('Title:'), Label::OUTSIDE_LABEL_BEFORE))
                                    ->for('new_cat_title'),
                                (new Input('new_cat_title'))
                                    ->size(30)
                                    ->maxlenght(255)
                                    ->value(''),
                            ]),
                        (new Para())
                            ->items([
                                (new Label(__('Parent:'), Label::OUTSIDE_LABEL_BEFORE))
                                    ->for('new_cat_parent'),
                                (new Select('new_cat_parent'))
                                    ->items($categories_combo)
                                    ->default(''),
                            ]),
                    ]);
            }

            $items[] = (new Para())
                ->items([
                    Core::nonce()->formNonce(),
                    ... $ap->hiddenFields(),
                    (new Hidden('action', 'category')),
                    (new Submit('save'))
                        ->value(__('Save')),
                ]);

            echo (new Form('dochangepostcategory'))
                ->method('post')
                ->action($ap->getURI())
                ->fields($items)
                ->render();

            $ap->endPage();
        }
    }

    /**
     * Does a change post author.
     *
     * @param      ActionsPosts  $ap
     * @param      ArrayObject           $post   The parameters ($_POST)
     *
     * @throws     Exception             If no entry selected
     */
    public static function doChangePostAuthor(ActionsPosts $ap, ArrayObject $post)
    {
        if (isset($post['new_auth_id']) && Core::auth()->check(Core::auth()->makePermissions([
            Core::auth()::PERMISSION_ADMIN,
        ]), Core::blog()->id)) {
            $new_user_id = $post['new_auth_id'];
            $ids         = $ap->getIDs();
            if (empty($ids)) {
                throw new Exception(__('No entry selected'));
            }
            if (Core::users()->getUser($new_user_id)->isEmpty()) {
                throw new Exception(__('This user does not exist'));
            }

            $cur          = Core::con()->openCursor(Core::con()->prefix() . dcBlog::POST_TABLE_NAME);
            $cur->user_id = $new_user_id;
            $cur->update('WHERE post_id ' . Core::con()->in($ids));
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully set to user "%s"',
                        '%d entries have been successfully set to user "%s"',
                        count($ids)
                    ),
                    count($ids),
                    Html::escapeHTML($new_user_id)
                )
            );

            $ap->redirect(true);
        } else {
            $usersList = [];
            if (Core::auth()->check(Core::auth()->makePermissions([
                Core::auth()::PERMISSION_ADMIN,
            ]), Core::blog()->id)) {
                $params = [
                    'limit' => 100,
                    'order' => 'nb_post DESC',
                ];
                $rs       = Core::users()->getUsers($params);
                $rsStatic = $rs->toStatic();
                $rsStatic->extend('rsExtUser');
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort('user_id');
                while ($rsStatic->fetch()) {
                    $usersList[] = $rsStatic->user_id;
                }
            }
            $ap->beginPage(
                Page::breadcrumb(
                    [
                        Html::escapeHTML(Core::blog()->name) => '',
                        $ap->getCallerTitle()                       => $ap->getRedirection(true),
                        __('Change author for this selection')      => '', ]
                ),
                Page::jsLoad('js/jquery/jquery.autocomplete.js') .
                Page::jsJson('users_list', $usersList)
            );

            echo (new Form('dochangepostauthor'))
                ->method('post')
                ->action($ap->getURI())
                ->fields([
                    $ap->checkboxes(),
                    (new Para())
                        ->items([
                            (new Label(__('New author (author ID):'), Label::OUTSIDE_LABEL_BEFORE))
                                ->for('new_auth_id'),
                            (new Input('new_auth_id'))
                                ->size(20)
                                ->maxlenght(255)
                                ->value(''),
                        ]),
                    (new Para())
                        ->items([
                            Core::nonce()->formNonce(),
                            ... $ap->hiddenFields(),
                            (new Hidden('action', 'author')),
                            (new Submit('save'))
                                ->value(__('Save')),

                        ]),
                ])
                ->render();

            $ap->endPage();
        }
    }

    /**
     * Does a change post language.
     *
     * @param      ActionsPosts  $ap
     * @param      ArrayObject           $post   The parameters ($_POST)
     *
     * @throws     Exception             If no entry selected
     */
    public static function doChangePostLang(ActionsPosts $ap, ArrayObject $post)
    {
        $post_ids = $ap->getIDs();
        if (empty($post_ids)) {
            throw new Exception(__('No entry selected'));
        }
        if (isset($post['new_lang'])) {
            $new_lang       = $post['new_lang'];
            $cur            = Core::con()->openCursor(Core::con()->prefix() . dcBlog::POST_TABLE_NAME);
            $cur->post_lang = $new_lang;
            $cur->update('WHERE post_id ' . Core::con()->in($post_ids));
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d entry has been successfully set to language "%s"',
                        '%d entries have been successfully set to language "%s"',
                        count($post_ids)
                    ),
                    count($post_ids),
                    Html::escapeHTML(L10n::getLanguageName($new_lang))
                )
            );
            $ap->redirect(true);
        } else {
            $ap->beginPage(
                Page::breadcrumb(
                    [
                        Html::escapeHTML(Core::blog()->name) => '',
                        $ap->getCallerTitle()                       => $ap->getRedirection(true),
                        __('Change language for this selection')    => '',
                    ]
                )
            );
            # lang list
            # Languages combo
            $rs         = Core::blog()->getLangs(['order' => 'asc']);
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

            echo (new Form('dochangepostlang'))
                ->method('post')
                ->action($ap->getURI())
                ->fields([
                    $ap->checkboxes(),
                    (new Para())
                        ->items([
                            (new Label(__('Entry language:'), Label::OUTSIDE_LABEL_BEFORE))
                                ->for('new_lang'),
                            (new Select('new_lang'))
                                ->items($lang_combo)
                                ->default(''),
                        ]),
                    (new Para())
                        ->items([
                            Core::nonce()->formNonce(),
                            ... $ap->hiddenFields(),
                            (new Hidden('action', 'lang')),
                            (new Submit('save'))
                                ->value(__('Save')),

                        ]),
                ])
                ->render();

            $ap->endPage();
        }
    }
}
