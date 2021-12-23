<?php
/**
 * @class Dotclear\Admin\Filter\CommentFilter
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

namespace Dotclear\Admin\Filter;

use Dotclear\Core\Core;

use Dotclear\Admin\Filter;
use Dotclear\Admin\Filters;
use Dotclear\Admin\Combos;
use Dotclear\Admin\Filter\DefaultFilter;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class CommentFilter extends Filter
{
    public function __construct(Core $core)
    {
        parent::__construct($core, 'comments');

        $filters = new \arrayObject([
            Filters::getPageFilter(),
            $this->getCommentAuthorFilter(),
            $this->getCommentTypeFilter(),
            $this->getCommentStatusFilter(),
            $this->getCommentIpFilter(),
            Filters::getInputFilter('email', __('Email:'), 'comment_email'),
            Filters::getInputFilter('site', __('Web site:'), 'comment_site')
        ]);

        # --BEHAVIOR-- adminCommentFilter
        $core->callBehavior('adminCommentFilter', $core, $filters);

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
            ->param('comment_trackback', ['adminCommentFilter', 'getCommentTypeParam'])
            ->title(__('Type:'))
            ->options([
                '-'             => '',
                __('Comment')   => 'co',
                __('Trackback') => 'tb'
            ])
            ->prime(true);
    }

    public static function getCommentTypeParam($f)
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
                Combos::getCommentStatusesCombo()
            ))
            ->prime(true);
    }

    /**
     * Common IP field
     */
    public function getCommentIpFilter(): ?DefaultFilter
    {
        if (!$this->core->auth->check('contentadmin', $this->core->blog->id)) {
            return null;
        }

        return (new DefaultFilter('ip'))
            ->param('comment_ip')
            ->form('input')
            ->title(__('IP address:'));
    }
}
