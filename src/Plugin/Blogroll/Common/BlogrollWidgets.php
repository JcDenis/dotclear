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

use Dotclear\Html\Html;
use Dotclear\Plugin\Blogroll\Common\Blogroll;
use Dotclear\Plugin\Blogroll\Public\BlogrollTemplate;
use Dotclear\Plugin\Widgets\Common\Widget;
use Dotclear\Plugin\Widgets\Common\Widgets;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class BlogrollWidgets
{
    public static function initBlogroll()
    {
        dotclear()->behavior()->add('initWidgets', [__CLASS__, 'initWidgets']);
        dotclear()->behavior()->add('initDefaultWidgets', [__CLASS__, 'initDefaultWidgets']);
    }

    public static function initWidgets(Widgets $w): void
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

        $w
            ->create('links', __('Blogroll'), [__CLASS__, 'linksWidget'], null, 'Blogroll list')
            ->addTitle(__('Links'))
            ->setting('category', __('Category'), '', 'combo', $categories)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    public static function initDefaultWidgets(Widgets $w, array $d): void
    {
        $d['extra']->append($w->links);
    }

    public static function linksWidget(Widget $w): string
    {
        if ($w->offline) {
            return '';
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return '';
        }

        $links = BlogrollTemplate::getList($w->renderSubtitle('', false), '<ul>%s</ul>', '<li%2$s>%1$s</li>', $w->category);

        if (empty($links)) {
            return '';
        }

        return $w->renderDiv(
            $w->content_only,
            'links ' . $w->class,
            '',
            ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') .
            $links
        );
    }
}
