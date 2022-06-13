<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Common;

// Dotclear\Plugin\Pages\Common\PagesWidgets
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\Widgets\Common\Widget;
use Dotclear\Plugin\Widgets\Common\Widgets;

/**
 * Widgets methods for plugin Pages.
 *
 * @ingroup  Plugin Pages Widgets
 */
class PagesWidgets
{
    public function __construct()
    {
        App::core()->behavior()->add('initWidgets', [$this, 'initWidgets']);
        App::core()->behavior()->add('initDefaultWidgets', [$this, 'initDefaultWidgets']);
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
        if ($widget->isOffline() || !$widget->checkHomeOnly()) {
            return '';
        }

        $param = new Param();
        $param->set('post_type', 'page');
        $param->set('no_content', true);
        $param->set('post_selected', false);

        $sort = $widget->get('sortby');
        if (!in_array($sort, ['post_title', 'post_position', 'post_dt'])) {
            $sort = 'post_title';
        }

        $order = $widget->get('orderby');
        if ('asc' != $order) {
            $order = 'desc';
        }
        $param->set('order', $sort . ' ' . $order);

        if (abs((int) $widget->get('limit'))) {
            $param->set('limit', abs((int) $widget->get('limit')));
        }

        $rs = App::core()->blog()->posts()->getPosts(param: $param);

        if ($rs->isEmpty()) {
            return '';
        }

        $res = '<ul>';

        while ($rs->fetch()) {
            $class = '';
            if ('pages' == App::core()->url()->getCurrentType()
                && App::core()->context()->get('posts') instanceof Record
                && App::core()->context()->get('posts')->integer('post_id') === $rs->integer('post_id')
            ) {
                $class = ' class="page-current"';
            }
            $res .= '<li' . $class . '><a href="' . $rs->getURL() . '">' .
            Html::escapeHTML($rs->field('post_title')) . '</a></li>';
        }

        $res .= '</ul>';

        return $widget->renderDiv($widget->get('content_only'), 'pages ' . $widget->get('class'), '', $widget->renderTitle() . $res);
    }
}
