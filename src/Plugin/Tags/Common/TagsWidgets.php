<?php
/**
 * @class Dotclear\Plugin\Tags\Common\TagsWidgets
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Common;

use ArrayObject;

use Dotclear\Html\Html;
use Dotclear\Plugin\Widgets\Lib\Widgets;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class TagsWidgets
{
    public static function initTags()
    {
        dotclear()->behavior()->add('initWidgets', [__CLASS__, 'initWidgets']);
        dotclear()->behavior()->add('initDefaultWidgets', [__CLASS__, 'initDefaultWidgets']);
    }

    public static function initWidgets(Widgets $w): void
    {
        $w
            ->create('tags', __('Tags'), [__CLASS__, 'tagsWidget'], null, 'Tags cloud')
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
            ->addOffline();
    }

    public static function initDefaultWidgets(Widgets $w, array $d): void
    {
        $d['nav']->append($w->tags);
    }

    public static function tagsWidget($w)
    {
        if ($w->offline) {
            return;
        }

        if (($w->homeonly == 1 && !dotclear()->url()->isHome(dotclear()->url()->type))
            || ($w->homeonly == 2 && dotclear()->url()->isHome(dotclear()->url()->type))
        ) {
            return;
        }

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sort = $w->sortby;
        if (!in_array($sort, $combo)) {
            $sort = 'meta_id_lower';
        }

        $order = $w->orderby;
        if ($order != 'asc') {
            $order = 'desc';
        }

        $params = ['meta_type' => 'tag'];

        if ($sort != 'meta_id_lower') {
            // As optional limit may restrict result, we should set order (if not computed after)
            $params['order'] = $sort . ' ' . ($order == 'asc' ? 'ASC' : 'DESC');
        }

        if (abs((int) $w->limit)) {
            $params['limit'] = abs((int) $w->limit);
        }

        $rs = dotclear()->meta()->computeMetaStats(
            dotclear()->meta()->getMetadata($params)
        );

        if ($rs->isEmpty()) {
            return;
        }

        if ($sort == 'meta_id_lower') {
            // Sort resulting recordset on cleaned id
            $rs->sort($sort, $order);
        }

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') .
            '<ul>';

        if (dotclear()->url()->type == 'post' && dotclear()->context()->posts instanceof Record) {
            dotclear()->context()->meta = dotclear()->meta()->getMetaRecordset((string) dotclear()->context()->posts->post_meta, 'tag');
        }
        while ($rs->fetch()) {
            $class = '';
            if (dotclear()->url()->type == 'post' && dotclear()->context()->posts instanceof Record) {
                while (dotclear()->context()->meta->fetch()) {
                    if (dotclear()->context()->meta->meta_id == $rs->meta_id) {
                        $class = ' class="tag-current"';

                        break;
                    }
                }
            }
            $res .= '<li' . $class . '><a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('tag', rawurlencode($rs->meta_id)) . '" ' .
            'class="tag' . $rs->roundpercent . '">' .
            $rs->meta_id . '</a> </li>';
        }

        $res .= '</ul>';

        if (dotclear()->url()->getURLFor('tags') && !is_null($w->alltagslinktitle) && $w->alltagslinktitle !== '') {
            $res .= '<p><strong><a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('tags') . '">' .
            Html::escapeHTML($w->alltagslinktitle) . '</a></strong></p>';
        }

        return $w->renderDiv($w->content_only, 'tags ' . $w->class, '', $res);
    }
}
