<?php
/**
 * @class Dotclear\Process\Admin\Combo\Combo
 * @brief Admin combo library
 *
 * Dotclear utility class that provides reuseable combos across all admin
 * form::combo -compatible format
 *
 * Accessible from dotclear()->combo()->
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Combo;

use ArrayObject;

use Dotclear\Container\UserContainer;
use Dotclear\Database\Record;
use Dotclear\Helper\Html\FormSelectOption;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Dt;
use Dotclear\Helper\L10n;

class Combo
{
    /**
     * Return an hierarchical categories combo from a category record
     *
     * @param   Record  $categories     The categories
     * @param   bool    $include_empty  Includes empty categories
     * @param   bool    $use_url        Use url or ID
     *
     * @return  array                   The categories combo
     */
    public function getCategoriesCombo(Record $categories, bool $include_empty = true, bool $use_url = false): array
    {
        $categories_combo = [];
        if ($include_empty) {
            $categories_combo = [new FormSelectOption(__('(No cat)'), '')];
        }
        while ($categories->fetch()) {
            $categories_combo[] = new FormSelectOption(
                str_repeat('&nbsp;', ($categories->fInt('level') - 1) * 4) .
                Html::escapeHTML($categories->f('cat_title')) . ' (' . $categories->f('nb_post') . ')',
                ($use_url ? $categories->f('cat_url') : $categories->f('cat_id')),
                ($categories->fInt('level') - 1 ? 'sub-option' . ($categories->fInt('level') - 1) : '')
            );
        }

        return $categories_combo;
    }

    /**
     * Return available post status combo.
     *
     * @return  array   The post statuses combo
     */
    public function getPostStatusesCombo(): array
    {
        $status_combo = [];
        foreach (dotclear()->blog()->getAllPostStatus() as $k => $v) {
            $status_combo[$v] = (string) $k;
        }

        return $status_combo;
    }

    /**
     * Return a users combo from a users record.
     *
     * @param   Record  $users  The users
     *
     * @return  array           The users combo
     */
    public function getUsersCombo(Record $users): array
    {
        $users_combo = [];
        while ($users->fetch()) {
            $user_cn = UserContainer::getUserCN(
                $users->f('user_id'),
                $users->f('user_name'),
                $users->f('user_firstname'),
                $users->f('user_displayname')
            );

            if ($user_cn != $users->f('user_id')) {
                $user_cn .= ' (' . $users->f('user_id') . ')';
            }

            $users_combo[$user_cn] = $users->f('user_id');
        }

        return $users_combo;
    }

    /**
     * Get the dates combo.
     *
     * @param   Record  $dates  The dates
     *
     * @return  array           The dates combo
     */
    public function getDatesCombo(Record $dates): array
    {
        $dt_m_combo = [];
        while ($dates->fetch()) {
            $dt_m_combo[Dt::str('%B %Y', $dates->call('ts'))] = $dates->call('year') . $dates->call('month');
        }

        return $dt_m_combo;
    }

    /**
     * Get the langs combo.
     *
     * @param   Record  $langs              The langs
     * @param   bool    $with_available     If false, only list items from
     * record if true, also list available languages
     *
     * @return  array                       The langs combo
     */
    public function getLangsCombo(Record $langs, bool $with_available = false): array
    {
        $all_langs = L10n::getISOcodes(false, true);
        if ($with_available) {
            $langs_combo = ['' => '', __('Most used') => [], __('Available') => L10n::getISOcodes(true, true)];
            while ($langs->fetch()) {
                if (isset($all_langs[$langs->f('post_lang')])) {
                    $langs_combo[__('Most used')][$all_langs[$langs->f('post_lang')]] = $langs->f('post_lang');
                    unset($langs_combo[__('Available')][$all_langs[$langs->f('post_lang')]]);
                } else {
                    $langs_combo[__('Most used')][$langs->f('post_lang')] = $langs->f('post_lang');
                }
            }
        } else {
            $langs_combo = [];
            while ($langs->fetch()) {
                $lang_name               = $all_langs[$langs->f('post_lang')] ?? $langs->f('post_lang');
                $langs_combo[$lang_name] = $langs->f('post_lang');
            }
        }
        unset($all_langs);

        return $langs_combo;
    }

    /**
     * Return a combo containing all available and installed languages for administration pages.
     *
     * @return  array   The admin langs combo
     */
    public function getAdminLangsCombo(): array
    {
        $lang_combo = [];
        $langs      = L10n::getISOcodes(true, true);
        foreach ($langs as $k => $v) {
            $lang_avail   = $v == 'en' || is_dir(dotclear()->config()->get('l10n_dir') . '/' . $v);
            $lang_combo[] = new FormSelectOption($k, $v, $lang_avail ? 'avail10n' : '');
        }

        return $lang_combo;
    }

    /**
     * Return a combo containing all available editors in admin.
     *
     * @return  array   The editors combo
     */
    public function getEditorsCombo(): array
    {
        $editors_combo = [];
        foreach (dotclear()->formater()->getEditors() as $v) {
            $editors_combo[$v] = $v;
        }

        return $editors_combo;
    }

    /**
     * Return a combo containing all available formaters by editor in admin.
     *
     * @param   string  $editor_id  The editor identifier (LegacyEditor, dcCKEditor, ...)
     *
     * @return  array               The formaters combo
     */
    public function getFormatersCombo(string $editor_id = ''): array
    {
        $formaters_combo = [];
        if (!empty($editor_id)) {
            foreach (dotclear()->formater()->getFormaters($editor_id) as $formater) {
                $formaters_combo[$formater] = $formater;
            }
        } else {
            foreach (dotclear()->formater()->getFormaters() as $editor => $formaters) {
                foreach ($formaters as $formater) {
                    $formaters_combo[$editor][$formater] = $formater;
                }
            }
        }

        return $formaters_combo;
    }

    /**
     * Return a combo containing all available iconset in admin.
     *
     * @return  array   The iconset combo
     */
    public function getIconsetCombo(): array
    {
        $iconsets_combo = new ArrayObject([__('Default') => '']);

        # --BEHAVIOR-- adminPostsSortbyCombo , ArrayObject
        dotclear()->behavior()->call('adminIconsetCombo', $iconsets_combo);

        return $iconsets_combo->getArrayCopy();
    }

    /**
     * Get the blog statuses combo.
     *
     * @return  array   The blog statuses combo
     */
    public function getBlogStatusesCombo(): array
    {
        $status_combo = [];
        foreach (dotclear()->blogs()->getAllBlogStatus() as $k => $v) {
            $status_combo[$v] = (string) $k;
        }

        return $status_combo;
    }

    /**
     * Get the comment statuses combo.
     *
     * @return  array   The comment statuses combo
     */
    public function getCommentStatusesCombo(): array
    {
        $status_combo = [];
        foreach (dotclear()->blog()->getAllCommentStatus() as $k => $v) {
            $status_combo[$v] = (string) $k;
        }

        return $status_combo;
    }

    /**
     * Get order combo
     *
     * @return  array   The order combo
     */
    public function getOrderCombo(): array
    {
        return [
            __('Descending') => 'desc',
            __('Ascending')  => 'asc'
        ];
    }

    /**
     * Get sort by combo for posts
     *
     * @return  array   The posts sort by combo
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
            __('Number of trackbacks') => 'nb_trackback'
        ]);
        # --BEHAVIOR-- adminPostsSortbyCombo , ArrayObject
        dotclear()->behavior()->call('adminPostsSortbyCombo', $sortby_combo);

        return $sortby_combo->getArrayCopy();
    }

    /**
     * Get sort by combo for comments
     *
     * @return  array   The comments sort by combo
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
            __('Spam filter') => 'comment_spam_filter'
        ]);
        # --BEHAVIOR-- adminCommentsSortbyCombo , ArrayObject
        dotclear()->behavior()->call('adminCommentsSortbyCombo', $sortby_combo);

        return $sortby_combo->getArrayCopy();
    }

    /**
     * Get sort by combo for blogs
     *
     * @return  array   The blogs sort by combo
     */
    public function getBlogsSortbyCombo(): array
    {
        $sortby_combo = new ArrayObject([
            __('Last update') => 'blog_upddt',
            __('Blog name')   => 'UPPER(blog_name)',
            __('Blog ID')     => 'B.blog_id',
            __('Status')      => 'blog_status'
        ]);
        # --BEHAVIOR-- adminBlogsSortbyCombo , ArrayObject
        dotclear()->behavior()->call('adminBlogsSortbyCombo', $sortby_combo);

        return $sortby_combo->getArrayCopy();
    }

    /**
     * Get sort by combo for users
     *
     * @return  array   The users sort by combo
     */
    public function getUsersSortbyCombo(): array
    {
        $sortby_combo = new ArrayObject([]);
        if (dotclear()->user()->isSuperAdmin()) {
            $sortby_combo = new ArrayObject([
                __('Username')          => 'user_id',
                __('Last Name')         => 'user_name',
                __('First Name')        => 'user_firstname',
                __('Display name')      => 'user_displayname',
                __('Number of entries') => 'nb_post'
            ]);
            # --BEHAVIOR-- adminUsersSortbyCombo , ArrayObject
            dotclear()->behavior()->call('adminUsersSortbyCombo', $sortby_combo);
        }

        return $sortby_combo->getArrayCopy();
    }
}
