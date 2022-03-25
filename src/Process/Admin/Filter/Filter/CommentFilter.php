<?php
/**
 * @class Dotclear\Process\Admin\Filter\Filter\CommentFilter
 * @brief class for admin comment list filters form
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
            $this->getInputFilter('site', __('Web site:'), 'comment_site')
        ]);

        # --BEHAVIOR-- adminCommentFilter
        dotclear()->behavior()->call('adminCommentFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
    }

    /**
     * Comment author select
     */
    public function getCommentAuthorFilter(): DefaultFilter
    {
        return (new DefaultFilter('author'))
            ->param('q_author')
            ->form('input')
            ->title(__('Author:'));
    }

    /**
     * Comment type select
     */
    public function getCommentTypeFilter(): DefaultFilter
    {
        return (new DefaultFilter('type'))
            ->param('comment_trackback', [$this, 'getCommentTypeParam'])
            ->title(__('Type:'))
            ->options([
                '-'             => '',
                __('Comment')   => 'co',
                __('Trackback') => 'tb'
            ])
            ->prime(true);
    }

    public function getCommentTypeParam($f): bool
    {
        return $f[0] == 'tb';
    }

    /**
     * Comment status select
     */
    public function getCommentStatusFilter(): DefaultFilter
    {
        return (new DefaultFilter('status'))
            ->param('comment_status')
            ->title(__('Status:'))
            ->options(array_merge(
                ['-' => ''],
                dotclear()->combo()->getCommentStatusesCombo()
            ))
            ->prime(true);
    }

    /**
     * Common IP field
     */
    public function getCommentIpFilter(): ?DefaultFilter
    {
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            return null;
        }

        return (new DefaultFilter('ip'))
            ->param('comment_ip')
            ->form('input')
            ->title(__('IP address:'));
    }
}