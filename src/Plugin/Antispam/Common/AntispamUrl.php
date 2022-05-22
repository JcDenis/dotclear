<?php
/**
 * @note Dotclear\Plugin\Antispam\Common\AntispamUrl
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

// Dotclear\Plugin\Antispam\Common\AntispamUrl
use Dotclear\App;
use Dotclear\Core\Url\Url;
use Dotclear\Database\Param;
use Dotclear\Helper\Html\Html;

/**
 * URL handling of plugin Antispam.
 *
 * @ingroup  Plugin Antispam Url
 */
class AntispamUrl extends Url
{
    public function __construct()
    {
        App::core()->url()->register('spamfeed', 'spamfeed', '^spamfeed/(.+)$', [$this, 'spamFeed']);
        App::core()->url()->register('hamfeed', 'hamfeed', '^hamfeed/(.+)$', [$this, 'hamFeed']);
    }

    public function hamFeed(?string $args): void
    {
        $this->genFeed('ham', (string) $args);
    }

    public function spamFeed(?string $args): void
    {
        $this->genFeed('spam', (string) $args);
    }

    private function genFeed(string $type, string $args): void
    {
        $user_id = (new Antispam())->checkUserCode($args);

        if (false === $user_id) {
            App::core()->url()->p404();

            return;
        }

        App::core()->user()->checkUser($user_id, null, null);

        header('Content-Type: application/xml; charset=UTF-8');

        $title   = App::core()->blog()->name . ' - ' . __('Spam moderation') . ' - ';
        $param   = new Param();
        $end_url = '';
        if ('spam' == $type) {
            $title .= __('Spam');
            $param->set('comment_status', -2);
            $end_url                  = '&status=-2';
        } else {
            $title .= __('Ham');
            $param->push('sql', ' AND comment_status IN (1,-1) ');
        }

        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
        '<rss version="2.0"' . "\n" .
        'xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n" .
        'xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n" .
        '<channel>' . "\n" .
        '<title>' . html::escapeHTML($title) . '</title>' . "\n" .
        '<link>' . ('' != App::core()->config()->get('admin_url') ? App::core()->config()->get('admin_url') . '?handler=admin.comments' . $end_url : 'about:blank') . '</link>' . "\n" .
        '<description></description>' . "\n";

        $rs       = App::core()->blog()->comments()->getComments(param: $param);
        $maxitems = 20;
        $nbitems  = 0;

        while ($rs->fetch() && ($nbitems < $maxitems)) {
            ++$nbitems;
            $uri    = App::core()->config()->get('admin_url') != '' ? App::core()->config()->get('admin_url') . '?handler=admin.comment&id=' . $rs->f('comment_id') : 'about:blank';
            $author = $rs->f('comment_author');
            $title  = $rs->f('post_title') . ' - ' . $author;
            if ('spam' == $type) {
                $title .= '(' . $rs->f('comment_spam_filter') . ')';
            }
            $id = $rs->call('getFeedID');

            $content = '<p>IP: ' . $rs->f('comment_ip');

            if (trim($rs->f('comment_site'))) {
                $content .= '<br />URL: <a href="' . $rs->f('comment_site') . '">' . $rs->f('comment_site') . '</a>';
            }
            $content .= "</p><hr />\n";
            $content .= $rs->f('comment_content');

            echo '<item>' . "\n" .
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
