<?php
/**
 * @class Dotclear\Plugin\ImportExport\Admin\Lib\Module\ImportFeed
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module;

use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Feed\Reader;
use Dotclear\Plugin\ImportExport\Admin\Lib\Module;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class ImportFeed extends Module
{
    protected $status   = false;
    protected $feed_url = '';

    // IPv6 functions (from https://gist.github.com/tbaschak/7866688)
    private function gethostbyname6($host, $try_a = false)
    {
        // get AAAA record for $host
        // if $try_a is true, if AAAA fails, it tries for A
        // the first match found is returned
        // otherwise returns false

        $dns = $this->gethostbynamel6($host, $try_a);
        if ($dns == false) {
            return false;
        }

        return $dns[0];
    }
    private function gethostbynamel6($host, $try_a = false)
    {
        // get AAAA records for $host,
        // if $try_a is true, if AAAA fails, it tries for A
        // results are returned in an array of ips found matching type
        // otherwise returns false

        $dns6 = dns_get_record($host, DNS_AAAA);
        if ($try_a == true) {
            $dns4 = dns_get_record($host, DNS_A);
            $dns  = array_merge($dns4, $dns6);
        } else {
            $dns = $dns6;
        }
        $ip6 = [];
        $ip4 = [];
        foreach ($dns as $record) {
            if ($record['type'] == 'A') {
                $ip4[] = $record['ip'];
            }
            if ($record['type'] == 'AAAA') {
                $ip6[] = $record['ipv6'];
            }
        }
        if (count($ip6) < 1) {
            if ($try_a == true) {
                if (count($ip4) < 1) {
                    return false;
                }

                return $ip4;
            }

            return false;
        }

        return $ip6;
    }

    public function setInfo()
    {
        $this->type        = 'import';
        $this->name        = __('RSS or Atom feed import');
        $this->description = __('Add a feed content to the blog.');
    }

    public function process($do)
    {
        if ($do == 'ok') {
            $this->status = true;

            return;
        }

        if (empty($_POST['feed_url'])) {
            return;
        }

        $this->feed_url = $_POST['feed_url'];

        // Check feed URL
        if (dotclear()->blog()->settings()->system->import_feed_url_control) {
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
            if (dotclear()->blog()->settings()->system->import_feed_no_private_ip) {
                $flag |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            }
            if (!filter_var($ip, $flag)) {
                throw new ModuleException(__('Cannot retrieve feed URL.'));
            }
            // IP control (white list regexp)
            if (dotclear()->blog()->settings()->system->import_feed_ip_regexp != '') {
                if (!preg_match(dotclear()->blog()->settings()->system->import_feed_ip_regexp, $ip)) {
                    throw new ModuleException(__('Cannot retrieve feed URL.'));
                }
            }
            // Port control (white list regexp)
            if (dotclear()->blog()->settings()->system->import_feed_port_regexp != '' && isset($bits['port'])) {
                if (!preg_match(dotclear()->blog()->settings()->system->import_feed_port_regexp, $bits['port'])) { // @phpstan-ignore-line
                    throw new ModuleException(__('Cannot retrieve feed URL.'));
                }
            }
        }

        $feed = Reader::quickParse($this->feed_url);
        if ($feed === false) {
            throw new ModuleException(__('Cannot retrieve feed URL.'));
        }
        if (count($feed->items) == 0) {
            throw new ModuleException(__('No items in feed.'));
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
        dotclear()->con()->begin();
        foreach ($feed->items as $item) {
            $cur->clean();
            $cur->user_id      = dotclear()->user()->userID();
            $cur->post_content = $item->content ?: $item->description;
            $cur->post_title   = $item->title ?: Text::cutString(Html::clean($cur->post_content), 60);
            $cur->post_format  = 'xhtml';
            $cur->post_status  = -2;
            $cur->post_dt      = @strftime('%Y-%m-%d %H:%M:%S', $item->TS);

            try {
                $post_id = dotclear()->blog()->addPost($cur);
            } catch (\Exception $e) {
                dotclear()->con()->rollback();

                throw $e;
            }

            foreach ($item->subject as $subject) {
                dotclear()->meta()->setPostMeta($post_id, 'tag', dotclear()->meta()::sanitizeMetaID($subject));
            }
        }

        dotclear()->con()->commit();
        Http::redirect($this->getURL() . '&do=ok');
    }

    public function gui()
    {
        if ($this->status) {
            dotclear()->notice()->success(__('Content successfully imported.'));
        }

        echo
        '<form action="' . $this->getURL(true) . '" method="post">' .
        '<p>' . sprintf(__('Add a feed content to the current blog: <strong>%s</strong>.'), Html::escapeHTML(dotclear()->blog()->name)) . '</p>' .

        '<p><label for="feed_url">' . __('Feed URL:') . '</label>' .
        Form::url('feed_url', 50, 300, Html::escapeHTML($this->feed_url)) . '</p>' .

        '<p>' .
        dotclear()->nonce()->form() .
        Form::hidden(['handler'], 'admin.plugin.ImportExport') .
        Form::hidden(['do'], 1) .
        '<input type="submit" value="' . __('Import') . '" /></p>' .

            '</form>';
    }
}
