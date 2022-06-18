<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Combo;

// Dotclear\Process\Admin\Combo\Combo
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\User\UserContainer;
use Dotclear\Database\Record;
use Dotclear\Helper\Html\FormSelectOption;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Clock;
use Dotclear\Helper\L10n;

/**
 * Admin combo library.
 *
 * Dotclear utility class that provides reuseable combos across all admin
 * form::combo -compatible format
 *
 * Accessible from App::core()->combo()->
 *
 * @ingroup  Admin Combo
 */
class Combo
{
    /**
     * Return an hierarchical categories combo from a category record.
     *
     * @param Record $categories    The categories
     * @param bool   $include_empty Includes empty categories
     * @param bool   $use_url       Use url or ID
     *
     * @return array The categories combo
     */
    public function getCategoriesCombo(Record $categories, bool $include_empty = true, bool $use_url = false): array
    {
        $categories_combo = [];
        if ($include_empty) {
            $categories_combo = [new FormSelectOption(__('(No cat)'), '')];
        }
        while ($categories->fetch()) {
            $categories_combo[] = new FormSelectOption(
                str_repeat('&nbsp;', ($categories->integer('level') - 1) * 4) .
                Html::escapeHTML($categories->field('cat_title')) . ' (' . $categories->field('nb_post') . ')',
                ($use_url ? $categories->field('cat_url') : $categories->field('cat_id')),
                ($categories->integer('level') - 1 ? 'sub-option' . ($categories->integer('level') - 1) : '')
            );
        }

        return $categories_combo;
    }

    /**
     * Return available post status combo.
     *
     * @return array The post statuses combo
     */
    public function getPostStatusesCombo(): array
    {
        return App::core()->blog()->posts()->status()->getStates();
    }

    /**
     * Return a users combo from a users record.
     *
     * @param Record $users The users
     *
     * @return array The users combo
     */
    public function getUsersCombo(Record $users): array
    {
        $users_combo = [];
        while ($users->fetch()) {
            $user_cn = UserContainer::getUserCN(
                $users->field('user_id'),
                $users->field('user_name'),
                $users->field('user_firstname'),
                $users->field('user_displayname')
            );

            if ($users->field('user_id') != $user_cn) {
                $user_cn .= ' (' . $users->field('user_id') . ')';
            }

            $users_combo[$user_cn] = $users->field('user_id');
        }

        return $users_combo;
    }

    /**
     * Get the dates combo.
     *
     * @param Record $dates The dates
     *
     * @return array The dates combo
     */
    public function getDatesCombo(Record $dates): array
    {
        $dt_m_combo = [];
        while ($dates->fetch()) {
            $dt_m_combo[Clock::str(
                format: '%B %Y',
                date: $dates->call('ts'),
                from: App::core()->timezone(),
                to: App::core()->timezone()
            )] = $dates->call('year') . $dates->call('month');
        }

        return $dt_m_combo;
    }

    /**
     * Get the langs combo.
     *
     * @param Record $langs          The langs
     * @param bool   $with_available If false, only list items from
     *                               record if true, also list available languages
     *
     * @return array The langs combo
     */
    public function getLangsCombo(Record $langs, bool $with_available = false): array
    {
        $all_langs = L10n::getISOcodes(false, true);
        if ($with_available) {
            $langs_combo = ['' => '', __('Most used') => [], __('Available') => L10n::getISOcodes(true, true)];
            while ($langs->fetch()) {
                if (isset($all_langs[$langs->field('post_lang')])) {
                    $langs_combo[__('Most used')][$all_langs[$langs->field('post_lang')]] = $langs->field('post_lang');
                    unset($langs_combo[__('Available')][$all_langs[$langs->field('post_lang')]]);
                } else {
                    $langs_combo[__('Most used')][$langs->field('post_lang')] = $langs->field('post_lang');
                }
            }
        } else {
            $langs_combo = [];
            while ($langs->fetch()) {
                $lang_name               = $all_langs[$langs->field('post_lang')] ?? $langs->field('post_lang');
                $langs_combo[$lang_name] = $langs->field('post_lang');
            }
        }
        unset($all_langs);

        return $langs_combo;
    }

    /**
     * Return a combo containing all available and installed languages for administration pages.
     *
     * @return array The admin langs combo
     */
    public function getAdminLangsCombo(): array
    {
        $lang_combo = [];
        $langs      = L10n::getISOcodes(true, true);
        foreach ($langs as $k => $v) {
            $lang_avail   = 'en' == $v || is_dir(App::core()->config()->get('l10n_dir') . '/' . $v);
            $lang_combo[] = new FormSelectOption($k, $v, $lang_avail ? 'avail10n' : '');
        }

        return $lang_combo;
    }

    /**
     * Return a combo containing all available editors in admin.
     *
     * @return array The editors combo
     */
    public function getEditorsCombo(): array
    {
        $editors_combo = [];
        foreach (App::core()->formater()->getEditors() as $v) {
            $editors_combo[$v] = $v;
        }

        return $editors_combo;
    }

    /**
     * Return a combo containing all available formaters by editor in admin.
     *
     * @param string $editor_id The editor identifier (LegacyEditor, CKEditor, ...)
     *
     * @return array The formaters combo
     */
    public function getFormatersCombo(string $editor_id = ''): array
    {
        $formaters_combo = [];
        if (!empty($editor_id)) {
            foreach (App::core()->formater()->getFormaters($editor_id) as $formater) {
                $formaters_combo[$formater] = $formater;
            }
        } else {
            foreach (App::core()->formater()->getFormaters() as $editor => $formaters) {
                foreach ($formaters as $formater) {
                    $formaters_combo[$editor][$formater] = $formater;
                }
            }
        }

        return $formaters_combo;
    }

    /**
     * Get the blog statuses combo.
     *
     * @return array The blog statuses combo
     */
    public function getBlogStatusesCombo(): array
    {
        return App::core()->blogs()->status()->getStates();
    }

    /**
     * Get the comment statuses combo.
     *
     * @return array The comment statuses combo
     */
    public function getCommentStatusesCombo(): array
    {
        return App::core()->blog()->comments()->status()->getStates();
    }

    /**
     * Get order combo.
     *
     * @return array The order combo
     */
    public function getOrderCombo(): array
    {
        return [
            __('Descending') => 'desc',
            __('Ascending')  => 'asc',
        ];
    }

    /**
     * Get sort by combo for posts.
     *
     * @return array The posts sort by combo
     */
    public function getPostsSortbyCombo(): array
    {
        $sortby_combo = new ArrayObject([
            __('Date')                 => 'post_dt',
            __('Title')                => 'post_title',
            __('Category')             => 'cat_title',
            __('Author')               => 'user_id',
            __('Status')               => 'post_status',
            __('Selected')             => 'post_selected',
            __('Number of comments')   => 'nb_comment',
            __('Number of trackbacks') => 'nb_trackback',
        ]);
        // --BEHAVIOR-- adminPostsSortbyCombo , ArrayObject
        App::core()->behavior('adminPostsSortbyCombo')->call($sortby_combo);

        return $sortby_combo->getArrayCopy();
    }

    /**
     * Get sort by combo for comments.
     *
     * @return array The comments sort by combo
     */
    public function getCommentsSortbyCombo(): array
    {
        $sortby_combo = new ArrayObject([
            __('Date')        => 'comment_dt',
            __('Entry title') => 'post_title',
            __('Entry date')  => 'post_dt',
            __('Author')      => 'comment_author',
            __('Status')      => 'comment_status',
            __('IP')          => 'comment_ip',
            __('Spam filter') => 'comment_spam_filter',
        ]);
        // --BEHAVIOR-- adminCommentsSortbyCombo , ArrayObject
        App::core()->behavior('adminCommentsSortbyCombo')->call($sortby_combo);

        return $sortby_combo->getArrayCopy();
    }

    /**
     * Get sort by combo for blogs.
     *
     * @return array The blogs sort by combo
     */
    public function getBlogsSortbyCombo(): array
    {
        $sortby_combo = new ArrayObject([
            __('Last update') => 'blog_upddt',
            __('Blog name')   => 'UPPER(blog_name)',
            __('Blog ID')     => 'B.blog_id',
            __('Status')      => 'blog_status',
        ]);
        // --BEHAVIOR-- adminBlogsSortbyCombo , ArrayObject
        App::core()->behavior('adminBlogsSortbyCombo')->call($sortby_combo);

        return $sortby_combo->getArrayCopy();
    }

    /**
     * Get sort by combo for users.
     *
     * @return array The users sort by combo
     */
    public function getUsersSortbyCombo(): array
    {
        $sortby_combo = new ArrayObject([]);
        if (App::core()->user()->isSuperAdmin()) {
            $sortby_combo = new ArrayObject([
                __('Username')          => 'user_id',
                __('Last Name')         => 'user_name',
                __('First Name')        => 'user_firstname',
                __('Display name')      => 'user_displayname',
                __('Number of entries') => 'nb_post',
            ]);
            // --BEHAVIOR-- adminUsersSortbyCombo , ArrayObject
            App::core()->behavior('adminUsersSortbyCombo')->call($sortby_combo);
        }

        return $sortby_combo->getArrayCopy();
    }
}
