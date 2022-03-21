<?php
/**
 * @class Dotclear\Plugin\Pages\Common\PagesWidgets
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Common;

use ArrayObject;

use Dotclear\Database\Record;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\Widgets\Common\Widget;
use Dotclear\Plugin\Widgets\Common\Widgets;

class PagesWidgets
{
    public function __construct()
    {
        dotclear()->behavior()->add('initWidgets', [$this, 'initWidgets']);
        dotclear()->behavior()->add('initDefaultWidgets', [$this, 'initDefaultWidgets']);
    }

    public function initWidgets(Widgets $w): void
    {
        $w
            ->create('pages', __('Pages'), [$this, 'pagesWidget'], null, 'List of published pages')
            ->addTitle(__('Pages'))
            ->setting('limit', __('Limit (empty means no limit):'), '10', 'number')
            ->setting(
                'sortby',
                __('Order by:'),
                'post_title',
                'combo',
                [
                    __('Page title')       => 'post_title',
                    __('Page position')    => 'post_position',
                    __('Publication date') => 'post_dt',
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
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    public function initDefaultWidgets(Widgets $w, array $d): void
    {
        $d['nav']->append($w->pages);
    }

    public function pagesWidget(Widget $w): string
    {
        if ($w->offline) {
            return '';
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return '';
        }

        $params['post_type']     = 'page';
        $params['no_content']    = true;
        $params['post_selected'] = false;

        $sort = $w->sortby;
        if (!in_array($sort, ['post_title', 'post_position', 'post_dt'])) {
            $sort = 'post_title';
        }

        $order = $w->orderby;
        if ($order != 'asc') {
            $order = 'desc';
        }
        $params['order'] = $sort . ' ' . $order;

        if (abs((int) $w->limit)) {
            $params['limit']         = abs((int) $w->limit);
        }

        $rs = dotclear()->blog()->posts()->getPosts($params);

        if ($rs->isEmpty()) {
            return '';
        }

        $res = ($w->title ? $w->renderTitle(html::escapeHTML($w->title)) : '') . '<ul>';

        while ($rs->fetch()) {
            $class = '';
            if (dotclear()->url()->type == 'pages'
                && dotclear()->context()->posts instanceof Record
                && dotclear()->context()->posts->post_id == $rs->post_id
            ) {
                $class = ' class="page-current"';
            }
            $res .= '<li' . $class . '><a href="' . $rs->getURL() . '">' .
            Html::escapeHTML($rs->post_title) . '</a></li>';
        }

        $res .= '</ul>';

        return $w->renderDiv($w->content_only, 'pages ' . $w->class, '', $res);
    }
}
