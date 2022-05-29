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
    public function __construct(string $type = 'posts', protected string $post_type = 'post')
    {
        $stack = new FilterStack();

        if (!App::core()->posttype()->exists($this->post_type)) {
            $this->post_type = 'post';
        }
        if ('post' != $this->post_type) {
            $filter = new Filter(id: 'post_type', value: $this->post_type);
            $filter->param(name: 'post_type');

            $stack->add(filter: $filter);
        }

        $stack->add(filter: $this->getPageFilter());
        $stack->add(filter: $this->getPostUserFilter());
        $stack->add(filter: $this->getPostCategoriesFilter());
        $stack->add(filter: $this->getPostStatusFilter());
        $stack->add(filter: $this->getPostFormatFilter());
        $stack->add(filter: $this->getPostPasswordFilter());
        $stack->add(filter: $this->getPostSelectedFilter());
        $stack->add(filter: $this->getPostAttachmentFilter());
        $stack->add(filter: $this->getPostMonthFilter());
        $stack->add(filter: $this->getPostLangFilter());
        $stack->add(filter: $this->getPostCommentFilter());
        $stack->add(filter: $this->getPostTrackbackFilter());

        parent::__construct(type: $type, filters: $stack);
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

        $filter = new Filter(id: 'user_id');
        $filter->param();
        $filter->title(title: __('Author:'));
        $filter->options(options: array_merge(
            ['-' => ''],
            $combo
        ));
        $filter->prime(prime: true);

        return $filter;
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
                str_repeat('&nbsp;', ($categories->fInt('level') - 1) * 4) .
                Html::escapeHTML($categories->f('cat_title')) . ' (' . $categories->f('nb_post') . ')'
            ] = (string) $categories->f('cat_id');
        }

        $filter = new Filter(id: 'cat_id');
        $filter->param();
        $filter->title(title: __('Category:'));
        $filter->options(options: $combo);
        $filter->prime(prime: true);

        return $filter;
    }

    /**
     * Posts status select.
     */
    public function getPostStatusFilter(): Filter
    {
        $filter = new Filter(id: 'status');
        $filter->param(name: 'post_status', value: fn ($f) => (int) $f[0]);
        $filter->title(title: __('Status:'));
        $filter->options(options: array_merge(
            ['-' => ''],
            App::core()->combo()->getPostStatusesCombo()
        ));
        $filter->prime(prime: true);

        return $filter;
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

        $filter = new Filter(id: 'format');
        $filter->param(name: 'where', value: fn ($f) => " AND post_format = '" . $f[0] . "' ");
        $filter->title(title: __('Format:'));
        $filter->options(options: array_merge(
            ['-' => ''],
            $available_formats
        ));
        $filter->prime(prime: true);

        return $filter;
    }

    /**
     * Posts password state select.
     */
    public function getPostPasswordFilter(): Filter
    {
        $filter = new Filter(id: 'password');
        $filter->param(name: 'sql', value: fn ($f) => ' AND post_password IS ' . ($f[0] ? 'NOT ' : '') . 'NULL ');
        $filter->title(title: __('Password:'));
        $filter->options(options: [
            '-'                    => '',
            __('With password')    => '1',
            __('Without password') => '0',
        ]);
        $filter->prime(prime: true);

        return $filter;
    }

    /**
     * Posts selected state select.
     */
    public function getPostSelectedFilter(): Filter
    {
        $filter = new Filter(id: 'selected');
        $filter->param(name: 'post_selected', value: fn ($f) => (bool) $f[0]);
        $filter->title(title: __('Selected:'));
        $filter->options(options: [
            '-'                => '',
            __('Selected')     => '1',
            __('Not selected') => '0',
        ]);

        return $filter;
    }

    /**
     * Posts attachment state select.
     */
    public function getPostAttachmentFilter(): Filter
    {
        $filter = new Filter(id: 'attachment');
        $filter->param(name: 'media');
        $filter->param(name: 'link_type', value: 'attachment');
        $filter->title(title: __('Attachments:'));
        $filter->options(options: [
            '-'                       => '',
            __('With attachments')    => '1',
            __('Without attachments') => '0',
        ]);

        return $filter;
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

        $filter = new Filter(id: 'month');
        $filter->param(name: 'post_month', value: fn ($f) => (int) substr($f[0], 4, 2));
        $filter->param(name: 'post_year', value: fn ($f) => (int) substr($f[0], 0, 4));
        $filter->title(title: __('Month:'));
        $filter->options(options: array_merge(
            ['-' => ''],
            App::core()->combo()->getDatesCombo($dates)
        ));

        return $filter;
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

        $filter = new Filter(id: 'lang');
        $filter->param(name: 'post_lang');
        $filter->title(title: __('Lang:'));
        $filter->options(options: array_merge(
            ['-' => ''],
            App::core()->combo()->getLangsCombo($langs, false)
        ));

        return $filter;
    }

    /**
     * Posts comments state select.
     */
    public function getPostCommentFilter(): Filter
    {
        $filter = new Filter(id: 'comment');
        $filter->param(name: 'where', value: fn ($f) => " AND post_open_comment = '" . $f[0] . "' ");
        $filter->title(title: __('Comments:'));
        $filter->options(options: [
            '-'          => '',
            __('Opened') => '1',
            __('Closed') => '0',
        ]);

        return $filter;
    }

    /**
     * Posts trackbacks state select.
     */
    public function getPostTrackbackFilter(): Filter
    {
        $filter = new Filter(id: 'trackback');
        $filter->param(name: 'where', value: fn ($f) => " AND post_open_tb = '" . $f[0] . "' ");
        $filter->title(title: __('Trackbacks:'));
        $filter->options(options: [
            '-'          => '',
            __('Opened') => '1',
            __('Closed') => '0',
        ]);

        return $filter;
    }
}
