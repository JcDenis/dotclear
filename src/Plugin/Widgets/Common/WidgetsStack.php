<?php
/**
 * @class Dotclear\Plugin\Widgets\Common\WidgetsStack
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Common;

use Dotclear\Plugin\Widgets\Common\Widgets;
use Dotclear\Plugin\Widgets\Common\Widget;

use Dotclear\Helper\Html\Html;

class WidgetsStack
{
    public static $stack;
    public static $__widgets;
    public static $__default_widgets;

    public function __construct()
    {
        $this->initWidgets();
        self::$stack = $this;

        if (dotclear()->processed('Public')) {
            dotclear()->template()->addValue('Widgets', [$this, 'tplWidgets']);
            dotclear()->template()->addBlock('Widget', [$this, 'tplWidget']);
            dotclear()->template()->addBlock('IfWidgets', [$this, 'tplIfWidgets']);
        }
    }

    /// @name Widgets page methods
    //@{
    public function search($w)
    {
        if (dotclear()->blog()->settings()->get('system')->get('no_search')) {
            return;
        }

        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $value = isset($GLOBALS['_search']) ? Html::escapeHTML($GLOBALS['_search']) : '';

        return $w->renderDiv($w->content_only, $w->class, 'id="search"',
            ($w->title ? $w->renderTitle('<label for="q">' . Html::escapeHTML($w->title) . '</label>') : '') .
            '<form action="' . dotclear()->blog()->url . '" method="get" role="search">' .
            '<p><input type="text" size="10" maxlength="255" id="q" name="q" value="' . $value . '" ' .
            ($w->placeholder ? 'placeholder="' . Html::escapeHTML($w->placeholder) . '"' : '') .
            ' aria-label="' . __('Search') . '"/> ' .
            '<input type="submit" class="submit" value="ok" title="' . __('Search') . '" /></p>' .
            '</form>');
    }

    public function navigation($w)
    {
        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') .
            '<nav role="navigation"><ul>';

        if (!dotclear()->url()->isHome(dotclear()->url()->type)) {
            // Not on home page (standard or static), add home link
            $res .= '<li class="topnav-home">' .
            '<a href="' . dotclear()->blog()->url . '">' . __('Home') . '</a></li>';
            if (dotclear()->blog()->settings()->get('system')->get('static_home')) {
                // Static mode: add recent posts link
                $res .= '<li class="topnav-posts">' .
                '<a href="' . dotclear()->blog()->getURLFor('posts') . '">' . __('Recent posts') . '</a></li>';
            }
        } else {
            // On home page (standard or static)
            if (dotclear()->blog()->settings()->get('system')->get('static_home')) {
                // Static mode: add recent posts link
                $res .= '<li class="topnav-posts">' .
                '<a href="' . dotclear()->blog()->getURLFor('posts') . '">' . __('Recent posts') . '</a></li>';
            }
        }

        $res .= '<li class="topnav-arch">' .
        '<a href="' . dotclear()->blog()->getURLFor('archive') . '">' .
        __('Archives') . '</a></li>' .
            '</ul></nav>';

        return $w->renderDiv($w->content_only, $w->class, 'id="topnav"', $res);
    }

    public function categories($w)
    {
        $context = dotclear()->context();

        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $rs = dotclear()->blog()->categories()->getCategories(['post_type' => 'post', 'without_empty' => !$w->with_empty]);
        if ($rs->isEmpty()) {
            return;
        }

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '');

        $ref_level = $level = $rs->level - 1;
        while ($rs->fetch()) {
            $class = '';
            if ((dotclear()->url()->type == 'category' && $context->categories instanceof record && $context->categories->cat_id == $rs->cat_id)
                || (dotclear()->url()->type == 'post' && $context->posts instanceof record && $context->posts->cat_id == $rs->cat_id)) {
                $class = ' class="category-current"';
            }

            if ($rs->level > $level) {
                $res .= str_repeat('<ul><li' . $class . '>', $rs->level - $level);
            } elseif ($rs->level < $level) {
                $res .= str_repeat('</li></ul>', -($rs->level - $level));
            }

            if ($rs->level <= $level) {
                $res .= '</li><li' . $class . '>';
            }

            $res .= '<a href="' . dotclear()->blog()->getURLFor('category', $rs->cat_url) . '">' .
            Html::escapeHTML($rs->cat_title) . '</a>' .
                ($w->postcount ? ' <span>(' . ($w->subcatscount ? $rs->nb_total : $rs->nb_post) . ')</span>' : '');

            $level = $rs->level;
        }

        if ($ref_level - $level < 0) {
            $res .= str_repeat('</li></ul>', -($ref_level - $level));
        }

        return $w->renderDiv($w->content_only, 'categories ' . $w->class, '', $res);
    }

    public function bestof($w)
    {
        $context = dotclear()->context();

        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $params = [
            'post_selected' => true,
            'no_content'    => true,
            'order'         => 'post_dt ' . strtoupper($w->orderby)
        ];

        $rs = dotclear()->blog()->posts()->getPosts($params);

        if ($rs->isEmpty()) {
            return;
        }

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') .
            '<ul>';

        while ($rs->fetch()) {
            $class = '';
            if (dotclear()->url()->type == 'post' && $context->posts instanceof record && $context->posts->post_id == $rs->post_id) {
                $class = ' class="post-current"';
            }
            $res .= ' <li' . $class . '><a href="' . $rs->getURL() . '">' . Html::escapeHTML($rs->post_title) . '</a></li> ';
        }

        $res .= '</ul>';

        return $w->renderDiv($w->content_only, 'selected ' . $w->class, '', $res);
    }

    public function langs($w)
    {
        $context = dotclear()->context();

        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $rs = dotclear()->blog()->posts()->getLangs();

        if ($rs->count() <= 1) {
            return;
        }

        $langs = L10n::getISOcodes();
        $res   = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') .
            '<ul>';

        while ($rs->fetch()) {
            $l = ($context->cur_lang == $rs->post_lang) ? '<strong>%s</strong>' : '%s';

            $lang_name = $langs[$rs->post_lang] ?? $rs->post_lang;

            $res .= ' <li>' .
            sprintf($l,
                '<a href="' . dotclear()->blog()->getURLFor('lang', $rs->post_lang) . '" ' .
                'class="lang-' . $rs->post_lang . '">' .
                $lang_name . '</a>') .
                ' </li>';
        }

        $res .= '</ul>';

        return $w->renderDiv($w->content_only, 'langs ' . $w->class, '', $res);
    }

    public function subscribe($w)
    {
        $context = dotclear()->context();

        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $type = ($w->type == 'atom' || $w->type == 'rss2') ? $w->type : 'rss2';
        $mime = $type == 'rss2' ? 'application/rss+xml' : 'application/atom+xml';
        if ($context->exists('cur_lang')) {
            $type = $context->cur_lang . '/' . $type;
        }

        $p_title = __('This blog\'s entries %s feed');
        $c_title = __('This blog\'s comments %s feed');

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') .
            '<ul>';

        $res .= '<li><a type="' . $mime . '" ' .
        'href="' . dotclear()->blog()->getURLFor('feed', $type) . '" ' .
        'title="' . sprintf($p_title, ($type == 'atom' ? 'Atom' : 'RSS')) . '" class="feed">' .
        __('Entries feed') . '</a></li>';

        if (dotclear()->blog()->settings()->get('system')->get('allow_comments') || dotclear()->blog()->settings()->get('system')->get('allow_trackbacks')) {
            $res .= '<li><a type="' . $mime . '" ' .
            'href="' . dotclear()->blog()->getURLFor('feed', $type . '/comments') . '" ' .
            'title="' . sprintf($c_title, ($type == 'atom' ? 'Atom' : 'RSS')) . '" class="feed">' .
            __('Comments feed') . '</a></li>';
        }

        $res .= '</ul>';

        return $w->renderDiv($w->content_only, 'syndicate ' . $w->class, '', $res);
    }

    public function feed($w)
    {
        if (!$w->url) {
            return;
        }

        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $limit = abs((int) $w->limit);

        try {
            $feed = Reader::quickParse($w->url, dotclear()->config()->get('cache_dir'));
            if ($feed == false || count($feed->items) == 0) {
                return;
            }
        } catch (\Exception) {
            return;
        }

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') .
            '<ul>';

        $i = 0;
        foreach ($feed->items as $item) {
            $title = isset($item->title) && strlen(trim($item->title)) ? $item->title : '';
            $link  = isset($item->link)  && strlen(trim($item->link)) ? $item->link : '';

            if (!$link && !$title) {
                continue;
            }

            if (!$title) {
                $title = substr($link, 0, 25) . '...';
            }

            $li = $link ? '<a href="' . Html::escapeHTML($item->link) . '">' . $title . '</a>' : $title;
            $res .= ' <li>' . $li . '</li> ';
            $i++;
            if ($i >= $limit) {
                break;
            }
        }

        $res .= '</ul>';

        return $w->renderDiv($w->content_only, 'feed ' . $w->class, '', $res);
    }

    public function text($w)
    {
        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') . $w->text;

        return $w->renderDiv($w->content_only, 'text ' . $w->class, '', $res);
    }

    public function lastposts($w)
    {
        $context = dotclear()->context();

        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $params['limit']      = abs((int) $w->limit);
        $params['order']      = 'post_dt desc';
        $params['no_content'] = true;

        if ($w->category) {
            if ($w->category == 'null') {
                $params['sql'] = ' AND P.cat_id IS NULL ';
            } elseif (is_numeric($w->category)) {
                $params['cat_id'] = (int) $w->category;
            } else {
                $params['cat_url'] = $w->category;
            }
        }

        if ($w->tag) {
            $params['meta_id'] = $w->tag;
            $rs                = dotclear()->meta()->getPostsByMeta($params);
        } else {
            $rs = dotclear()->blog()->posts()->getPosts($params);
        }

        if ($rs->isEmpty()) {
            return;
        }

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') .
            '<ul>';

        while ($rs->fetch()) {
            $class = '';
            if (dotclear()->url()->type == 'post' && $context->posts instanceof record && $context->posts->post_id == $rs->post_id) {
                $class = ' class="post-current"';
            }
            $res .= '<li' . $class . '><a href="' . $rs->getURL() . '">' .
            Html::escapeHTML($rs->post_title) . '</a></li>';
        }

        $res .= '</ul>';

        return $w->renderDiv($w->content_only, 'lastposts ' . $w->class, '', $res);
    }

    public function lastcomments($w)
    {
        if ($w->offline) {
            return;
        }

        if (!$w->checkHomeOnly(dotclear()->url()->type)) {
            return;
        }

        $params['limit'] = abs((int) $w->limit);
        $params['order'] = 'comment_dt desc';
        $rs              = dotclear()->blog()->comments()->getComments($params);

        if ($rs->isEmpty()) {
            return;
        }

        $res = ($w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '') . '<ul>';

        while ($rs->fetch()) {
            $res .= '<li class="' .
            ((bool) $rs->comment_trackback ? 'last-tb' : 'last-comment') .
            '"><a href="' . $rs->getPostURL() . '#c' . $rs->comment_id . '">' .
            Html::escapeHTML($rs->post_title) . ' - ' .
            Html::escapeHTML($rs->comment_author) .
                '</a></li>';
        }

        $res .= '</ul>';

        return $w->renderDiv($w->content_only, 'lastcomments ' . $w->class, '', $res);
    }
    //@}

    /// @name Widgets init methods
    //@{
    public function initWidgets()
    {
        $__widgets = new Widgets();

        $__widgets
            ->create('search', __('Search engine'), [$this, 'search'], null, 'Search engine form')
            ->addTitle(__('Search'))
            ->setting('placeholder', __('Placeholder (HTML5 only, optional):'), '')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('navigation', __('Navigation links'), [$this, 'navigation'], null, 'List of navigation links')
            ->addTitle()
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('bestof', __('Selected entries'), [$this, 'bestof'], null, 'List of selected entries')
            ->addTitle(__('Best of me'))
            ->setting('orderby', __('Sort:'), 'asc', 'combo', [__('Ascending') => 'asc', __('Descending') => 'desc'])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('langs', __('Blog languages'), [$this, 'langs'], null, 'List of available languages')
            ->addTitle(__('Languages'))
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('categories', __('List of categories'), [$this, 'categories'], null, 'List of categories')
            ->addTitle(__('Categories'))
            ->setting('postcount', __('With entries counts'), 0, 'check')
            ->setting('subcatscount', __('Include sub cats in count'), false, 'check')
            ->setting('with_empty', __('Include empty categories'), 0, 'check')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('subscribe', __('Subscribe links'), [$this, 'subscribe'], null, 'Feed subscription links (RSS or Atom)')
            ->addTitle(__('Subscribe'))
            ->setting('type', __('Feeds type:'), 'atom', 'combo', ['Atom' => 'atom', 'RSS' => 'rss2'])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('feed', __('Feed reader'), [$this, 'feed'], null, 'List of last entries from feed (RSS or Atom)')
            ->addTitle(__('Somewhere else'))
            ->setting('url', __('Feed URL:'), '')
            ->setting('limit', __('Entries limit:'), 10)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $__widgets
            ->create('text', __('Text'), [$this, 'text'], null, 'Simple text')
            ->addTitle()
            ->setting('text', __('Text:'), '', 'textarea')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $rs         = dotclear()->blog()->categories()->getCategories(['post_type' => 'post']);
        $categories = ['' => '', __('Uncategorized') => 'null'];
        while ($rs->fetch()) {
            $categories[str_repeat('&nbsp;&nbsp;', $rs->level - 1) . ($rs->level - 1 == 0 ? '' : '&bull; ') . Html::escapeHTML($rs->cat_title)] = $rs->cat_id;
        }
        $w = $__widgets->create('lastposts', __('Last entries'), [$this, 'lastposts'], null, 'List of last entries published');
        $w
            ->addTitle(__('Last entries'))
            ->setting('category', __('Category:'), '', 'combo', $categories);
        if (dotclear()->plugins()->hasModule('Tags')) {
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
            ->create('lastcomments', __('Last comments'), [$this, 'lastcomments'], null, 'List of last comments published')
            ->addTitle(__('Last comments'))
            ->setting('limit', __('Comments limit:'), 10)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        # --BEHAVIOR-- initWidgets
        dotclear()->behavior()->call('initWidgets', $__widgets);

        $__default_widgets = ['nav' => new Widgets(), 'extra' => new Widgets(), 'custom' => new Widgets()];

        $__default_widgets['nav']->append($__widgets->search);
        $__default_widgets['nav']->append($__widgets->bestof);
        $__default_widgets['nav']->append($__widgets->categories);
        $__default_widgets['custom']->append($__widgets->subscribe);

        # --BEHAVIOR-- initDefaultWidgets
        dotclear()->behavior()->call('initDefaultWidgets', $__widgets, $__default_widgets);

        self::$__widgets = $__widgets;
        self::$__default_widgets = $__default_widgets;
    }
    //@}

    /// @name Widgets tempalte methods
    //@{
    public function tplWidgets($attr)
    {
        $type = $attr['type'] ?? '';

        # widgets to disable
        $disable = isset($attr['disable']) ? trim($attr['disable']) : '';

        if ($type == '') {
            $res = __CLASS__ . "::\$stack->widgetsHandler('nav','" . addslashes($disable) . "');" . "\n" .
            "   " . __CLASS__ . "::\$stack->widgetsHandler('extra','" . addslashes($disable) . "');" . "\n" .
            "   " . __CLASS__ . "::\$stack->widgetsHandler('custom','" . addslashes($disable) . "');" . "\n";
        } else {
            if (!in_array($type, ['nav', 'extra', 'custom'])) {
                $type = 'nav';
            }
            $res = __CLASS__ . "::\$stack->widgetsHandler('" . addslashes($type) . "','" . addslashes($disable) . "');";
        }

        return '<?php ' . $res . ' ?>';
    }

    public function widgetsHandler($type, $disable = '')
    {
        $wtype = 'widgets_' . $type;
        $widgets = dotclear()->blog()->settings()->get('widgets')->get($wtype);

        if (!$widgets) {
            // If widgets value is empty, get defaults
            $widgets = $this->defaultWidgets($type);
        } else {
            // Otherwise, load widgets
            $w = new Widgets();
            $widgets = $w->load($widgets);
        }

        if ($widgets->isEmpty()) {
            // Widgets are empty, don't show anything
            return;
        }

        $disable = preg_split('/\s*,\s*/', $disable, -1, PREG_SPLIT_NO_EMPTY);
        $disable = array_flip($disable);

        foreach ($widgets->elements() as $k => $w) {
            if (isset($disable[$w->id()])) {
                continue;
            }
            echo $w->call($k);
        }
    }

    public function tplIfWidgets($attr, $content)
    {
        $type = $attr['type'] ?? '';

        # widgets to disable
        $disable = isset($attr['disable']) ? trim($attr['disable']) : '';

        if ($type == '') {
            $res = __CLASS__ . "::\$stack->ifWidgetsHandler('nav','" . addslashes($disable) . "') &&" . "\n" .
            "   " . __CLASS__ . "::\$stack->ifWidgetsHandler('extra','" . addslashes($disable) . "') &&" . "\n" .
            "   " . __CLASS__ . "::\$stack->ifWidgetsHandler('custom','" . addslashes($disable) . "')" . "\n";
        } else {
            if (!in_array($type, ['nav', 'extra', 'custom'])) {
                $type = 'nav';
            }
            $res = __CLASS__ . "::\$stack->ifWidgetsHandler('" . addslashes($type) . "','" . addslashes($disable) . "')";
        }

        return '<?php if(' . $res . ') : ?>' . $content . '<?php endif; ?>';
    }

    public function ifWidgetsHandler($type, $disable = '')
    {
        $wtype = 'widgets_' . $type;
        $widgets = dotclear()->blog()->settings()->get('widgets')->get($wtype);

        if (!$widgets) {
            // If widgets value is empty, get defaults
            $widgets = $this->defaultWidgets($type);
        } else {
            // Otherwise, load widgets
            $w = new Widgets();
            $widgets = $w->load($widgets);
        }

        return (!$widgets->isEmpty());
    }

    private function defaultWidgets($type)
    {
        $w = new Widgets();

        if (isset(self::$__default_widgets[$type])) {
            $w = self::$__default_widgets[$type];
        }

        return $w;
    }

    public function tplWidget($attr, $content)
    {
        if (!isset($attr['id']) || !(self::$__widgets->{$attr['id']} instanceof Widget)) {
            return;
        }

        # We change tpl:lang syntax, we need it
        $content = preg_replace('/\{\{tpl:lang\s+(.*?)\}\}/msu', '{tpl:lang $1}', $content);

        # We remove every {{tpl:
        $content = preg_replace('/\{\{tpl:.*?\}\}/msu', '', $content);

        return
        "<?php " . __CLASS__ . "::\$stack->widgetHandler('" . addslashes($attr['id']) . "','" . str_replace("'", "\\'", $content) . "'); ?>";
    }

    public function widgetHandler($id, $xml)
    {
        if (!trim($xml)) {
            return;
        }

        $widgets = self::$__widgets;

        if (!($widgets->{$id} instanceof Widget)) {
            return;
        }

        $xml = '<?xml version="1.0" encoding="utf-8" ?><widget>' . $xml . '</widget>';
        $xml = @simplexml_load_string($xml);
        if (!($xml instanceof SimpleXMLElement)) {
            echo 'Invalid widget XML fragment';

            return;
        }

        $w = clone $widgets->{$id};

        foreach ($xml->setting as $e) {
            if (empty($e['name'])) {
                continue;
            }

            $setting = (string) $e['name'];
            if ($e->count() > 0) {
                $text = preg_replace('#^<setting[^>]*>(.*)</setting>$#msu', '\1', (string) $e->asXML());
            } else {
                $text = $e;
            }
            $w->{$setting} = preg_replace_callback('/\{tpl:lang (.*?)\}/msu', ['self', 'widgetL10nHandler'], $text);
        }

        echo $w->call(0);
    }

    private function widgetL10nHandler($m)
    {
        return __($m[1]);
    }
    //@}
}
