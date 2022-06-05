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
use Dotclear\App;
use Dotclear\Database\Param;
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
        App::core()->behavior()->add('initWidgets', [$this, 'initWidgets']);
        App::core()->behavior()->add('initDefaultWidgets', [$this, 'initDefaultWidgets']);
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
        if ($widget->isOffline() || !$widget->checkHomeOnly()) {
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

        $param = new Param();
        $param->set('meta_type', 'tag');

        if ('meta_id_lower' != $sort) {
            // As optional limit may restrict result, we should set order (if not computed after)
            $param->set('order', $sort . ' ' . ('asc' == $order ? 'ASC' : 'DESC'));
        }

        if (abs((int) $widget->get('limit'))) {
            $param->set('limit', abs((int) $widget->get('limit')));
        }

        $rs = App::core()->meta()->computeMetaStats(
            App::core()->meta()->getMetadata(param: $param)
        );

        if ($rs->isEmpty()) {
            return '';
        }

        if ('meta_id_lower' == $sort) {
            // Sort resulting recordset on cleaned id
            $rs->sort($sort, $order);
        }

        $res = '<ul>';

        if ('post' == App::core()->url()->getCurrentType() && App::core()->context()->get('posts') instanceof Record) {
            App::core()->context()->set('meta', App::core()->meta()->getMetaRecordset((string) App::core()->context()->get('posts')->f('post_meta'), 'tag'));
        }
        while ($rs->fetch()) {
            $class = '';
            if ('post' == App::core()->url()->getCurrentType() && App::core()->context()->get('posts') instanceof Record) {
                while (App::core()->context()->get('meta')->fetch()) {
                    if (App::core()->context()->get('meta')->f('meta_id') == $rs->f('meta_id')) {
                        $class = ' class="tag-current"';

                        break;
                    }
                }
            }
            $res .= '<li' . $class . '><a href="' . App::core()->blog()->getURLFor('tag', rawurlencode($rs->f('meta_id'))) . '" ' .
            'class="tag' . $rs->f('roundpercent') . '">' .
            $rs->f('meta_id') . '</a> </li>';
        }

        $res .= '</ul>';

        if (App::core()->url()->getURLFor('tags') && !is_null($widget->get('alltagslinktitle')) && '' !== $widget->get('alltagslinktitle')) {
            $res .= '<p><strong><a href="' . App::core()->blog()->getURLFor('tags') . '">' .
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
