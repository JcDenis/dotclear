<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\PostFilters
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Lexical;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filters;
use Dotclear\Process\Admin\Filter\FilterStack;
use Exception;

/**
 * Admin posts list filters form.
 *
 * @ingroup  Admin Post Filter
 *
 * @since 2.20
 */
class PostFilters extends Filters
{
    public function __construct(string $id = 'posts', private string $post_type = 'post')
    {
        $filter_stack = new FilterStack();

        if (!App::core()->posttype()->hasPostType(type: $this->post_type)) {
            $this->post_type = 'post';
        }
        if ('post' != $this->post_type) {
            $filter_stack->addFilter(new Filter(
                id: 'post_type',
                value: $this->post_type,
                params: [['post_type', null]],
            ));
        }

        $filter_stack->addFilter(filter: $this->getPageFilter());
        $filter_stack->addFilter(filter: $this->getPostUserFilter());
        $filter_stack->addFilter(filter: $this->getPostCategoriesFilter());
        $filter_stack->addFilter(filter: $this->getPostStatusFilter());
        $filter_stack->addFilter(filter: $this->getPostFormatFilter());
        $filter_stack->addFilter(filter: $this->getPostPasswordFilter());
        $filter_stack->addFilter(filter: $this->getPostSelectedFilter());
        $filter_stack->addFilter(filter: $this->getPostAttachmentFilter());
        $filter_stack->addFilter(filter: $this->getPostMonthFilter());
        $filter_stack->addFilter(filter: $this->getPostLangFilter());
        $filter_stack->addFilter(filter: $this->getPostCommentFilter());
        $filter_stack->addFilter(filter: $this->getPostTrackbackFilter());

        parent::__construct(id: $id, filters: $filter_stack);
    }

    /**
     * Posts users select.
     */
    public function getPostUserFilter(): ?Filter
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

        return new Filter(
            id: 'user_id',
            title: __('Author:'),
            prime: true,
            // params: [[null, null],],
            options: array_merge(
                ['-' => ''],
                $combo
            ),
        );
    }

    /**
     * Posts categories select.
     */
    public function getPostCategoriesFilter(): ?Filter
    {
        $categories = null;

        try {
            $param = new Param();
            $param->set('post_type', $this->post_type);

            $categories = App::core()->blog()->categories()->getCategories(param: $param);
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
                str_repeat('&nbsp;', ($categories->integer('level') - 1) * 4) .
                Html::escapeHTML($categories->field('cat_title')) . ' (' . $categories->field('nb_post') . ')'
            ] = (string) $categories->field('cat_id');
        }

        return new Filter(
            id: 'cat_id',
            title: __('Category:'),
            prime: true,
            params: [[null, null]],
            options: $combo,
        );
    }

    /**
     * Posts status select.
     */
    public function getPostStatusFilter(): Filter
    {
        return new Filter(
            id: 'status',
            title: __('Status:'),
            prime: true,
            params: [
                ['post_status', fn ($f) => (int) $f[0]],
            ],
            options: array_merge(
                ['-' => ''],
                App::core()->combo()->getPostStatusesCombo()
            ),
        );
    }

    /**
     * Posts format select.
     */
    public function getPostFormatFilter(): Filter
    {
        $core_formaters    = App::core()->formater()->getFormaters();
        $available_formats = [];
        foreach ($core_formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $available_formats[$format] = $format;
            }
        }

        return new Filter(
            id: 'format',
            title: __('Format:'),
            prime: true,
            params: [
                ['where', fn ($f) => " AND post_format = '" . $f[0] . "' "],
            ],
            options: array_merge(
                ['-' => ''],
                $available_formats
            ),
        );
    }

    /**
     * Posts password state select.
     */
    public function getPostPasswordFilter(): Filter
    {
        return new Filter(
            id: 'password',
            title: __('Password:'),
            prime: true,
            params: [
                ['sql', fn ($f) => ' AND post_password IS ' . ($f[0] ? 'NOT ' : '') . 'NULL '],
            ],
            options: [
                '-'                    => '',
                __('With password')    => '1',
                __('Without password') => '0',
            ],
        );
    }

    /**
     * Posts selected state select.
     */
    public function getPostSelectedFilter(): Filter
    {
        return new Filter(
            id: 'selected',
            title: __('Selected:'),
            params: [
                ['post_selected', fn ($f) => (bool) $f[0]],
            ],
            options: [
                '-'                => '',
                __('Selected')     => '1',
                __('Not selected') => '0',
            ],
        );
    }

    /**
     * Posts attachment state select.
     */
    public function getPostAttachmentFilter(): Filter
    {
        return new Filter(
            id: 'attachment',
            title: __('Attachments:'),
            params: [
                ['media', null],
                ['link_type', 'attachment'],
            ],
            options: [
                '-'                       => '',
                __('With attachments')    => '1',
                __('Without attachments') => '0',
            ],
        );
    }

    /**
     * Posts by month select.
     */
    public function getPostMonthFilter(): ?Filter
    {
        $dates = null;

        try {
            $param = new Param();
            $param->set('type', 'month');
            $param->set('post_type', $this->post_type);

            $dates = App::core()->blog()->posts()->getDates(param: $param);
            if ($dates->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());

            return null;
        }

        return new Filter(
            id: 'month',
            title: __('Month:'),
            params: [
                ['post_month', fn ($f) => (int) substr($f[0], 4, 2)],
                ['post_year', fn ($f) => (int) substr($f[0], 0, 4)],
            ],
            options: array_merge(
                ['-' => ''],
                App::core()->combo()->getDatesCombo($dates)
            ),
        );
    }

    /**
     * Posts lang select.
     */
    public function getPostLangFilter(): ?Filter
    {
        $langs = null;

        try {
            $param = new Param();
            $param->set('post_type', $this->post_type);

            $langs = App::core()->blog()->posts()->getLangs(param: $param);
            if ($langs->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());

            return null;
        }

        return new Filter(
            id: 'lang',
            title: __('Lang:'),
            params: [
                ['post_lang', null],
            ],
            options: array_merge(
                ['-' => ''],
                App::core()->combo()->getLangsCombo($langs, false)
            ),
        );
    }

    /**
     * Posts comments state select.
     */
    public function getPostCommentFilter(): Filter
    {
        return new Filter(
            id: 'comment',
            title: __('Comments:'),
            params: [
                ['where', fn ($f) => " AND post_open_comment = '" . $f[0] . "' "],
            ],
            options: [
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0',
            ],
        );
    }

    /**
     * Posts trackbacks state select.
     */
    public function getPostTrackbackFilter(): Filter
    {
        return new Filter(
            id: 'trackback',
            title: __('Trackbacks:'),
            params: [
                ['where', fn ($f) => " AND post_open_tb = '" . $f[0] . "' "],
            ],
            options: [
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0',
            ],
        );
    }
}
