<?php
/**
 * @class Dotclear\Plugin\Tags\Lib\TagsWidgets
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Lib;

use ArrayObject;

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
        $class = 'Dotclear\\Plugin\\Tags\Lib\\TagsTemplate';

        $w
            ->create('tags', __('Tags'), [$class, 'tagsWidget'], null, 'Tags cloud')
            ->addTitle(__('Menu'))
            ->setting('limit', __('Limit (empty means no limit):'), '20')
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
}
