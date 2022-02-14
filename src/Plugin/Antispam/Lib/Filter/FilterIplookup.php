<?php
/**
 * @class Dotclear\Plugin\Antispam\Lib\Filter\FilterIpookup
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Lib\Filter;


use Dotclear\Plugin\Antispam\Lib\Spamfilter;

use Dotclear\Html\Html;
Use Dotclear\Html\Form;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class FilterIplookup extends Spamfilter
{
    public $name    = 'IP Lookup';
    public $has_gui = true;
    public $help    = 'iplookup-filter';

    private $default_bls = 'sbl-xbl.spamhaus.org , bsb.spamlookup.net';

    public function __construct()
    {
        parent::__construct();

        if (defined('DC_DNSBL_SUPER') && DC_DNSBL_SUPER && !dotclear()->auth()->isSuperAdmin()) {
            $this->has_gui = false;
        }
    }

    protected function setInfo(): void
    {
        $this->description = __('Checks sender IP address against DNSBL servers');
    }

    public function getStatusMessage(string $status, int $comment_id): string
    {
        return sprintf(__('Filtered by %1$s with server %2$s.'), $this->guiLink(), $status);
    }

    public function isSpam(string $type, string $author, string $email, string $site, string $ip, string $content, int $post_id, ?int &$status): ?bool
    {
        if (!$ip) {
            // No IP given
            return null;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE)) {
            // Not an IPv4 IP (excludind private range) not an IPv6 IP (excludind private range)
            return null;
        }

        $bls = $this->getServers();
        $bls = preg_split('/\s*,\s*/', $bls);

        foreach ($bls as $bl) {
            if ($this->dnsblLookup($ip, $bl)) {
                // Pass by reference $status to contain matching DNSBL
                $status = $bl;

                return true;
            }
        }

        return null;
    }

    public function gui(string $url): string
    {
        $bls = $this->getServers();

        if (isset($_POST['bls'])) {
            try {
                dotclear()->blog()->settings->addNamespace('antispam');
                dotclear()->blog()->settings->antispam->put('antispam_dnsbls', $_POST['bls'], 'string', 'Antispam DNSBL servers', true, false);
                dotclear()->notices->addSuccessNotice(__('The list of DNSBL servers has been succesfully updated.'));
                Http::redirect($url);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        /* DISPLAY
        ---------------------------------------------- */
        $res = '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .
        '<h3>' . __('IP Lookup servers') . '</h3>' .
        '<p><label for="bls">' . __('Add here a coma separated list of servers.') . '</label>' .
        Form::textarea('bls', 40, 3, Html::escapeHTML($bls), 'maximal') .
        '</p>' .
        '<p><input type="submit" value="' . __('Save') . '" />' .
        dotclear()->nonce()->form() . '</p>' .
            '</form>';

        return $res;
    }

    private function getServers()
    {
        $bls = dotclear()->blog()->settings->antispam->antispam_dnsbls;
        if ($bls === null) {
            dotclear()->blog()->settings->addNamespace('antispam');
            dotclear()->blog()->settings->antispam->put('antispam_dnsbls', $this->default_bls, 'string', 'Antispam DNSBL servers', true, false);

            return $this->default_bls;
        }

        return $bls;
    }

    private function dnsblLookup($ip, $bl)
    {
        $revIp = implode('.', array_reverse(explode('.', $ip)));

        $host = $revIp . '.' . $bl . '.';
        if (gethostbyname($host) != $host) {
            return true;
        }

        return false;
    }
}
