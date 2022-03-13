<?php
/**
 * @class Dotclear\Plugin\Blogroll\Public\BlogrollTemplate
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBlogroll
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Public;

use ArrayObject;

use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Blogroll\Common\Blogroll;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class BlogrollTemplate
{
    public function __construct()
    {
        dotclear()->template()->addValue('Blogroll', [$this, 'blogroll']);
        dotclear()->template()->addValue('BlogrollXbelLink', [$this, 'blogrollXbelLink']);
    }

    public function blogroll($attr)
    {
        $category = '<h3>%s</h3>';
        $block    = '<ul>%s</ul>';
        $item     = '<li%2$s>%1$s</li>';

        if (isset($attr['category'])) {
            $category = addslashes($attr['category']);
        }

        if (isset($attr['block'])) {
            $block = addslashes($attr['block']);
        }

        if (isset($attr['item'])) {
            $item = addslashes($attr['item']);
        }

        $only_cat = 'null';
        if (!empty($attr['only_category'])) {
            $only_cat = "'" . addslashes($attr['only_category']) . "'";
        }

        return
            '<?php ' .
            "echo " . __CLASS__ . "::getList('" . $category . "','" . $block . "','" . $item . "'," . $only_cat . '); ' .
            '?>';
    }

    public function blogrollXbelLink($attr)
    {
        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("xbel")') . '; ?>';
    }

    public static function getList($cat_title = '<h3>%s</h3>', $block = '<ul>%s</ul>', $item = '<li>%s</li>', $category = null)
    {
        $blogroll = new Blogroll();

        try {
            $links = $blogroll->getLinks();
        } catch (\Exception) {
            return false;
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
            if ($k != '') {
                $res .= sprintf($cat_title, Html::escapeHTML($k)) . "\n";
            }

            $res .= self::getLinksList($v, $block, $item);
        }

        return $res;
    }

    private static function getLinksList($links, $block = '<ul>%s</ul>', $item = '<li%2$s>%1$s</li>')
    {
        $list = '';

        # Find current link item if any
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
