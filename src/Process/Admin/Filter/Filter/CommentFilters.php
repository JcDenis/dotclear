<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\CommentFilters
use Dotclear\App;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filters;
use Dotclear\Process\Admin\Filter\FilterStack;

/**
 * Admin comments list filters form.
 *
 * @ingroup  Admin Comment Filter
 */
class CommentFilters extends Filters
{
    public function __construct()
    {
        parent::__construct(type: 'comments', filters: new FilterStack(
            $this->getPageFilter(),
            $this->getCommentAuthorFilter(),
            $this->getCommentTypeFilter(),
            $this->getCommentStatusFilter(),
            $this->getCommentIpFilter(),
            $this->getInputFilter('email', __('Email:'), 'comment_email'),
            $this->getInputFilter('site', __('Web site:'), 'comment_site')
        ));
    }

    /**
     * Comment author select.
     */
    public function getCommentAuthorFilter(): Filter
    {
        $filter = new Filter('author');
        $filter->param('q_author');
        $filter->form('input');
        $filter->title(__('Author:'));

        return $filter;
    }

    /**
     * Comment type select.
     */
    public function getCommentTypeFilter(): Filter
    {
        $filter = new Filter('type');
        $filter->param('comment_trackback', fn ($f) => 'tb' == $f[0]);
        $filter->title(__('Type:'));
        $filter->options([
            '-'             => '',
            __('Comment')   => 'co',
            __('Trackback') => 'tb',
        ]);
        $filter->prime(true);

        return $filter;
    }

    /**
     * Comment status select.
     */
    public function getCommentStatusFilter(): Filter
    {
        $filter = new Filter('status');
        $filter->param('comment_status', fn ($f) => (int) $f[0]);
        $filter->title(__('Status:'));
        $filter->options(array_merge(
            ['-' => ''],
            App::core()->combo()->getCommentStatusesCombo()
        ));
        $filter->prime(true);

        return $filter;
    }

    /**
     * Common IP field.
     */
    public function getCommentIpFilter(): ?Filter
    {
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            return null;
        }

        $filter = new Filter('ip');
        $filter->param('comment_ip');
        $filter->form('input');
        $filter->title(__('IP address:'));

        return $filter;
    }
}
