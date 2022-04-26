<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\FairTrackbacks\Common;

// Dotclear\Plugin\FairTrackbacks\Common\FilterFairtrackbacks
use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Network\NetHttp\NetHttp;
use Dotclear\Plugin\Antispam\Common\Spamfilter;
use Exception;

/**
 * FairTrackbacks Antispam filter.
 *
 * @ingroup  Plugin FairTrackbacks Antispam
 */
class FilterFairtrackbacks extends Spamfilter
{
    public $name    = 'Fair Trackbacks';
    public $has_gui = false;
    public $active  = true;
    public $order   = -10;

    public static function initFairTrackbacks()
    {
        if (App::core()->processed('Admin') && (!defined('DC_FAIRTRACKBACKS_FORCE') || !DC_FAIRTRACKBACKS_FORCE)
            || App::core()->processed('Public') && defined('DC_FAIRTRACKBACKS_FORCE') && DC_FAIRTRACKBACKS_FORCE
        ) {
            App::core()->behavior()->add('antispamInitFilters', function (ArrayObject $spamfilters): void {
                $spamfilters[] = __CLASS__;
            });
        }
    }

    protected function setInfo(): void
    {
        $this->description = __('Checks trackback source for a link to the post');
    }

    public function isSpam(string $type, string $author, string $email, string $site, string $ip, string $content, int $post_id, ?int &$status): ?bool
    {
        if ('trackback' != $type) {
            return null;
        }

        try {
            $default_parse = ['scheme' => '', 'host' => '', 'path' => '', 'query' => ''];
            $S             = array_merge($default_parse, parse_url($site));

            if (('http' != $S['scheme'] && 'https' != $S['scheme']) || !$S['host'] || !$S['path']) {
                throw new Exception('Invalid URL');
            }

            // Check incomink link page
            $post     = App::core()->blog()->posts()->getPosts(['post_id' => $post_id]);
            $post_url = $post->getURL();
            $P        = array_merge($default_parse, parse_url($post_url));

            if ($post_url == $site) {
                throw new Exception('Same source and destination');
            }

            $o = NetHttp::initClient($site, $path);
            $o->setTimeout(App::core()->config()->get('query_timeout'));
            $o->get($path);

            // Trackback source does not return 200 status code
            if ($o->getStatus() != 200) {
                throw new Exception('Invalid Status Code');
            }

            $tb_page = $o->getContent();

            // Do we find a link to post in trackback source?
            if ($S['host'] == $P['host']) {
                $pattern = $P['path'] . ($P['query'] ? '?' . $P['query'] : '');
            } else {
                $pattern = $post_url;
            }
            $pattern = preg_quote($pattern, '/');

            if (!preg_match('/' . $pattern . '/', $tb_page)) {
                throw new Exception('Unfair');
            }
        } catch (\Exception) {
            throw new Exception('Trackback not allowed for this URL.');
        }

        return null;
    }
}
