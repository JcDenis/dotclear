<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\PostFilter
use Dotclear\App;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\FiltersStack;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Lexical;
use Exception;

/**
 * Admin posts list filters form.
 *
 * @ingroup  Admin Post Filter
 *
 * @since 2.20
 */
class PostFilter extends Filter
{
    public function __construct(string $type = 'posts', protected string $post_type = 'post')
    {
        parent::__construct($type);

        $fs = new FiltersStack();

        if (!App::core()->posttype()->exists($this->post_type)) {
            $this->post_type = 'post';
        }
        if ('post' != $this->post_type) {
            $fs->add((new DefaultFilter('post_type', $this->post_type))->param('post_type'));
        }

        $fs->add($this->getPageFilter());
        $fs->add($this->getPostUserFilter());
        $fs->add($this->getPostCategoriesFilter());
        $fs->add($this->getPostStatusFilter());
        $fs->add($this->getPostFormatFilter());
        $fs->add($this->getPostPasswordFilter());
        $fs->add($this->getPostSelectedFilter());
        $fs->add($this->getPostAttachmentFilter());
        $fs->add($this->getPostMonthFilter());
        $fs->add($this->getPostLangFilter());
        $fs->add($this->getPostCommentFilter());
        $fs->add($this->getPostTrackbackFilter());

        // --BEHAVIOR-- adminPostFilter, FiltersStack
        App::core()->behavior()->call('adminPostFilter', $fs);

        $this->addStack($fs);
    }

    /**
     * Posts users select.
     */
    public function getPostUserFilter(): ?DefaultFilter
    {
        $users = null;

        try {
            $users = App::core()->blog()->posts()->getPostsUsers($this->post_type);
            if ($users->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());

            return null;
        }

        $combo = App::core()->combo()->getUsersCombo($users);
        Lexical::lexicalKeySort($combo);

        return DefaultFilter::init('user_id')
            ->param()
            ->title(__('Author:'))
            ->options(array_merge(
                ['-' => ''],
                $combo
            ))
            ->prime(true)
        ;
    }

    /**
     * Posts categories select.
     */
    public function getPostCategoriesFilter(): ?DefaultFilter
    {
        $categories = null;

        try {
            $categories = App::core()->blog()->categories()->getCategories(['post_type' => $this->post_type]);
            if ($categories->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());

            return null;
        }

        $combo = [
            '-'            => '',
            __('(No cat)') => 'NULL',
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
            ->prime(true)
        ;
    }

    /**
     * Posts status select.
     */
    public function getPostStatusFilter(): DefaultFilter
    {
        return DefaultFilter::init('status')
            ->param('post_status')
            ->title(__('Status:'))
            ->options(array_merge(
                ['-' => ''],
                App::core()->combo()->getPostStatusesCombo()
            ))
            ->prime(true)
        ;
    }

    /**
     * Posts format select.
     */
    public function getPostFormatFilter(): DefaultFilter
    {
        $core_formaters    = App::core()->formater()->getFormaters();
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
            ->prime(true)
        ;
    }

    public function getPostFormatParam($f): string
    {
        return " AND post_format = '" . $f[0] . "' ";
    }

    /**
     * Posts password state select.
     */
    public function getPostPasswordFilter(): DefaultFilter
    {
        return DefaultFilter::init('password')
            ->param('where', [$this, 'getPostPasswordParam'])
            ->title(__('Password:'))
            ->options([
                '-'                    => '',
                __('With password')    => '1',
                __('Without password') => '0',
            ])
            ->prime(true)
        ;
    }

    public function getPostPasswordParam($f): string
    {
        return ' AND post_password IS ' . ($f[0] ? 'NOT ' : '') . 'NULL ';
    }

    /**
     * Posts selected state select.
     */
    public function getPostSelectedFilter(): DefaultFilter
    {
        return DefaultFilter::init('selected')
            ->param('post_selected')
            ->title(__('Selected:'))
            ->options([
                '-'                => '',
                __('Selected')     => '1',
                __('Not selected') => '0',
            ])
        ;
    }

    /**
     * Posts attachment state select.
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
                __('Without attachments') => '0',
            ])
        ;
    }

    /**
     * Posts by month select.
     */
    public function getPostMonthFilter(): ?DefaultFilter
    {
        $dates = null;

        try {
            $dates = App::core()->blog()->posts()->getDates([
                'type'      => 'month',
                'post_type' => $this->post_type,
            ]);
            if ($dates->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());

            return null;
        }

        return DefaultFilter::init('month')
            ->param('post_month', [$this, 'getPostMonthParam'])
            ->param('post_year', [$this, 'getPostYearParam'])
            ->title(__('Month:'))
            ->options(array_merge(
                ['-' => ''],
                App::core()->combo()->getDatesCombo($dates)
            ))
        ;
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
     * Posts lang select.
     */
    public function getPostLangFilter(): ?DefaultFilter
    {
        $langs = null;

        try {
            $langs = App::core()->blog()->posts()->getLangs(['post_type' => $this->post_type]);
            if ($langs->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());

            return null;
        }

        return DefaultFilter::init('lang')
            ->param('post_lang')
            ->title(__('Lang:'))
            ->options(array_merge(
                ['-' => ''],
                App::core()->combo()->getLangsCombo($langs, false)
            ))
        ;
    }

    /**
     * Posts comments state select.
     */
    public function getPostCommentFilter(): DefaultFilter
    {
        return DefaultFilter::init('comment')
            ->param('where', [$this, 'getPostCommentParam'])
            ->title(__('Comments:'))
            ->options([
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0',
            ])
        ;
    }

    public function getPostCommentParam($f): string
    {
        return " AND post_open_comment = '" . $f[0] . "' ";
    }

    /**
     * Posts trackbacks state select.
     */
    public function getPostTrackbackFilter(): DefaultFilter
    {
        return DefaultFilter::init('trackback')
            ->param('where', [$this, 'getPostTrackbackParam'])
            ->title(__('Trackbacks:'))
            ->options([
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0',
            ])
        ;
    }

    public function getPostTrackbackParam($f): string
    {
        return " AND post_open_tb = '" . $f[0] . "' ";
    }
}
