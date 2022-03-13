<?php
/**
 * @class Dotclear\Plugin\Antispam\Common\AntispamUrl
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

use ArrayObject;

use Dotclear\Plugin\Antispam\Common\Antispam;

use Dotclear\Core\Url\Url;
use Dotclear\Helper\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class AntispamUrl extends Url
{
    public function __construct()
    {
        dotclear()->url()->register('spamfeed', 'spamfeed', '^spamfeed/(.+)$', [$this, 'spamFeed']);
        dotclear()->url()->register('hamfeed', 'hamfeed', '^hamfeed/(.+)$', [$this, 'hamFeed']);
    }

    public function hamFeed($args)
    {
        $this->genFeed('ham', $args);
    }

    public function spamFeed($args)
    {
        $this->genFeed('spam', $args);
    }

    private function genFeed($type, $args)
    {
        $user_id = (new Antispam())->checkUserCode($args);

        if ($user_id === false) {
            dotclear()->url()->p404();

            return;
        }

        dotclear()->user()->checkUser($user_id, null, null);

        header('Content-Type: application/xml; charset=UTF-8');

        $title   = dotclear()->blog()->name . ' - ' . __('Spam moderation') . ' - ';
        $params  = [];
        $end_url = '';
        if ($type == 'spam') {
            $title .= __('Spam');
            $params['comment_status'] = -2;
            $end_url                  = '&status=-2';
        } else {
            $title .= __('Ham');
            $params['sql'] = ' AND comment_status IN (1,-1) ';
        }

        echo
        '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
        '<rss version="2.0"' . "\n" .
        'xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n" .
        'xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n" .
        '<channel>' . "\n" .
        '<title>' . html::escapeHTML($title) . '</title>' . "\n" .
        /* @phpstan-ignore-next-line */
        '<link>' . (dotclear()->config()->admin_url != '' ? dotclear()->config()->admin_url . '?handler=admin.comments' . $end_url : 'about:blank') . '</link>' . "\n" .
        '<description></description>' . "\n";

        $rs       = dotclear()->blog()->comments()->getComments($params);
        $maxitems = 20;
        $nbitems  = 0;

        while ($rs->fetch() && ($nbitems < $maxitems)) {
            $nbitems++;
            /* @phpstan-ignore-next-line */
            $uri    = dotclear()->config()->admin_url != '' ? dotclear()->config()->admin_url . '?handler=admin.comment&id=' . $rs->comment_id : 'about:blank';
            $author = $rs->comment_author;
            $title  = $rs->post_title . ' - ' . $author;
            if ($type == 'spam') {
                $title .= '(' . $rs->comment_spam_filter . ')';
            }
            $id = $rs->getFeedID();

            $content = '<p>IP: ' . $rs->comment_ip;

            if (trim($rs->comment_site)) {
                $content .= '<br />URL: <a href="' . $rs->comment_site . '">' . $rs->comment_site . '</a>';
            }
            $content .= "</p><hr />\n";
            $content .= $rs->comment_content;

            echo
            '<item>' . "\n" .
            '  <title>' . html::escapeHTML($title) . '</title>' . "\n" .
            '  <link>' . $uri . '</link>' . "\n" .
            '  <guid>' . $id . '</guid>' . "\n" .
            '  <pubDate>' . $rs->getRFC822Date() . '</pubDate>' . "\n" .
            '  <dc:creator>' . html::escapeHTML($author) . '</dc:creator>' . "\n" .
            '  <description>' . html::escapeHTML($content) . '</description>' . "\n" .
                '</item>';
        }

        echo "</channel>\n</rss>";
    }
}
