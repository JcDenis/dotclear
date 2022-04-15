<?php
/**
 * @class Dotclear\Plugin\Antispam\Common\Filter\FilterLinkslookup
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common\Filter;

use Dotclear\Plugin\Antispam\Common\Spamfilter;

class FilterLinkslookup extends Spamfilter
{
    public $name = 'Links Lookup';

    private $server = 'multi.surbl.org';

    protected function setInfo(): void
    {
        $this->description = __('Checks links in comments against surbl.org');
    }

    public function getStatusMessage(string $status, int $comment_id): string
    {
        return sprintf(__('Filtered by %1$s with server %2$s.'), $this->guiLink(), $status);
    }

    public function isSpam(string $type, string $author, string $email, string $site, string $ip, string $content, int $post_id, ?int &$status): ?bool
    {
        if (!$ip || long2ip((int) ip2long($ip)) != $ip) {
            return null;
        }

        $urls = $this->getLinks($content);
        array_unshift($urls, $site);

        foreach ($urls as $u) {
            $b = parse_url($u);
            if (!isset($b['host']) || !$b['host']) {
                continue;
            }

            $domain      = preg_replace('/^[\w]{2,6}:\/\/([\w\d\.\-]+).*$/', '$1', $b['host']);
            $domain_elem = explode('.', $domain);

            $i = count($domain_elem) - 1;
            if (0 == $i) {
                // "domain" is 1 word long, don't check it
                return null;
            }
            $host = $domain_elem[$i];
            do {
                $host = $domain_elem[$i - 1] . '.' . $host;
                $i--;
                $response = gethostbyname($host . '.' . $this->server);
                if ('127' === substr($response, 0, 3) && '1' !== substr($response, 8)) {
                    $status = substr($domain, 0, 128);

                    return true;
                }
            } while ($i > 0);
        }

        return null;
    }

    private function getLinks(string $text): array
    {
        // href attribute on "a" tags is second match
        preg_match_all('|<a.*?href="(http.*?)"|', $text, $parts);

        return $parts[1];
    }
}
