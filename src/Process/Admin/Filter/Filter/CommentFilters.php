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
        parent::__construct(id: 'comments', filters: new FilterStack(
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
        return new Filter(
            id: 'author',
            type: 'input',
            title: __('Author:'),
            params: [
                ['q_author', null],
            ]
        );
    }

    /**
     * Comment type select.
     */
    public function getCommentTypeFilter(): Filter
    {
        return new Filter(
            id: 'type',
            title: __('Type:'),
            prime: true,
            params: [
                ['comment_trackback', fn ($f) => 'tb' == $f[0]],
            ],
            options: [
                '-'             => '',
                __('Comment')   => 'co',
                __('Trackback') => 'tb',
            ]
        );
    }

    /**
     * Comment status select.
     */
    public function getCommentStatusFilter(): Filter
    {
        return new Filter(
            id: 'status',
            title: __('Status:'),
            prime: true,
            params : [
                ['comment_status', fn ($f) => (int) $f[0]],
            ],
            options: array_merge(
                ['-' => ''],
                App::core()->combo()->getCommentStatusesCombo()
            )
        );
    }

    /**
     * Common IP field.
     */
    public function getCommentIpFilter(): ?Filter
    {
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            return null;
        }

        return new Filter(
            id: 'ip',
            type: 'input',
            title: __('IP address:'),
            params: [
                ['comment_ip', null],
            ]
        );
    }
}
