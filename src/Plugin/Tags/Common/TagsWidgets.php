<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Common;

// Dotclear\Plugin\Tags\Common\TagsWidgets
use Dotclear\Database\Record;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\Widgets\Common\Widget;
use Dotclear\Plugin\Widgets\Common\Widgets;

/**
 * Widgets methods of plugin Tags.
 *
 * @ingroup  Plugin Tags Widgets
 */
class TagsWidgets
{
    public function __construct()
    {
        dotclear()->behavior()->add('initWidgets', [$this, 'initWidgets']);
        dotclear()->behavior()->add('initDefaultWidgets', [$this, 'initDefaultWidgets']);
    }

    public function initWidgets(Widgets $w): void
    {
        $w
            ->create('tags', __('Tags'), [$this, 'tagsWidget'], null, 'Tags cloud')
            ->addTitle(__('Tags'))
            ->setting('limit', __('Limit (empty means no limit):'), '20', 'number')
            ->setting(
                'sortby',
                __('Order by:'),
                'meta_id_lower',
                'combo',
                [
                    __('Tag name')      => 'meta_id_lower',
                    __('Entries count') => 'count',
                    __('Newest entry')  => 'latest',
                    __('Oldest entry')  => 'oldest',
                ]
            )
            ->setting(
                'orderby',
                __('Sort:'),
                'asc',
                'combo',
                [
                    __('Ascending')  => 'asc',
                    __('Descending') => 'desc',
                ]
            )
            ->setting('alltagslinktitle', __('Link to all tags:'), __('All tags'))
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;
    }

    public function initDefaultWidgets(Widgets $w, array $d): void
    {
        $d['nav']->append($w->get('tags'));
    }

    public function tagsWidget(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(dotclear()->url()->type)) {
            return '';
        }

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sort = $widget->get('sortby');
        if (!in_array($sort, $combo)) {
            $sort = 'meta_id_lower';
        }

        $order = $widget->get('orderby');
        if ('asc' != $order) {
            $order = 'desc';
        }

        $params = ['meta_type' => 'tag'];

        if ('meta_id_lower' != $sort) {
            // As optional limit may restrict result, we should set order (if not computed after)
            $params['order'] = $sort . ' ' . ('asc' == $order ? 'ASC' : 'DESC');
        }

        if (abs((int) $widget->get('limit'))) {
            $params['limit'] = abs((int) $widget->get('limit'));
        }

        $rs = dotclear()->meta()->computeMetaStats(
            dotclear()->meta()->getMetadata($params)
        );

        if ($rs->isEmpty()) {
            return '';
        }

        if ('meta_id_lower' == $sort) {
            // Sort resulting recordset on cleaned id
            $rs->sort($sort, $order);
        }

        $res = '<ul>';

        if ('post' == dotclear()->url()->type && dotclear()->context()->get('posts') instanceof Record) {
            dotclear()->context()->set('meta', dotclear()->meta()->getMetaRecordset((string) dotclear()->context()->get('posts')->f('post_meta'), 'tag'));
        }
        while ($rs->fetch()) {
            $class = '';
            if ('post' == dotclear()->url()->type && dotclear()->context()->get('posts') instanceof Record) {
                while (dotclear()->context()->get('meta')->fetch()) {
                    if (dotclear()->context()->get('meta')->f('meta_id') == $rs->f('meta_id')) {
                        $class = ' class="tag-current"';

                        break;
                    }
                }
            }
            $res .= '<li' . $class . '><a href="' . dotclear()->blog()->getURLFor('tag', rawurlencode($rs->f('meta_id'))) . '" ' .
            'class="tag' . $rs->f('roundpercent') . '">' .
            $rs->f('meta_id') . '</a> </li>';
        }

        $res .= '</ul>';

        if (dotclear()->url()->getURLFor('tags') && !is_null($widget->get('alltagslinktitle')) && '' !== $widget->get('alltagslinktitle')) {
            $res .= '<p><strong><a href="' . dotclear()->blog()->getURLFor('tags') . '">' .
            Html::escapeHTML($widget->get('alltagslinktitle')) . '</a></strong></p>';
        }

        return $widget->renderDiv(
            $widget->get('content_only'),
            'tags ' . $widget->get('class'),
            '',
            $widget->renderTitle() . $res
        );
    }
}
