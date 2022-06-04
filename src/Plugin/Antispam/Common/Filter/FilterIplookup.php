<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common\Filter;

// Dotclear\Plugin\Antispam\Common\Filter\FilterIpookup
use Dotclear\App;
use Dotclear\Plugin\Antispam\Common\Spamfilter;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Antispam IP lookup filter.
 *
 * @ingroup  Plugin Antispam
 */
class FilterIplookup extends Spamfilter
{
    public $name    = 'IP Lookup';
    public $has_gui = true;
    public $help    = 'iplookup-filter';

    private $default_bls = 'sbl-xbl.spamhaus.org , bsb.spamlookup.net';

    public function __construct()
    {
        parent::__construct();

        if (defined('DC_DNSBL_SUPER') && DC_DNSBL_SUPER && !App::core()->user()->isSuperAdmin()) {
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

        if (GPC::post()->isset('bls')) {
            try {
                App::core()->blog()->settings()->getGroup('antispam')->putSetting('antispam_dnsbls', GPC::post()->string('bls'), 'string', 'Antispam DNSBL servers', true, false);
                App::core()->notice()->addSuccessNotice(__('The list of DNSBL servers has been succesfully updated.'));
                Http::redirect($url);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        /* DISPLAY
        ---------------------------------------------- */
        return '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .
        '<h3>' . __('IP Lookup servers') . '</h3>' .
        '<p><label for="bls">' . __('Add here a coma separated list of servers.') . '</label>' .
        Form::textarea('bls', 40, 3, Html::escapeHTML($bls), 'maximal') .
        '</p>' .
        '<p><input type="submit" value="' . __('Save') . '" />' .
        App::core()->nonce()->form() . '</p>' .
            '</form>';
    }

    private function getServers(): string
    {
        $bls = App::core()->blog()->settings()->getGroup('antispam')->getSetting('antispam_dnsbls');
        if (null === $bls) {
            App::core()->blog()->settings()->getGroup('antispam')->putSetting('antispam_dnsbls', $this->default_bls, 'string', 'Antispam DNSBL servers', true, false);

            return $this->default_bls;
        }

        return $bls;
    }

    private function dnsblLookup(string $ip, string $bl): bool
    {
        $revIp = implode('.', array_reverse(explode('.', $ip)));

        $host = $revIp . '.' . $bl . '.';

        return gethostbyname($host) != $host;
    }
}
