<?php
/**
 * @class Dotclear\Plugin\Blogroll\Lib\BlogrollWidgets
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBlogroll
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Lib;

use ArrayObject;

use Dotclear\Plugin\Blogroll\Lib\Blogroll;
use Dotclear\Plugin\Widgets\Lib\Widgets;

class BlogrollWidgets
{
    public function __construct()
    {
        dotclear()->behavior()->add('initWidgets', [$this, 'initWidgets']);
        dotclear()->behavior()->add('initDefaultWidgets', [$this, 'initDefaultWidgets']);
    }

    public function initWidgets(Widgets $w): void
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

        $class = 'Dotclear\\Plugin\\Blogroll\\Lib\\BlogrollTemplate';

        $w
            ->create('links', __('Blogroll'), [$class, 'linksWidget'], null, 'Blogroll list')
            ->addTitle(__('Links'))
            ->setting('category', __('Category'), '', 'combo', $categories)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    public function initDefaultWidgets(Widgets $w, array $d): void
    {
        $d['extra']->append($w->links);
    }
}
