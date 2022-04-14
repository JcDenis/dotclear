<?php
/**
 * @class Dotclear\Process\Admin\Filter\Filter\PostFilter
 * @brief class for admin post list filters form
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @since 2.20
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

use ArrayObject;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filter\DefaultFilter;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Lexical;

class PostFilter extends Filter
{
    public function __construct(string $type = 'posts', protected string $post_type = 'post')
    {
        parent::__construct($type);

        if (empty($this->post_type) || !array_key_exists($this->post_type, dotclear()->posttype()->getPostTypes())) {
            $this->post_type = 'post';
        }
        if ('post' != $this->post_type) {
            $this->add((new DefaultFilter('post_type', $this->post_type))->param('post_type'));
        }

        $filters = new ArrayObject([
            $this->getPageFilter(),
            $this->getPostUserFilter(),
            $this->getPostCategoriesFilter(),
            $this->getPostStatusFilter(),
            $this->getPostFormatFilter(),
            $this->getPostPasswordFilter(),
            $this->getPostSelectedFilter(),
            $this->getPostAttachmentFilter(),
            $this->getPostMonthFilter(),
            $this->getPostLangFilter(),
            $this->getPostCommentFilter(),
            $this->getPostTrackbackFilter()
        ]);

        # --BEHAVIOR-- adminPostFilter
        dotclear()->behavior()->call('adminPostFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
    }

    /**
     * Posts users select
     */
    public function getPostUserFilter(): ?DefaultFilter
    {
        $users = null;

        try {
            $users = dotclear()->blog()->posts()->getPostsUsers($this->post_type);
            if ($users->isEmpty()) {
                return null;
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());

            return null;
        }

        $combo = dotclear()->combo()->getUsersCombo($users);
        Lexical::lexicalKeySort($combo);

        return DefaultFilter::init('user_id')
            ->param()
            ->title(__('Author:'))
            ->options(array_merge(
                ['-' => ''],
                $combo
            ))
            ->prime(true);
    }

    /**
     * Posts categories select
     */
    public function getPostCategoriesFilter(): ?DefaultFilter
    {
        $categories = null;

        try {
            $categories = dotclear()->blog()->categories()->getCategories(['post_type' => $this->post_type]);
            if ($categories->isEmpty()) {
                return null;
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());

            return null;
        }

        $combo = [
            '-'            => '',
            __('(No cat)') => 'NULL'
        ];
        while ($categories->fetch()) {
            $combo[
                str_repeat('&nbsp;', ($categories->fInt('level') - 1) * 4) .
                Html::escapeHTML($categories->f('cat_title')) . ' (' . $categories->f('nb_post') . ')'
            ] = (string) $categories->f('cat_id');
        }

        return DefaultFilter::init('cat_id')
            ->param()
            ->title(__('Category:'))
            ->options($combo)
            ->prime(true);
    }

    /**
     * Posts status select
     */
    public function getPostStatusFilter(): DefaultFilter
    {
        return DefaultFilter::init('status')
            ->param('post_status')
            ->title(__('Status:'))
            ->options(array_merge(
                ['-' => ''],
                dotclear()->combo()->getPostStatusesCombo()
            ))
            ->prime(true);
    }

    /**
     * Posts format select
     */
    public function getPostFormatFilter(): DefaultFilter
    {
        $core_formaters    = dotclear()->formater()->getFormaters();
        $available_formats = [];
        foreach ($core_formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $available_formats[$format] = $format;
            }
        }

        return DefaultFilter::init('format')
            ->param('where', [$this, 'getPostFormatParam'])
            ->title(__('Format:'))
            ->options(array_merge(
                ['-' => ''],
                $available_formats
            ))
            ->prime(true);
    }

    public function getPostFormatParam($f): string
    {
        return " AND post_format = '" . $f[0] . "' ";
    }

    /**
     * Posts password state select
     */
    public function getPostPasswordFilter(): DefaultFilter
    {
        return DefaultFilter::init('password')
            ->param('where', [$this, 'getPostPasswordParam'])
            ->title(__('Password:'))
            ->options([
                '-'                    => '',
                __('With password')    => '1',
                __('Without password') => '0'
            ])
            ->prime(true);
    }

    public function getPostPasswordParam($f): string
    {
        return ' AND post_password IS ' . ($f[0] ? 'NOT ' : '') . 'NULL ';
    }

    /**
     * Posts selected state select
     */
    public function getPostSelectedFilter(): DefaultFilter
    {
        return DefaultFilter::init('selected')
            ->param('post_selected')
            ->title(__('Selected:'))
            ->options([
                '-'                => '',
                __('Selected')     => '1',
                __('Not selected') => '0'
            ]);
    }

    /**
     * Posts attachment state select
     */
    public function getPostAttachmentFilter(): DefaultFilter
    {
        return DefaultFilter::init('attachment')
            ->param('media')
            ->param('link_type', 'attachment')
            ->title(__('Attachments:'))
            ->options([
                '-'                       => '',
                __('With attachments')    => '1',
                __('Without attachments') => '0'
            ]);
    }

    /**
     * Posts by month select
     */
    public function getPostMonthFilter(): ?DefaultFilter
    {
        $dates = null;

        try {
            $dates = dotclear()->blog()->posts()->getDates([
                'type'      => 'month',
                'post_type' => $this->post_type
            ]);
            if ($dates->isEmpty()) {
                return null;
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());

            return null;
        }

        return DefaultFilter::init('month')
            ->param('post_month', [$this, 'getPostMonthParam'])
            ->param('post_year', [$this, 'getPostYearParam'])
            ->title(__('Month:'))
            ->options(array_merge(
                ['-' => ''],
                dotclear()->combo()->getDatesCombo($dates)
            ));
    }

    public function getPostMonthParam($f): string
    {
        return substr($f[0], 4, 2);
    }

    public function getPostYearParam($f): string
    {
        return substr($f[0], 0, 4);
    }

    /**
     * Posts lang select
     */
    public function getPostLangFilter(): ?DefaultFilter
    {
        $langs = null;

        try {
            $langs = dotclear()->blog()->posts()->getLangs(['post_type' => $this->post_type]);
            if ($langs->isEmpty()) {
                return null;
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());

            return null;
        }

        return DefaultFilter::init('lang')
            ->param('post_lang')
            ->title(__('Lang:'))
            ->options(array_merge(
                ['-' => ''],
                dotclear()->combo()->getLangsCombo($langs, false)
            ));
    }

    /**
     * Posts comments state select
     */
    public function getPostCommentFilter(): DefaultFilter
    {
        return DefaultFilter::init('comment')
            ->param('where', [$this, 'getPostCommentParam'])
            ->title(__('Comments:'))
            ->options([
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0'
            ]);
    }

    public function getPostCommentParam($f): string
    {
        return " AND post_open_comment = '" . $f[0] . "' ";
    }

    /**
     * Posts trackbacks state select
     */
    public function getPostTrackbackFilter(): DefaultFilter
    {
        return DefaultFilter::init('trackback')
            ->param('where', [$this, 'getPostTrackbackParam'])
            ->title(__('Trackbacks:'))
            ->options([
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0'
            ]);
    }

    public function getPostTrackbackParam($f): string
    {
        return " AND post_open_tb = '" . $f[0] . "' ";
    }
}
