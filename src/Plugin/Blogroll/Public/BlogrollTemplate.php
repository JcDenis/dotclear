<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Public;

// Dotclear\Plugin\Blogroll\Public\BlogrollTemplate
use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Blogroll\Common\Blogroll;
use Dotclear\Process\Public\Template\Engine\TplAttr;

/**
 * Public templates methods for plugin Blogroll.
 *
 * @ingroup  Plugin Blogroll Template
 */
class BlogrollTemplate
{
    // \cond
    // php tags break doxygen parser...
    private static $toff = ' ?>';
    private static $ton  = '<?php ';
    // \endcond

    public function __construct()
    {
        App::core()->template()->addValue('Blogroll', [$this, 'blogroll']);
        App::core()->template()->addValue('BlogrollXbelLink', [$this, 'blogrollXbelLink']);
    }

    public function blogroll(TplAttr $attr): string
    {
        $category = $attr->has('category') ? addslashes($attr->get('category')) : '<h3>%s</h3>';
        $block    = $attr->has('block') ? addslashes($attr->get('block')) : '<ul>%s</ul>';
        $item     = $attr->has('item') ? addslashes($attr->get('item')) : '<li%2$s>%1$s</li>';
        $only_cat = empty($attr->get('only_category')) ? 'null' : "'" . addslashes($attr->get('only_category')) . "'";

        return
            self::$ton .
            'echo ' . __CLASS__ . "::getList('" . $category . "','" . $block . "','" . $item . "'," . $only_cat . '); ' .
            self::$toff;
    }

    public function blogrollXbelLink(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->blog()->getURLFor("xbel")') . ';' . self::$toff;
    }

    public static function getList(string $cat_title = '<h3>%s</h3>', string $block = '<ul>%s</ul>', string $item = '<li>%s</li>', ?string $category = null): string
    {
        try {
            $blogroll = new Blogroll();
            $links    = $blogroll->getLinks();
        } catch (\Exception) {
            return '';
        }

        $res = '';

        $hierarchy = $blogroll->getLinksHierarchy($links);

        if ($category) {
            if (!isset($hierarchy[$category])) {
                return '';
            }
            $hierarchy = [$hierarchy[$category]];
        }

        foreach ($hierarchy as $k => $v) {
            if ('' != $k) {
                $res .= sprintf($cat_title, Html::escapeHTML($k)) . "\n";
            }

            $res .= self::getLinksList($v, $block, $item);
        }

        return $res;
    }

    private static function getLinksList(array $links, string $block = '<ul>%s</ul>', string $item = '<li%2$s>%1$s</li>'): string
    {
        $list = '';

        // Find current link item if any
        $current      = -1;
        $current_size = 0;
        $self_uri     = Http::getSelfURI();

        foreach ($links as $k => $v) {
            if (!preg_match('$^([a-z][a-z0-9.+-]+://)$', $v['link_href'])) {
                $url = Http::concatURL($self_uri, $v['link_href']);
                if (strlen($url) > $current_size && preg_match('/^' . preg_quote($url, '/') . '/', $self_uri)) {
                    $current      = $k;
                    $current_size = strlen($url);
                }
            }
        }

        foreach ($links as $k => $v) {
            $title = $v['link_title'];
            $href  = $v['link_href'];
            $desc  = $v['link_desc'];
            $lang  = $v['link_lang'];
            $xfn   = $v['link_xfn'];

            $link = '<a href="' . Html::escapeHTML($href) . '"' .
            ((!$lang) ? '' : ' hreflang="' . Html::escapeHTML($lang) . '"') .
            ((!$desc) ? '' : ' title="' . Html::escapeHTML($desc) . '"') .
            ((!$xfn) ? '' : ' rel="' . Html::escapeHTML($xfn) . '"') .
            '>' .
            Html::escapeHTML($title) .
                '</a>';

            $current_class = $current == $k ? ' class="active"' : '';

            $list .= sprintf($item, $link, $current_class) . "\n";
        }

        return sprintf($block, $list) . "\n";
    }
}
