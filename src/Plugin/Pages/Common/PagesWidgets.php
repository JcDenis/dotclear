<?php
/**
 * @note Dotclear\Plugin\Pages\Common\PagesWidgets
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Common;

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

    public function initWidgets(Widgets $widgets): void
    {
        $widgets
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
            ->addOffline()
        ;
    }

    public function initDefaultWidgets(Widgets $widgets, array $default): void
    {
        $default['nav']->append($widgets->get('pages'));
    }

    public function pagesWidget(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(dotclear()->url()->type)) {
            return '';
        }

        $params['post_type']     = 'page';
        $params['no_content']    = true;
        $params['post_selected'] = false;

        $sort = $widget->get('sortby');
        if (!in_array($sort, ['post_title', 'post_position', 'post_dt'])) {
            $sort = 'post_title';
        }

        $order = $widget->get('orderby');
        if ('asc' != $order) {
            $order = 'desc';
        }
        $params['order'] = $sort . ' ' . $order;

        if (abs((int) $widget->get('limit'))) {
            $params['limit'] = abs((int) $widget->get('limit'));
        }

        $rs = dotclear()->blog()->posts()->getPosts($params);

        if ($rs->isEmpty()) {
            return '';
        }

        $res = '<ul>';

        while ($rs->fetch()) {
            $class = '';
            if ('pages' == dotclear()->url()->type
                && dotclear()->context()->get('posts') instanceof Record
                && dotclear()->context()->get('posts')->fInt('post_id') === $rs->fInt('post_id')
            ) {
                $class = ' class="page-current"';
            }
            $res .= '<li' . $class . '><a href="' . $rs->getURL() . '">' .
            Html::escapeHTML($rs->f('post_title')) . '</a></li>';
        }

        $res .= '</ul>';

        return $widget->renderDiv($widget->get('content_only'), 'pages ' . $widget->get('class'), '', $widget->renderTitle() . $res);
    }
}
