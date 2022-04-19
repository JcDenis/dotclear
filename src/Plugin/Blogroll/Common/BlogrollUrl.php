<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Common;

use Dotclear\Core\Url\Url;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Plugin Blogroll URL methods.
 *
 * \Dotclear\Plugin\Blogroll\Common\BlogrollUrl
 *
 * @ingroup  Plugin Blogroll Url
 */
class BlogrollUrl extends Url
{
    public function __construct()
    {
        dotclear()->url()->register('xbel', 'xbel', '^xbel(?:/?)$', [$this, 'xbel']);
    }

    public function xbel($args)
    {
        $blogroll = new Blogroll();

        try {
            $links = $blogroll->getLinks();
        } catch (Exception $e) {
            dotclear()->url()->p404();

            return;
        }

        if ($args) {
            dotclear()->url()->p404();

            return;
        }

        Http::cache(dotclear()->url()->mod_files, dotclear()->url()->mod_ts);

        header('Content-Type: text/xml; charset=UTF-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<!DOCTYPE xbel PUBLIC "+//IDN python.org//DTD XML Bookmark Exchange ' .
        'Language 1.0//EN//XML"' . "\n" .
        '"http://www.python.org/topics/xml/dtds/xbel-1.0.dtd">' . "\n" .
        '<xbel version="1.0">' . "\n" .
        '<title>' . Html::escapeHTML(dotclear()->blog()->name) . " blogroll</title>\n";

        $i = 1;
        foreach ($blogroll->getLinksHierarchy($links) as $cat_title => $links) {
            if ('' != $cat_title) {
                echo '<folder>' . "\n" .
                '<title>' . Html::escapeHTML($cat_title) . "</title>\n";
            }

            foreach ($links as $k => $v) {
                $lang = $v['link_lang'] ? ' xml:lang="' . $v['link_lang'] . '"' : '';

                echo '<bookmark href="' . $v['link_href'] . '"' . $lang . '>' . "\n" .
                '<title>' . Html::escapeHTML($v['link_title']) . "</title>\n";

                if ($v['link_desc']) {
                    echo '<desc>' . Html::escapeHTML($v['link_desc']) . "</desc>\n";
                }

                if ($v['link_xfn']) {
                    echo "<info>\n" .
                        '<metadata owner="http://gmpg.org/xfn/">' . $v['link_xfn'] . "</metadata>\n" .
                        "</info>\n";
                }

                echo "</bookmark>\n";
            }

            if ('' != $cat_title) {
                echo "</folder>\n";
            }

            ++$i;
        }

        echo '</xbel>';
    }
}
