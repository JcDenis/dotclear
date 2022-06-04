<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module;

// Dotclear\Plugin\ImportExport\Admin\Lib\Module\ImportFeed
use Dotclear\App;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Network\Feed\Reader;
use Dotclear\Helper\Text;
use Dotclear\Plugin\ImportExport\Admin\Lib\Module;
use Exception;

/**
 * Import feed for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class ImportFeed extends Module
{
    protected $status   = false;
    protected $feed_url = '';

    // IPv6 functions (from https://gist.github.com/tbaschak/7866688)
    private function gethostbyname6(string $host, bool $try_a = false): string|false
    {
        // get AAAA record for $host
        // if $try_a is true, if AAAA fails, it tries for A
        // the first match found is returned
        // otherwise returns false

        $dns = $this->gethostbynamel6($host, $try_a);
        if (false == $dns) {
            return false;
        }

        return $dns[0];
    }

    private function gethostbynamel6(string $host, bool $try_a = false): array|false
    {
        // get AAAA records for $host,
        // if $try_a is true, if AAAA fails, it tries for A
        // results are returned in an array of ips found matching type
        // otherwise returns false

        $dns6 = dns_get_record($host, DNS_AAAA);
        if (true == $try_a) {
            $dns4 = dns_get_record($host, DNS_A);
            $dns  = array_merge($dns4, $dns6);
        } else {
            $dns = $dns6;
        }
        $ip6 = [];
        $ip4 = [];
        foreach ($dns as $record) {
            if ('A' == $record['type']) {
                $ip4[] = $record['ip'];
            }
            if ('AAAA' == $record['type']) {
                $ip6[] = $record['ipv6'];
            }
        }
        if (count($ip6) < 1) {
            if (true == $try_a) {
                if (count($ip4) < 1) {
                    return false;
                }

                return $ip4;
            }

            return false;
        }

        return $ip6;
    }

    public function setInfo(): void
    {
        $this->type        = 'import';
        $this->name        = __('RSS or Atom feed import');
        $this->description = __('Add a feed content to the blog.');
    }

    public function process(string $do): void
    {
        if ('ok' == $do) {
            $this->status = true;

            return;
        }

        if (GPC::post()->empty('feed_url')) {
            return;
        }

        $this->feed_url = GPC::post()->string('feed_url');

        // Check feed URL
        if (App::core()->blog()->settings()->getGroup('system')->getSetting('import_feed_url_control')) {
            // Get IP from URL
            $bits = parse_url($this->feed_url);
            if (!$bits || !isset($bits['host'])) {
                throw new ModuleException(__('Cannot retrieve feed URL.'));
            }
            $ip = gethostbyname($bits['host']);
            if ($ip == $bits['host']) {
                $ip = $this->gethostbyname6($bits['host']);
                if (!$ip) {
                    throw new ModuleException(__('Cannot retrieve feed URL.'));
                }
            }
            // Check feed IP
            $flag = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
            if (App::core()->blog()->settings()->getGroup('system')->getSetting('import_feed_no_private_ip')) {
                $flag |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            }
            if (!filter_var($ip, $flag)) {
                throw new ModuleException(__('Cannot retrieve feed URL.'));
            }
            // IP control (white list regexp)
            if (App::core()->blog()->settings()->getGroup('system')->getSetting('import_feed_ip_regexp') != '') {
                if (!preg_match(App::core()->blog()->settings()->getGroup('system')->getSetting('import_feed_ip_regexp'), $ip)) {
                    throw new ModuleException(__('Cannot retrieve feed URL.'));
                }
            }
            // Port control (white list regexp)
            if (App::core()->blog()->settings()->getGroup('system')->getSetting('import_feed_port_regexp') != '' && isset($bits['port'])) {
                if (!preg_match(App::core()->blog()->settings()->getGroup('system')->getSetting('import_feed_port_regexp'), (string) $bits['port'])) {
                    throw new ModuleException(__('Cannot retrieve feed URL.'));
                }
            }
        }

        $feed = Reader::quickParse($this->feed_url);
        if (false === $feed) {
            throw new ModuleException(__('Cannot retrieve feed URL.'));
        }
        if (count($feed->items) == 0) {
            throw new ModuleException(__('No items in feed.'));
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix() . 'post');
        App::core()->con()->begin();
        foreach ($feed->items as $item) {
            $cur->clean();
            $cur->setField('user_id', App::core()->user()->userID());
            $cur->setField('post_content', $item->content ?: $item->description);
            $cur->setField('post_title', $item->title ?: Text::cutString(Html::clean($cur->getField('post_content')), 60));
            $cur->setField('post_format', 'xhtml');
            $cur->setField('post_status', -2);
            $cur->setField('post_dt', Clock::str(format: '%Y-%m-%d %H:%M:%S', date: $item->TS));

            try {
                $post_id = App::core()->blog()->createPost(cursor: $cur);
            } catch (Exception $e) {
                App::core()->con()->rollback();

                throw $e;
            }

            foreach ($item->subject as $subject) {
                App::core()->meta()->setPostMeta($post_id, 'tag', App::core()->meta()::sanitizeMetaID($subject));
            }
        }

        App::core()->con()->commit();
        Http::redirect($this->getURL() . '&do=ok');
    }

    public function gui(): void
    {
        if ($this->status) {
            App::core()->notice()->success(__('Content successfully imported.'));
        }

        echo '<form action="' . $this->getURL(true) . '" method="post">' .
        '<p>' . sprintf(__('Add a feed content to the current blog: <strong>%s</strong>.'), Html::escapeHTML(App::core()->blog()->name)) . '</p>' .

        '<p><label for="feed_url">' . __('Feed URL:') . '</label>' .
        Form::url('feed_url', 50, 300, Html::escapeHTML($this->feed_url)) . '</p>' .

        '<p>' .
        App::core()->nonce()->form() .
        Form::hidden(['handler'], 'admin.plugin.ImportExport') .
        Form::hidden(['do'], 1) .
        '<input type="submit" value="' . __('Import') . '" /></p>' .

            '</form>';
    }
}
