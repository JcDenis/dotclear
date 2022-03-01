<?php
/**
 * @class Dotclear\Plugin\Antispam\Common\Filter\FilterIp
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

use Dotclear\Html\Html;
Use Dotclear\Html\Form;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class FilterIp extends Spamfilter
{
    public $name    = 'IP Filter';
    public $has_gui = true;
    public $help    = 'ip-filter';

    private $con;
    private $table;
    private $tab;

    public function __construct()
    {
        parent::__construct();
        $this->table = dotclear()->prefix . 'spamrule';
    }

    protected function setInfo(): void
    {
        $this->description = __('IP Blocklist / Allowlist Filter');
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
        if ($this->checkIP($ip, 'white') !== false) {
            return false;
        }

        # Black list check
        if (($s = $this->checkIP($ip, 'black')) !== false) {
            $status = $s;

            return true;
        }

        return null;
    }

    public function gui(string $url): string
    {
        # Set current type and tab
        $ip_type = 'black';
        if (!empty($_REQUEST['ip_type']) && $_REQUEST['ip_type'] == 'white') {
            $ip_type = 'white';
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
        $res = $this->displayForms($url, 'black', __('Blocklist')) .
        $this->displayForms($url, 'white', __('Allowlist'));

        return $res;
    }

    public function guiTab(): ?string
    {
        return $this->tab;
    }

    private function displayForms($url, $type, $title)
    {
        $res = '<div class="multi-part" id="tab_' . $type . '" title="' . $title . '">' .

        '<form action="' . html::escapeURL($url) . '" method="post" class="fieldset">' .

        '<p>' .
        form::hidden(['ip_type'], $type) .
        '<label class="classic" for="addip_' . $type . '">' . __('Add an IP address: ') . '</label> ' .
        form::field(['addip', 'addip_' . $type], 18, 255);
        if (dotclear()->user()->isSuperAdmin()) {
            $res .= '<label class="classic" for="globalip_' . $type . '">' . form::checkbox(['globalip', 'globalip_' . $type], 1) . ' ' .
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
            $res .= '<form action="' . html::escapeURL($url) . '" method="post">' .
            '<h3>' . __('IP list') . '</h3>' .
                '<div class="antispam">';

            $res_global = '';
            $res_local  = '';
            while ($rs->fetch()) {
                $bits    = explode(':', $rs->rule_content);
                $pattern = $bits[0];
                $ip      = $bits[1];
                $bitmask = $bits[2];

                $disabled_ip = false;
                $p_style     = '';
                if (!$rs->blog_id) {
                    $disabled_ip = !dotclear()->user()->isSuperAdmin();
                    $p_style .= ' global';
                }

                $item = '<p class="' . $p_style . '"><label class="classic" for="' . $type . '-ip-' . $rs->rule_id . '">' .
                form::checkbox(['delip[]', $type . '-ip-' . $rs->rule_id], $rs->rule_id,
                    [
                        'disabled' => $disabled_ip
                    ]
                ) . ' ' .
                html::escapeHTML($pattern) .
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
            form::hidden(['ip_type'], $type) .
                '</p>' .
                '</form>';
        }

        $res .= '</div>';

        return $res;
    }

    private function ipmask($pattern, &$ip, &$mask)
    {
        $bits = explode('/', $pattern);

        # Set IP
        $bits[0] .= str_repeat('.0', 3 - substr_count($bits[0], '.'));

        if (!filter_var($bits[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new \Exception('Invalid IPv4 address');
        }

        $ip = ip2long($bits[0]);

        if (!$ip || $ip == -1) {
            throw new \Exception('Invalid IP address');
        }

        # Set mask
        if (!isset($bits[1])) {
            $mask = -1;
        } elseif (strpos($bits[1], '.')) {
            $mask = ip2long($bits[1]);
            if (!$mask) {
                $mask = -1;
            }
        } else {
            $mask = ~((1 << (32 - min((int) $bits[1], 32))) - 1);
        }
    }

    public function addIP($type, $pattern, $global)
    {
        $this->ipmask($pattern, $ip, $mask);
        $pattern = long2ip($ip) . ($mask != -1 ? '/' . long2ip($mask) : '');
        $content = $pattern . ':' . $ip . ':' . $mask;

        $old = $this->getRuleCIDR($type, $global, $ip, $mask);
        $cur = dotclear()->con()->openCursor($this->table);

        if ($old->isEmpty()) {
            $id = dotclear()->con()->select('SELECT MAX(rule_id) FROM ' . $this->table)->f(0) + 1;

            $cur->rule_id      = $id;
            $cur->rule_type    = (string) $type;
            $cur->rule_content = (string) $content;

            if ($global && dotclear()->user()->isSuperAdmin()) {
                $cur->blog_id = null;
            } else {
                $cur->blog_id = dotclear()->blog()->id;
            }

            $cur->insert();
        } else {
            $cur->rule_type    = (string) $type;
            $cur->rule_content = (string) $content;
            $cur->update('WHERE rule_id = ' . (int) $old->rule_id);
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

    private function getRuleCIDR($type, $global, $ip, $mask)
    {
        $strReq = 'SELECT * FROM ' . $this->table . ' ' .
        "WHERE rule_type = '" . dotclear()->con()->escape($type) . "' " .
        "AND rule_content LIKE '%:" . (int) $ip . ':' . (int) $mask . "' " .
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
            list($pattern, $ip, $mask) = explode(':', $rs->rule_content);
            if ((ip2long($cip) & (int) $mask) == ((int) $ip & (int) $mask)) {
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
                $ids[$i] = (int) $v;
            }
            $strReq .= 'WHERE rule_id IN (' . implode(',', $ids) . ') ';
        } else {
            $ids = (int) $ids;
            $strReq .= 'WHERE rule_id = ' . $ids . ' ';
        }

        if (!dotclear()->user()->isSuperAdmin()) {
            $strReq .= "AND blog_id = '" . dotclear()->blog()->id . "' ";
        }

        dotclear()->con()->execute($strReq);
    }
}
