<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Common;

// Dotclear\Plugin\Widgets\Common\WidgetsStack
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Record;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Feed\Reader;
use SimpleXMLElement;

/**
 * Widgets stack.
 *
 * @ingroup  Plugin Widgets
 */
class WidgetsStack
{
    /**
     * @var WidgetsStack $stack
     *                   Self instance
     */
    public static $stack;

    /**
     * @var Widgets $__widgets
     *              Widgets
     */
    public static $__widgets;

    /**
     * @var array<string,Widgets> $__default_widgets
     *                            Default widgets
     */
    public static $__default_widgets;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->initWidgets();
        self::$stack = $this;

        // Add widget to template engine on public side (frontend)
        if (App::core()->processed('Public')) {
            App::core()->template()->addValue('Widgets', [$this, 'tplWidgets']);
            App::core()->template()->addBlock('Widget', [$this, 'tplWidget']);
            App::core()->template()->addBlock('IfWidgets', [$this, 'tplIfWidgets']);
        }
    }

    // / @name Widgets page methods
    // @{
    /**
     * Render widget 'search'.
     *
     * @param Widget $widget The widget
     */
    public function search(Widget $widget): string
    {
        if (App::core()->blog()->settings()->get('system')->get('no_search')
            || $widget->isOffline()
            || !$widget->checkHomeOnly(App::core()->url()->type)
        ) {
            return '';
        }

        $value = App::core()->url()->search_string ? Html::escapeHTML(App::core()->url()->search_string) : '';

        return $widget->renderDiv(
            $widget->get('content_only'),
            $widget->get('class'),
            'id="search"',
            ($widget->get('title') ? $widget->renderTitle('<label for="q">' . Html::escapeHTML($widget->get('title')) . '</label>', false) : '') .
            '<form action="' . App::core()->blog()->url . '" method="get" role="search">' .
            '<p><input type="text" size="10" maxlength="255" id="q" name="q" value="' . $value . '" ' .
            ($widget->get('placeholder') ? 'placeholder="' . Html::escapeHTML($widget->get('placeholder')) . '"' : '') .
            ' aria-label="' . __('Search') . '"/> ' .
            '<input type="submit" class="submit" value="ok" title="' . __('Search') . '" /></p>' .
            '</form>'
        );
    }

    /**
     * Render widget 'navigation'.
     *
     * @param Widget $widget The widget
     */
    public function navigation(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        $res = $widget->renderTitle() .
            '<nav role="navigation"><ul>';

        if (!App::core()->url()->isHome(App::core()->url()->type)) {
            // Not on home page (standard or static), add home link
            $res .= '<li class="topnav-home">' .
            '<a href="' . App::core()->blog()->url . '">' . __('Home') . '</a></li>';
            if (App::core()->blog()->settings()->get('system')->get('static_home')) {
                // Static mode: add recent posts link
                $res .= '<li class="topnav-posts">' .
                '<a href="' . App::core()->blog()->getURLFor('posts') . '">' . __('Recent posts') . '</a></li>';
            }
        } else {
            // On home page (standard or static)
            if (App::core()->blog()->settings()->get('system')->get('static_home')) {
                // Static mode: add recent posts link
                $res .= '<li class="topnav-posts">' .
                '<a href="' . App::core()->blog()->getURLFor('posts') . '">' . __('Recent posts') . '</a></li>';
            }
        }

        $res .= '<li class="topnav-arch">' .
        '<a href="' . App::core()->blog()->getURLFor('archive') . '">' .
        __('Archives') . '</a></li>' .
            '</ul></nav>';

        return $widget->renderDiv($widget->get('content_only'), $widget->get('class'), 'id="topnav"', $res);
    }

    /**
     * Render widget 'catgories'.
     *
     * @param Widget $widget The widget
     */
    public function categories(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        $rs = App::core()->blog()->categories()->getCategories(['post_type' => 'post', 'without_empty' => !$widget->get('with_empty')]);
        if ($rs->isEmpty()) {
            return '';
        }

        $res = $widget->renderTitle();

        $ref_level = $level = $rs->fInt('level') - 1;
        while ($rs->fetch()) {
            $class = '';
            if (('category' == App::core()->url()->type && App::core()->context()->get('categories') instanceof Record && App::core()->context()->get('categories')->fInt('cat_id') === $rs->fInt('cat_id'))
                || ('post' == App::core()->url()->type && App::core()->context()->get('posts') instanceof Record && App::core()->context()->get('posts')->fInt('cat_id') === $rs->fInt('cat_id'))) {
                $class = ' class="category-current"';
            }

            if ($rs->fInt('level') > $level) {
                $res .= str_repeat('<ul><li' . $class . '>', $rs->fInt('level') - $level);
            } elseif ($rs->fInt('level') < $level) {
                $res .= str_repeat('</li></ul>', -($rs->fInt('level') - $level));
            }

            if ($rs->fInt('level') <= $level) {
                $res .= '</li><li' . $class . '>';
            }

            $res .= '<a href="' . App::core()->blog()->getURLFor('category', $rs->f('cat_url')) . '">' .
            Html::escapeHTML($rs->f('cat_title')) . '</a>' .
                ($widget->get('postcount') ? ' <span>(' . ($widget->get('subcatscount') ? $rs->f('nb_total') : $rs->f('nb_post')) . ')</span>' : '');

            $level = $rs->fInt('level');
        }

        if ($ref_level - $level < 0) {
            $res .= str_repeat('</li></ul>', -($ref_level - $level));
        }

        return $widget->renderDiv($widget->get('content_only'), 'categories ' . $widget->get('class'), '', $res);
    }

    /**
     * Render widget 'bestof'.
     *
     * @param Widget $widget The widget
     */
    public function bestof(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        $params = [
            'post_selected' => true,
            'no_content'    => true,
            'order'         => 'post_dt ' . strtoupper($widget->get('orderby')),
        ];

        $rs = App::core()->blog()->posts()->getPosts($params);

        if ($rs->isEmpty()) {
            return '';
        }

        $res = $widget->renderTitle() .
            '<ul>';

        while ($rs->fetch()) {
            $class = '';
            if ('post' == App::core()->url()->type && App::core()->context()->get('posts') instanceof Record && App::core()->context()->get('posts')->fInt('post_id') === $rs->fInt('post_id')) {
                $class = ' class="post-current"';
            }
            $res .= ' <li' . $class . '><a href="' . $rs->getURL() . '">' . Html::escapeHTML($rs->f('post_title')) . '</a></li> ';
        }

        $res .= '</ul>';

        return $widget->renderDiv($widget->get('content_only'), 'selected ' . $widget->get('class'), '', $res);
    }

    /**
     * Render widget 'langs'.
     *
     * @param Widget $widget The widget
     */
    public function langs(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        $rs = App::core()->blog()->posts()->getLangs();

        if ($rs->count() <= 1) {
            return '';
        }

        $langs = L10n::getISOcodes();
        $res   = $widget->renderTitle() .
            '<ul>';

        while ($rs->fetch()) {
            $l = (App::core()->context()->get('cur_lang') == $rs->f('post_lang')) ? '<strong>%s</strong>' : '%s';

            $lang_name = $langs[$rs->f('post_lang')] ?? $rs->f('post_lang');

            $res .= ' <li>' .
            sprintf(
                $l,
                '<a href="' . App::core()->blog()->getURLFor('lang', $rs->f('post_lang')) . '" ' .
                'class="lang-' . $rs->f('post_lang') . '">' .
                $lang_name . '</a>'
            ) .
                ' </li>';
        }

        $res .= '</ul>';

        return $widget->renderDiv($widget->get('content_only'), 'langs ' . $widget->get('class'), '', $res);
    }

    /**
     * Render widget 'subscribe'.
     *
     * @param Widget $widget The widget
     */
    public function subscribe(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        $type = ('atom' == $widget->get('type') || 'rss2' == $widget->get('type')) ? $widget->get('type') : 'rss2';
        $mime = 'rss2'  == $type ? 'application/rss+xml' : 'application/atom+xml';
        if (App::core()->context()->exists('cur_lang')) {
            $type = App::core()->context()->get('cur_lang') . '/' . $type;
        }

        $p_title = __('This blog\'s entries %s feed');
        $c_title = __('This blog\'s comments %s feed');

        $res = $widget->renderTitle() .
            '<ul>';

        $res .= '<li><a type="' . $mime . '" ' .
        'href="' . App::core()->blog()->getURLFor('feed', $type) . '" ' .
        'title="' . sprintf($p_title, ('atom' == $type ? 'Atom' : 'RSS')) . '" class="feed">' .
        __('Entries feed') . '</a></li>';

        if (App::core()->blog()->settings()->get('system')->get('allow_comments') || App::core()->blog()->settings()->get('system')->get('allow_trackbacks')) {
            $res .= '<li><a type="' . $mime . '" ' .
            'href="' . App::core()->blog()->getURLFor('feed', $type . '/comments') . '" ' .
            'title="' . sprintf($c_title, ('atom' == $type ? 'Atom' : 'RSS')) . '" class="feed">' .
            __('Comments feed') . '</a></li>';
        }

        $res .= '</ul>';

        return $widget->renderDiv($widget->get('content_only'), 'syndicate ' . $widget->get('class'), '', $res);
    }

    /**
     * Render widget 'feed'.
     *
     * @param Widget $widget The widget
     */
    public function feed(Widget $widget): string
    {
        if (!$widget->get('url') || $widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        $limit = abs((int) $widget->get('limit'));

        try {
            $feed = Reader::quickParse($widget->get('url'), App::core()->config()->get('cache_dir'));
            if (false == $feed || count($feed->items) == 0) {
                return '';
            }
        } catch (\Exception) {
            return '';
        }

        $res = $widget->renderTitle() .
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
            ++$i;
            if ($i >= $limit) {
                break;
            }
        }

        $res .= '</ul>';

        return $widget->renderDiv($widget->get('content_only'), 'feed ' . $widget->get('class'), '', $res);
    }

    /**
     * Render widget 'text'.
     *
     * @param Widget $widget The widget
     */
    public function text(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        return $widget->renderDiv(
            $widget->get('content_only'),
            'text ' . $widget->get('class'),
            '',
            $widget->renderTitle() . $widget->get('text')
        );
    }

    /**
     * Render widget 'lastposts'.
     *
     * @param Widget $widget The widget
     */
    public function lastposts(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        $params['limit']      = abs((int) $widget->get('limit'));
        $params['order']      = 'post_dt desc';
        $params['no_content'] = true;

        if ($widget->get('category')) {
            if ('null' == $widget->get('category')) {
                $params['sql'] = ' AND P.cat_id IS NULL ';
            } elseif (is_numeric($widget->get('category'))) {
                $params['cat_id'] = (int) $widget->get('category');
            } else {
                $params['cat_url'] = $widget->get('category');
            }
        }

        if ($widget->get('tag')) {
            $params['meta_id'] = $widget->get('tag');
            $rs                = App::core()->meta()->getPostsByMeta($params);
        } else {
            $rs = App::core()->blog()->posts()->getPosts($params);
        }

        if ($rs->isEmpty()) {
            return '';
        }

        $res = $widget->renderTitle() .
            '<ul>';

        while ($rs->fetch()) {
            $class = '';
            if ('psot' == App::core()->url()->type && App::core()->context()->get('posts') instanceof Record && App::core()->context()->get('posts')->fInt('post_id') === $rs->fInt('post_id')) {
                $class = ' class="post-current"';
            }
            $res .= '<li' . $class . '><a href="' . $rs->getURL() . '">' .
            Html::escapeHTML($rs->f('post_title')) . '</a></li>';
        }

        $res .= '</ul>';

        return $widget->renderDiv($widget->get('content_only'), 'lastposts ' . $widget->get('class'), '', $res);
    }

    /**
     * Render widget 'lastcomments'.
     *
     * @param Widget $widget The widget
     */
    public function lastcomments(Widget $widget): string
    {
        if ($widget->isOffline() || !$widget->checkHomeOnly(App::core()->url()->type)) {
            return '';
        }

        $params['limit'] = abs((int) $widget->get('limit'));
        $params['order'] = 'comment_dt desc';
        $rs              = App::core()->blog()->comments()->getComments($params);

        if ($rs->isEmpty()) {
            return '';
        }

        $res = $widget->renderTitle() . '<ul>';

        while ($rs->fetch()) {
            $res .= '<li class="' .
            ((bool) $rs->fInt('comment_trackback') ? 'last-tb' : 'last-comment') .
            '"><a href="' . $rs->getPostURL() . '#c' . $rs->f('comment_id') . '">' .
            Html::escapeHTML($rs->f('post_title')) . ' - ' .
            Html::escapeHTML($rs->f('comment_author')) .
                '</a></li>';
        }

        $res .= '</ul>';

        return $widget->renderDiv($widget->get('content_only'), 'lastcomments ' . $widget->get('class'), '', $res);
    }
    // @}

    // / @name Widgets init methods
    // @{
    /**
     * Intialize widgets.
     */
    public function initWidgets(): void
    {
        $__widgets = new Widgets();

        $__widgets
            ->create('search', __('Search engine'), [$this, 'search'], null, __('Search engine form'))
            ->addTitle(__('Search'))
            ->setting('placeholder', __('Placeholder (HTML5 only, optional):'), '')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        $__widgets
            ->create('navigation', __('Navigation links'), [$this, 'navigation'], null, __('List of navigation links'))
            ->addTitle()
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        $__widgets
            ->create('bestof', __('Selected entries'), [$this, 'bestof'], null, __('List of selected entries'))
            ->addTitle(__('Best of me'))
            ->setting('orderby', __('Sort:'), 'asc', 'combo', [__('Ascending') => 'asc', __('Descending') => 'desc'])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        $__widgets
            ->create('langs', __('Blog languages'), [$this, 'langs'], null, __('List of available languages'))
            ->addTitle(__('Languages'))
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        $__widgets
            ->create('categories', __('List of categories'), [$this, 'categories'], null, __('List of categories'))
            ->addTitle(__('Categories'))
            ->setting('postcount', __('With entries counts'), 0, 'check')
            ->setting('subcatscount', __('Include sub cats in count'), false, 'check')
            ->setting('with_empty', __('Include empty categories'), 0, 'check')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        $__widgets
            ->create('subscribe', __('Subscribe links'), [$this, 'subscribe'], null, __('Feed subscription links (RSS or Atom)'))
            ->addTitle(__('Subscribe'))
            ->setting('type', __('Feeds type:'), 'atom', 'combo', ['Atom' => 'atom', 'RSS' => 'rss2'])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        $__widgets
            ->create('feed', __('Feed reader'), [$this, 'feed'], null, __('List of last entries from feed (RSS or Atom)'))
            ->addTitle(__('Somewhere else'))
            ->setting('url', __('Feed URL:'), '')
            ->setting('limit', __('Entries limit:'), 10)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        $__widgets
            ->create('text', __('Text'), [$this, 'text'], null, __('Simple text'))
            ->addTitle()
            ->setting('text', __('Text:'), '', 'textarea')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        $rs         = App::core()->blog()->categories()->getCategories(['post_type' => 'post']);
        $categories = ['' => '', __('Uncategorized') => 'null'];
        while ($rs->fetch()) {
            $categories[str_repeat('&nbsp;&nbsp;', $rs->fInt('level') - 1) . (0 == $rs->fInt('level') - 1 ? '' : '&bull; ') . Html::escapeHTML($rs->f('cat_title'))] = $rs->f('cat_id');
        }
        $widget = $__widgets->create('lastposts', __('Last entries'), [$this, 'lastposts'], null, __('List of last entries published'));
        $widget
            ->addTitle(__('Last entries'))
            ->setting('category', __('Category:'), '', 'combo', $categories)
        ;
        if (App::core()->plugins()->hasModule('Tags')) {
            $widget->setting('tag', __('Tag:'), '');
        }
        $widget
            ->setting('limit', __('Entries limit:'), 10)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;
        unset($rs, $categories, $widget);

        $__widgets
            ->create('lastcomments', __('Last comments'), [$this, 'lastcomments'], null, __('List of last comments published'))
            ->addTitle(__('Last comments'))
            ->setting('limit', __('Comments limit:'), 10)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;

        // --BEHAVIOR-- initWidgets
        App::core()->behavior()->call('initWidgets', $__widgets);

        $__default_widgets = ['nav' => new Widgets(), 'extra' => new Widgets(), 'custom' => new Widgets()];

        $__default_widgets['nav']->append($__widgets->get('search'));
        $__default_widgets['nav']->append($__widgets->get('bestof'));
        $__default_widgets['nav']->append($__widgets->get('categories'));
        $__default_widgets['custom']->append($__widgets->get('subscribe'));

        // --BEHAVIOR-- initDefaultWidgets
        App::core()->behavior()->call('initDefaultWidgets', $__widgets, $__default_widgets);

        self::$__widgets         = $__widgets;
        self::$__default_widgets = $__default_widgets;
    }
    // @}

    // / @name Widgets tempalte methods
    // @{
    public function tplWidgets(ArrayObject $attr): string
    {
        $type = $attr['type'] ?? '';

        // widgets to disable
        $disable = isset($attr['disable']) ? trim($attr['disable']) : '';

        if ('' == $type) {
            $res = __CLASS__ . "::\$stack->widgetsHandler('nav','" . addslashes($disable) . "');" . "\n" .
            '   ' . __CLASS__ . "::\$stack->widgetsHandler('extra','" . addslashes($disable) . "');" . "\n" .
            '   ' . __CLASS__ . "::\$stack->widgetsHandler('custom','" . addslashes($disable) . "');" . "\n";
        } else {
            if (!in_array($type, ['nav', 'extra', 'custom'])) {
                $type = 'nav';
            }
            $res = __CLASS__ . "::\$stack->widgetsHandler('" . addslashes($type) . "','" . addslashes($disable) . "');";
        }

        return '<?php ' . $res . ' ?>';
    }

    public function widgetsHandler(string $type, string $disable = ''): void
    {
        $wtype   = 'widgets_' . $type;
        $widgets = App::core()->blog()->settings()->get('widgets')->get($wtype);

        if (!$widgets) {
            // If widgets value is empty, get defaults
            $widgets = $this->defaultWidgets($type);
        } else {
            // Otherwise, load widgets
            $widget  = new Widgets();
            $widgets = $widget->load($widgets);
        }

        if ($widgets->isEmpty()) {
            // Widgets are empty, don't show anything
            return;
        }

        $disable = preg_split('/\s*,\s*/', $disable, -1, PREG_SPLIT_NO_EMPTY);
        $disable = array_flip($disable);

        foreach ($widgets->elements() as $k => $widget) {
            if (isset($disable[$widget->id()])) {
                continue;
            }
            echo $widget->call($k);
        }
    }

    public function tplIfWidgets(ArrayObject $attr, string $content): string
    {
        $type = $attr['type'] ?? '';

        // widgets to disable
        $disable = isset($attr['disable']) ? trim($attr['disable']) : '';

        if ('' == $type) {
            $res = __CLASS__ . "::\$stack->ifWidgetsHandler('nav','" . addslashes($disable) . "') &&" . "\n" .
            '   ' . __CLASS__ . "::\$stack->ifWidgetsHandler('extra','" . addslashes($disable) . "') &&" . "\n" .
            '   ' . __CLASS__ . "::\$stack->ifWidgetsHandler('custom','" . addslashes($disable) . "')" . "\n";
        } else {
            if (!in_array($type, ['nav', 'extra', 'custom'])) {
                $type = 'nav';
            }
            $res = __CLASS__ . "::\$stack->ifWidgetsHandler('" . addslashes($type) . "','" . addslashes($disable) . "')";
        }

        return '<?php if(' . $res . ') : ?>' . $content . '<?php endif; ?>';
    }

    public function ifWidgetsHandler(string $type, string $disable = ''): bool
    {
        $wtype   = 'widgets_' . $type;
        $widgets = App::core()->blog()->settings()->get('widgets')->get($wtype);

        if (!$widgets) {
            // If widgets value is empty, get defaults
            $widgets = $this->defaultWidgets($type);
        } else {
            // Otherwise, load widgets
            $widgets = (new Widgets())->load($widgets);
        }

        return !$widgets->isEmpty();
    }

    private function defaultWidgets(string $type): Widgets
    {
        $widgets = new Widgets();

        if (isset(self::$__default_widgets[$type])) {
            $widgets = self::$__default_widgets[$type];
        }

        return $widgets;
    }

    public function tplWidget(ArrayObject $attr, string $content): string
    {
        if (!isset($attr['id']) || !(self::$__widgets->get($attr['id']) instanceof Widget)) {
            return '';
        }

        // We change tpl:lang syntax, we need it
        $content = preg_replace('/\{\{tpl:lang\s+(.*?)\}\}/msu', '{tpl:lang $1}', $content);

        // We remove every {{tpl:
        $content = preg_replace('/\{\{tpl:.*?\}\}/msu', '', $content);

        return '<?php ' . __CLASS__ . "::\$stack->widgetHandler('" . addslashes($attr['id']) . "','" . str_replace("'", "\\'", $content) . "'); ?>";
    }

    public function widgetHandler(string $id, string $xml): void
    {
        if (!trim($xml)) {
            return;
        }

        $widgets = self::$__widgets;

        if (!($widgets->get($id) instanceof Widget)) {
            return;
        }

        $xml = '<?xml version="1.0" encoding="utf-8" ?><widget>' . $xml . '</widget>';
        $xml = @simplexml_load_string($xml);
        if (!($xml instanceof SimpleXMLElement)) {
            echo 'Invalid widget XML fragment';

            return;
        }

        $widget = clone $widgets->get($id);

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
            $widget->set($setting, preg_replace_callback('/\{tpl:lang (.*?)\}/msu', ['self', 'widgetL10nHandler'], $text));
        }

        echo $widget->call(0);
    }

    private function widgetL10nHandler(array $m): string
    {
        return __($m[1]);
    }
    // @}
}
