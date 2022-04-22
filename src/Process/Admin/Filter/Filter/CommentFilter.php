<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\CommentFilter
use ArrayObject;
use Dotclear\Process\Admin\Filter\Filter;

/**
 * Admin comments list filters form.
 *
 * @ingroup  Admin Comment Filter
 */
class CommentFilter extends Filter
{
    public function __construct()
    {
        parent::__construct('comments');

        $filters = new ArrayObject([
            $this->getPageFilter(),
            $this->getCommentAuthorFilter(),
            $this->getCommentTypeFilter(),
            $this->getCommentStatusFilter(),
            $this->getCommentIpFilter(),
            $this->getInputFilter('email', __('Email:'), 'comment_email'),
            $this->getInputFilter('site', __('Web site:'), 'comment_site'),
        ]);

        // --BEHAVIOR-- adminCommentFilter
        dotclear()->behavior()->call('adminCommentFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
    }

    /**
     * Comment author select.
     */
    public function getCommentAuthorFilter(): DefaultFilter
    {
        return DefaultFilter::init('author')
            ->param('q_author')
            ->form('input')
            ->title(__('Author:'))
        ;
    }

    /**
     * Comment type select.
     */
    public function getCommentTypeFilter(): DefaultFilter
    {
        return DefaultFilter::init('type')
            ->param('comment_trackback', [$this, 'getCommentTypeParam'])
            ->title(__('Type:'))
            ->options([
                '-'             => '',
                __('Comment')   => 'co',
                __('Trackback') => 'tb',
            ])
            ->prime(true)
        ;
    }

    public function getCommentTypeParam($f): bool
    {
        return 'tb' == $f[0];
    }

    /**
     * Comment status select.
     */
    public function getCommentStatusFilter(): DefaultFilter
    {
        return DefaultFilter::init('status')
            ->param('comment_status')
            ->title(__('Status:'))
            ->options(array_merge(
                ['-' => ''],
                dotclear()->combo()->getCommentStatusesCombo()
            ))
            ->prime(true)
        ;
    }

    /**
     * Common IP field.
     */
    public function getCommentIpFilter(): ?DefaultFilter
    {
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            return null;
        }

        return DefaultFilter::init('ip')
            ->param('comment_ip')
            ->form('input')
            ->title(__('IP address:'))
        ;
    }
}
