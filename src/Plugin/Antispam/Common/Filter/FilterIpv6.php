<?php
/**
 * @class Dotclear\Plugin\Antispam\Common\Filter\FilterIpv6
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

use Dotclear\Helper\Html\Html;
Use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class FilterIpv6 extends Spamfilter
{
    public $name    = 'IP Filter v6';
    public $has_gui = true;
    public $help    = 'ip-filter-v6';

    private $table;
    private $tab;

    public function __construct()
    {
        parent::__construct();
        $this->table = dotclear()->prefix . 'spamrule';
    }

    protected function setInfo(): void
    {
        $this->description = __('IP v6 Blocklist / Allowlist Filter');
    }

    public function getStatusMessage(string $status, int $comment_id): string
    {
        return sprintf(__('Filtered by %1$s with rule %2$s.'), $this->guiLink(), $status);
    }

    public function isSpam(string $type, string $author, string $email, string $site, string $ip, string $content, int $post_id, ?int &$status): ?bool
    {
        if (!$ip) {
            return null;
        }

        # White list check
        if ($this->checkIP($ip, 'whitev6') !== false) {
            return false;
        }

        # Black list check
        if (($s = $this->checkIP($ip, 'blackv6')) !== false) {
            $status = $s;

            return true;
        }

        return null;
    }

    public function gui(string $url): string
    {
        # Set current type and tab
        $ip_type = 'blackv6';
        if (!empty($_REQUEST['ip_type']) && $_REQUEST['ip_type'] == 'whitev6') {
            $ip_type = 'whitev6';
        }
        $this->tab = 'tab_' . $ip_type;

        # Add IP to list
        if (!empty($_POST['addip'])) {
            try {
                $global = !empty($_POST['globalip']) && dotclear()->user()->isSuperAdmin();

                $this->addIP($ip_type, $_POST['addip'], $global);
                dotclear()->notice()->addSuccessNotice(__('IP address has been successfully added.'));
                Http::redirect($url . '&ip_type=' . $ip_type);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Remove IP from list
        if (!empty($_POST['delip']) && is_array($_POST['delip'])) {
            try {
                $this->removeRule($_POST['delip']);
                dotclear()->notice()->addSuccessNotice(__('IP addresses have been successfully removed.'));
                Http::redirect($url . '&ip_type=' . $ip_type);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        /* DISPLAY
        ---------------------------------------------- */
        $res = $this->displayForms($url, 'blackv6', __('Blocklist')) .
        $this->displayForms($url, 'whitev6', __('Allowlist'));

        return $res;
    }

    public function guiTab(): ?string
    {
        return $this->tab;
    }

    private function displayForms($url, $type, $title)
    {
        $res = '<div class="multi-part" id="tab_' . $type . '" title="' . $title . '">' .

        '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .

        '<p>' .
        Form::hidden(['ip_type'], $type) .
        '<label class="classic" for="addip_' . $type . '">' . __('Add an IP address: ') . '</label> ' .
        Form::field(['addip', 'addip_' . $type], 18, 255);
        if (dotclear()->user()->isSuperAdmin()) {
            $res .= '<label class="classic" for="globalip_' . $type . '">' . Form::checkbox(['globalip', 'globalip_' . $type], 1) . ' ' .
            __('Global IP (used for all blogs)') . '</label> ';
        }

        $res .= dotclear()->nonce()->form() .
        '</p>' .
        '<p><input type="submit" value="' . __('Add') . '"/></p>' .
            '</form>';

        $rs = $this->getRules($type);

        if ($rs->isEmpty()) {
            $res .= '<p><strong>' . __('No IP address in list.') . '</strong></p>';
        } else {
            $res .= '<form action="' . Html::escapeURL($url) . '" method="post">' .
            '<h3>' . __('IP list') . '</h3>' .
                '<div class="antispam">';

            $res_global = '';
            $res_local  = '';
            while ($rs->fetch()) {
                $pattern = $rs->rule_content;

                $disabled_ip = false;
                $p_style     = '';
                if (!$rs->blog_id) {
                    $disabled_ip = !dotclear()->user()->isSuperAdmin();
                    $p_style .= ' global';
                }

                $item = '<p class="' . $p_style . '"><label class="classic" for="' . $type . '-ip-' . $rs->rule_id . '">' .
                Form::checkbox(['delip[]', $type . '-ip-' . $rs->rule_id], $rs->rule_id,
                    [
                        'disabled' => $disabled_ip
                    ]
                ) . ' ' .
                Html::escapeHTML($pattern) .
                    '</label></p>';

                if ($rs->blog_id) {
                    // local list
                    if ($res_local == '') {
                        $res_local = '<h4>' . __('Local IPs (used only for this blog)') . '</h4>';
                    }
                    $res_local .= $item;
                } else {
                    // global list
                    if ($res_global == '') {
                        $res_global = '<h4>' . __('Global IPs (used for all blogs)') . '</h4>';
                    }
                    $res_global .= $item;
                }
            }
            $res .= $res_local . $res_global;

            $res .= '</div>' .
            '<p><input class="submit delete" type="submit" value="' . __('Delete') . '"/>' .
            dotclear()->nonce()->form() .
            Form::hidden(['ip_type'], $type) .
                '</p>' .
                '</form>';
        }

        $res .= '</div>';

        return $res;
    }

    public function addIP($type, $pattern, $global)
    {
        $pattern = $this->compact($pattern);

        $old = $this->getRuleCIDR($type, $global, $pattern);
        $cur = dotclear()->con()->openCursor($this->table);

        if ($old->isEmpty()) {
            $id = dotclear()->con()->select('SELECT MAX(rule_id) FROM ' . $this->table)->asInt() + 1;

            $cur->rule_id      = $id;
            $cur->rule_type    = (string) $type;
            $cur->rule_content = (string) $pattern;

            if ($global && dotclear()->user()->isSuperAdmin()) {
                $cur->blog_id = null;
            } else {
                $cur->blog_id = dotclear()->blog()->id;
            }

            $cur->insert();
        } else {
            $cur->rule_type    = (string) $type;
            $cur->rule_content = (string) $pattern;
            $cur->update('WHERE rule_id = ' . (integer) $old->rule_id);
        }
    }

    private function getRules($type = 'all')
    {
        $strReq = 'SELECT rule_id, rule_type, blog_id, rule_content ' .
        'FROM ' . $this->table . ' ' .
        "WHERE rule_type = '" . dotclear()->con()->escape($type) . "' " .
        "AND (blog_id = '" . dotclear()->blog()->id . "' OR blog_id IS NULL) " .
            'ORDER BY blog_id ASC, rule_content ASC ';

        return dotclear()->con()->select($strReq);
    }

    private function getRuleCIDR($type, $global, $pattern)
    {
        // Search if we already have a rule for the given IP (ignoring mask in pattern if any)
        $this->ipmask($pattern, $ip, $mask);
        $ip = $this->long2ip_v6($ip);

        $strReq = 'SELECT * FROM ' . $this->table . ' ' .
        "WHERE rule_type = '" . dotclear()->con()->escape($type) . "' " .
        "AND rule_content LIKE '" . $ip . "%' " .
        'AND blog_id ' . ($global ? 'IS NULL ' : "= '" . dotclear()->blog()->id . "' ");

        return dotclear()->con()->select($strReq);
    }

    private function checkIP($cip, $type)
    {
        $strReq = 'SELECT DISTINCT(rule_content) ' .
        'FROM ' . $this->table . ' ' .
        "WHERE rule_type = '" . dotclear()->con()->escape($type) . "' " .
        "AND (blog_id = '" . dotclear()->blog()->id . "' OR blog_id IS NULL) " .
            'ORDER BY rule_content ASC ';

        $rs = dotclear()->con()->select($strReq);
        while ($rs->fetch()) {
            $pattern = $rs->rule_content;
            if ($this->inrange($cip, $pattern)) {
                return $pattern;
            }
        }

        return false;
    }

    private function removeRule($ids)
    {
        $strReq = 'DELETE FROM ' . $this->table . ' ';

        if (is_array($ids)) {
            foreach ($ids as $i => $v) {
                $ids[$i] = (integer) $v;
            }
            $strReq .= 'WHERE rule_id IN (' . implode(',', $ids) . ') ';
        } else {
            $ids = (integer) $ids;
            $strReq .= 'WHERE rule_id = ' . $ids . ' ';
        }

        if (!dotclear()->user()->isSuperAdmin()) {
            $strReq .= "AND blog_id = '" . dotclear()->blog()->id . "' ";
        }

        dotclear()->con()->execute($strReq);
    }

    private function compact($pattern)
    {
        // Compact the IP(s) in pattern
        $this->ipmask($pattern, $ip, $mask);
        $bits = explode('/', $pattern);
        $ip   = $this->long2ip_v6($ip);

        if (!isset($bits[1])) {
            // Only IP address
            return $ip;
        } elseif (strpos($bits[1], ':')) {
            // End IP address
            return $ip . '/' . $mask;
        } elseif ($mask === '1') {
            // Ignore mask
            return $ip;
        }
        // IP and mask
        return $ip . '/' . $bits[1];
    }

    private function inrange($value, $pattern)
    {
        // Check if an IP is inside the range given by the pattern
        $this->ipmask($pattern, $ipmin, $mask);
        $value = $this->ip2long_v6($value);

        $ipmax = '';
        if (strpos($mask, ':')) {
            // the mask is the last address of range
            $ipmax = $this->ip2long_v6($mask);
            if (function_exists('gmp_init')) {
                $ipmax = gmp_init($ipmax, 10);
            }
        } else {
            // the mask is the number of addresses in range
            if (function_exists('gmp_init')) {
                $ipmax = gmp_add(gmp_init($ipmin, 10), gmp_sub(gmp_init($mask, 10), gmp_init(1)));
            } elseif (function_exists('bcadd')) {
                $ipmax = bcadd($ipmin, bcsub($mask, '1'));    // @phpstan-ignore-line
            } else {
                trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
            }
        }

        $min = $max = 0;
        if (function_exists('gmp_init')) {
            $min = gmp_cmp(gmp_init($value, 10), gmp_init($ipmin, 10));
            $max = gmp_cmp(gmp_init($value, 10), $ipmax);
        } elseif (function_exists('bcadd')) {
            $min = bccomp($value, $ipmin);
            $max = bccomp($value, $ipmax);
        } else {
            trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
        }

        return (($min >= 0) && ($max <= 0));
    }

    private function ipmask($pattern, &$ip, &$mask)
    {
        // Analyse pattern returning IP and mask if any
        // returned mask = IP address or number of addresses in range
        $bits = explode('/', $pattern);

        if (!filter_var($bits[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new \Exception('Invalid IPv6 address');
        }

        $ip = $this->ip2long_v6($bits[0]);

        if (!$ip || $ip == -1) {
            throw new \Exception('Invalid IP address');
        }

        # Set mask
        if (!isset($bits[1])) {
            $mask = '1';
        } elseif (strpos($bits[1], ':')) {
            $mask = $this->ip2long_v6($bits[1]);
            if (!$mask) {
                $mask = '1';
            } else {
                $mask = $this->long2ip_v6($mask);
            }
        } else {
            //$mask = ~((1 << (128 - min((integer) $bits[1], 128))) - 1);
            if (function_exists('gmp_init')) {
                $mask = gmp_mul(gmp_init(1), gmp_pow(gmp_init(2), 128 - min((integer) $bits[1], 128)));
            } elseif (function_exists('bcadd')) {
                $mask = bcmul(1, bcpow(2, 128 - min((integer) $bits[1], 128))); // @phpstan-ignore-line
            } else {
                trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
            }
        }
    }

    private function ip2long_v6($ip)
    {
        // Convert IP v6 to long integer
        $ip_n = inet_pton($ip);
        $bin  = '';
        for ($bit = strlen($ip_n) - 1; $bit >= 0; $bit--) {
            $bin = sprintf('%08b', ord($ip_n[$bit])) . $bin;
        }

        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($bin, 2), 10);
        } elseif (function_exists('bcadd')) {
            $dec = '0';
            for ($i = 0; $i < strlen($bin); $i++) {
                $dec = bcmul($dec, '2', 0);
                $dec = bcadd($dec, $bin[$i], 0);
            }

            return $dec;
        }
        trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
    }

    private function long2ip_v6($dec)
    {
        $bin = '';
        // Convert long integer to IP v6
        if (function_exists('gmp_init')) {
            $bin = gmp_strval(gmp_init($dec, 10), 2);
        } elseif (function_exists('bcadd')) {
            $bin = '';
            do {
                $bin = bcmod($dec, '2') . $bin;
                $dec = bcdiv($dec, '2', 0);
            } while (bccomp($dec, '0'));
        } else {
            trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
        }

        $bin = str_pad($bin, 128, '0', STR_PAD_LEFT);
        $ip  = [];
        for ($bit = 0; $bit <= 7; $bit++) {
            $bin_part = substr($bin, $bit * 16, 16);
            $ip[]     = dechex(bindec($bin_part));
        }
        $ip = implode(':', $ip);

        return inet_ntop(inet_pton($ip));
    }
}
