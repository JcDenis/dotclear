<?php
/**
 * @class Dotclear\Plugin\Blogroll\Common\BlogrollWidgets
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBlogroll
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Common;

use ArrayObject;

use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\Blogroll\Common\Blogroll;
use Dotclear\Plugin\Blogroll\Public\BlogrollTemplate;
use Dotclear\Plugin\Widgets\Common\Widget;
use Dotclear\Plugin\Widgets\Common\Widgets;

class BlogrollWidgets
{
    public function __construct()
    {
        dotclear()->behavior()->add('initWidgets', [$this, 'initWidgets']);
        dotclear()->behavior()->add('initDefaultWidgets', [$this, 'initDefaultWidgets']);
    }

    public function initWidgets(Widgets $widgets): void
    {
        $br         = new Blogroll();
        $h          = $br->getLinksHierarchy($br->getLinks());
        $h          = array_keys($h);
        $categories = [__('All categories') => ''];
        foreach ($h as $v) {
            if ($v) {
                $categories[$v] = $v;
            }
        }
        unset($br, $h);

        $widgets
            ->create('links', __('Blogroll'), [$this, 'linksWidget'], null, 'Blogroll list')
            ->addTitle(__('Links'))
            ->setting('category', __('Category'), '', 'combo', $categories)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    public function initDefaultWidgets(Widgets $widgets, array $default): void
    {
        $default['extra']->append($widgets->get('links'));
    }

    public function linksWidget(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(dotclear()->url()->type)) {
            return '';
        }

        $links = BlogrollTemplate::getList($widget->renderSubtitle('', false), '<ul>%s</ul>', '<li%2$s>%1$s</li>', $widget->get('category'));

        if (empty($links)) {
            return '';
        }

        return $widget->renderDiv(
            $widget->get('content_only'),
            'links ' . $widget->get('class'),
            '',
            $widget->renderTitle() .
            $links
        );
    }
}
