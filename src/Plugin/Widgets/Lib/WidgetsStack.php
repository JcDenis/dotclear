<?php
/**
 * @class Dotclear\Plugin\Widgets\Lib\WidgetsStack
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Lib;

use Dotclear\Core\Core;
use Dotclear\Plugin\Widgets\Lib\Widgets;
use Dotclear\Plugin\Widgets\Lib\DefaultWidgets;
use Dotclear\Plugin\Widgets\Lib\Widget;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class WidgetsStack
{
    public static $__widgets;
    public static $__default_widgets;

    private static function wns()
    {
        return __NAMESPACE__ . '\\DefaultWidgets';
    }

    public static function initWidgets(Core $core)
    {
        DefaultWidgets::setCore($core);
        Widget::setCore($core);

        $__widgets = new Widgets();

        $__widgets
            ->create('search', __('Search engine'), [self::wns(), 'search'], null, 'Search engine form')
            ->addTitle(__('Search'))
            ->setting('placeholder', __('Placeholder (HTML5 only, optional):'), '')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('navigation', __('Navigation links'), [self::wns(), 'navigation'], null, 'List of navigation links')
            ->addTitle()
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('bestof', __('Selected entries'), [self::wns(), 'bestof'], null, 'List of selected entries')
            ->addTitle(__('Best of me'))
            ->setting('orderby', __('Sort:'), 'asc', 'combo', [__('Ascending') => 'asc', __('Descending') => 'desc'])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('langs', __('Blog languages'), [self::wns(), 'langs'], null, 'List of available languages')
            ->addTitle(__('Languages'))
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('categories', __('List of categories'), [self::wns(), 'categories'], null, 'List of categories')
            ->addTitle(__('Categories'))
            ->setting('postcount', __('With entries counts'), 0, 'check')
            ->setting('subcatscount', __('Include sub cats in count'), false, 'check')
            ->setting('with_empty', __('Include empty categories'), 0, 'check')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('subscribe', __('Subscribe links'), [self::wns(), 'subscribe'], null, 'Feed subscription links (RSS or Atom)')
            ->addTitle(__('Subscribe'))
            ->setting('type', __('Feeds type:'), 'atom', 'combo', ['Atom' => 'atom', 'RSS' => 'rss2'])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets->
            create('feed', __('Feed reader'), [self::wns(), 'feed'], null, 'List of last entries from feed (RSS or Atom)')
            ->addTitle(__('Somewhere else'))
            ->setting('url', __('Feed URL:'), '')
            ->setting('limit', __('Entries limit:'), 10)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('text', __('Text'), [self::wns(), 'text'], null, 'Simple text')
            ->addTitle()
            ->setting('text', __('Text:'), '', 'textarea')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $rs         = $core->blog->getCategories(['post_type' => 'post']);
        $categories = ['' => '', __('Uncategorized') => 'null'];
        while ($rs->fetch()) {
            $categories[str_repeat('&nbsp;&nbsp;', $rs->level - 1) . ($rs->level - 1 == 0 ? '' : '&bull; ') . html::escapeHTML($rs->cat_title)] = $rs->cat_id;
        }
        $w = $__widgets->create('lastposts', __('Last entries'), [self::wns(), 'lastposts'], null, 'List of last entries published');
        $w
            ->addTitle(__('Last entries'))
            ->setting('category', __('Category:'), '', 'combo', $categories);
        if ($core->plugins->hasModule('tags')) {
            $w->setting('tag', __('Tag:'), '');
        }
        $w
            ->setting('limit', __('Entries limit:'), 10)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
        unset($rs, $categories, $w);

        $__widgets
            ->create('lastcomments', __('Last comments'), [self::wns(), 'lastcomments'], null, 'List of last comments published')
            ->addTitle(__('Last comments'))
            ->setting('limit', __('Comments limit:'), 10)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        # --BEHAVIOR-- initWidgets
        $core->behaviors->call('initWidgets', $__widgets);

        $__default_widgets = ['nav' => new Widgets(), 'extra' => new Widgets(), 'custom' => new Widgets()];

        $__default_widgets['nav']->append($__widgets->search);
        $__default_widgets['nav']->append($__widgets->bestof);
        $__default_widgets['nav']->append($__widgets->categories);
        $__default_widgets['custom']->append($__widgets->subscribe);

        # --BEHAVIOR-- initDefaultWidgets
        $core->behaviors->call('initDefaultWidgets', $__widgets, $__default_widgets);

        self::$__widgets = $__widgets;
        self::$__default_widgets = $__default_widgets;
    }
}
