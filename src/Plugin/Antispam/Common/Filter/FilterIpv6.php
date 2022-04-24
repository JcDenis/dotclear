<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common\Filter;

// Dotclear\Plugin\Antispam\Common\Filter\FilterIpv6
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Antispam\Common\Spamfilter;
use Exception;

/**
 * Antispam IPv6 filter.
 *
 * @ingroup  Plugin Antispam
 */
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

        // White list check
        if (false !== $this->checkIP($ip, 'whitev6')) {
            return false;
        }

        // Black list check
        if (false !== ($s = $this->checkIP($ip, 'blackv6'))) {
            $status = $s;

            return true;
        }

        return null;
    }

    public function gui(string $url): string
    {
        // Set current type and tab
        $ip_type = 'blackv6';
        if (!empty($_REQUEST['ip_type']) && 'whitev6' == $_REQUEST['ip_type']) {
            $ip_type = 'whitev6';
        }
        $this->tab = 'tab_' . $ip_type;

        // Add IP to list
        if (!empty($_POST['addip'])) {
            try {
                $global = !empty($_POST['globalip']) && dotclear()->user()->isSuperAdmin();

                $this->addIP($ip_type, $_POST['addip'], $global);
                dotclear()->notice()->addSuccessNotice(__('IP address has been successfully added.'));
                Http::redirect($url . '&ip_type=' . $ip_type);
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        // Remove IP from list
        if (!empty($_POST['delip']) && is_array($_POST['delip'])) {
            try {
                $this->removeRule($_POST['delip']);
                dotclear()->notice()->addSuccessNotice(__('IP addresses have been successfully removed.'));
                Http::redirect($url . '&ip_type=' . $ip_type);
            } catch (Exception $e) {
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

    private function displayForms(string $url, string $type, string $title): string
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
                $disabled_ip = false;
                $p_style     = '';
                if (!$rs->f('blog_id')) {
                    $disabled_ip = !dotclear()->user()->isSuperAdmin();
                    $p_style .= ' global';
                }

                $item = '<p class="' . $p_style . '"><label class="classic" for="' . $type . '-ip-' . $rs->f('rule_id') . '">' .
                Form::checkbox(
                    ['delip[]', $type . '-ip-' . $rs->f('rule_id')],
                    $rs->f('rule_id'),
                    [
                        'disabled' => $disabled_ip,
                    ]
                ) . ' ' .
                Html::escapeHTML($rs->f('rule_content')) .
                    '</label></p>';

                if ($rs->f('blog_id')) {
                    // local list
                    if ('' == $res_local) {
                        $res_local = '<h4>' . __('Local IPs (used only for this blog)') . '</h4>';
                    }
                    $res_local .= $item;
                } else {
                    // global list
                    if ('' == $res_global) {
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

    public function addIP(string $type, string $pattern, bool $global): void
    {
        $pattern = $this->compact($pattern);

        $old = $this->getRuleCIDR($type, $global, $pattern);
        $cur = dotclear()->con()->openCursor($this->table);

        if ($old->isEmpty()) {
            $sql = new InsertStatement(__METHOD__);
            $sql
                ->columns([
                    'rule_id',
                    'rule_type',
                    'rule_content',
                    'blog_id',
                ])
                ->line([[
                    SelectStatement::init(__METHOD__)
                        ->columns($sql->max('rule_id'))
                        ->from($this->table)
                        ->select()
                        ->fInt() + 1,
                    $sql->quote($type),
                    $sql->quote($pattern),
                    $global && dotclear()->user()->isSuperAdmin() ? 'NULL' : $sql->quote(dotclear()->blog()->id),
                ]])
                ->from($this->table)
                ->insert()
            ;
        } else {
            $sql = new UpdateStatement(__METHOD__);
            $sql
                ->set('rule_type = ' . $sql->quote($type))
                ->set('rule_content = ' . $sql->quote($pattern))
                ->where('rule_id = ' . $old->fInt('rule_id'))
                ->from($this->table)
                ->update()
            ;
        }
    }

    private function getRules(string $type = 'all'): Record
    {
        $sql = new SelectStatement(__METHOD__);

        return $sql
            ->columns([
                'rule_id',
                'rule_type',
                'blog_id',
                'rule_content',
            ])
            ->where('rule_type = ' . $sql->quote($type))
            ->and($sql->orGroup([
                'blog_id = ' . $sql->quote(dotclear()->blog()->id),
                'blog_id IS NULL',
            ]))
            ->order([
                'blog_id ASC',
                'rule_content ASC',
            ])
            ->from($this->table)
            ->select()
        ;
    }

    private function getRuleCIDR(string $type, bool $global, string $pattern): Record
    {
        // Search if we already have a rule for the given IP (ignoring mask in pattern if any)
        $this->ipmask($pattern, $ip, $mask);
        $ip = $this->long2ip_v6($ip);

        $sql = new SelectStatement(__METHOD__);

        return $sql
            ->column('*')
            ->where('rule_type = ' . $sql->quote($type))
            ->and($sql->like('rule_content', $ip . '%'))
            ->and(
                $global ?
                'blog_id IS NULL' :
                'blog_id = ' . $sql->quote(dotclear()->blog()->id)
            )
            ->from($this->table)
            ->select()
        ;
    }

    private function checkIP(string $cip, string $type): string|false
    {
        $sql = new SelectStatement(__METHOD__);
        $rs  = $sql
            ->distinct()
            ->column('rule_content')
            ->where('rule_type = ' . $sql->quote($type))
            ->and($sql->orGroup([
                'blog_id = ' . $sql->quote(dotclear()->blog()->id),
                'blog_id IS NULL',
            ]))
            ->order('rule_content ASC')
            ->from($this->table)
            ->select()
        ;

        while ($rs->fetch()) {
            $pattern = $rs->f('rule_content');
            if ($this->inrange($cip, $pattern)) {
                return $pattern;
            }
        }

        return false;
    }

    private function removeRule(int|array $ids): void
    {
        $sql = new DeleteStatement(__METHOD__);

        if (is_array($ids)) {
            foreach ($ids as $i => $v) {
                $ids[$i] = (int) $v;
            }
            $sql->where('rule_id' . $sql->in($ids));
        } else {
            $sql->where('rule_id = ' . $ids);
        }

        if (!dotclear()->user()->isSuperAdmin()) {
            $sql->and('blog_id = ' . $sql->quote(dotclear()->blog()->id));
        }

        $sql
            ->from($this->table)
            ->delete()
        ;
    }

    private function compact(string $pattern): string
    {
        // Compact the IP(s) in pattern
        $this->ipmask($pattern, $ip, $mask);
        $bits = explode('/', $pattern);
        $ip   = $this->long2ip_v6($ip);

        if (!isset($bits[1])) {
            // Only IP address
            return $ip;
        }
        if (strpos($bits[1], ':')) {
            // End IP address
            return $ip . '/' . $mask;
        }
        if ('1' === $mask) {
            // Ignore mask
            return $ip;
        }
        // IP and mask
        return $ip . '/' . $bits[1];
    }

    private function inrange(string $value, string $pattern): bool
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
                $ipmax = bcadd($ipmin, bcsub($mask, '1'));
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

        return 0 <= $min && 0 >= $max;
    }

    private function ipmask(string $pattern, &$ip, &$mask): void
    {
        // Analyse pattern returning IP and mask if any
        // returned mask = IP address or number of addresses in range
        $bits = explode('/', $pattern);

        if (!filter_var($bits[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new Exception('Invalid IPv6 address');
        }

        $ip = $this->ip2long_v6($bits[0]);

        if (!$ip || -1 == $ip) {
            throw new Exception('Invalid IP address');
        }

        // Set mask
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
            // $mask = ~((1 << (128 - min((int) $bits[1], 128))) - 1);
            if (function_exists('gmp_init')) {
                $mask = gmp_mul(gmp_init(1), gmp_pow(gmp_init(2), 128 - min((int) $bits[1], 128)));
            } elseif (function_exists('bcadd')) {
                $mask = bcmul('1', bcpow('2', (string) (128 - min((int) $bits[1], 128))));
            } else {
                trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
            }
        }
    }

    private function ip2long_v6(string $ip): string
    {
        // Convert IP v6 to long integer
        $ip_n = inet_pton($ip);
        $bin  = '';
        for ($bit = strlen($ip_n) - 1; 0 <= $bit; --$bit) {
            $bin = sprintf('%08b', ord($ip_n[$bit])) . $bin;
        }

        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($bin, 2), 10);
        }
        if (function_exists('bcadd')) {
            $dec = '0';
            for ($i = 0; strlen($bin) > $i; ++$i) {
                $dec = bcmul($dec, '2', 0);
                $dec = bcadd($dec, $bin[$i], 0);
            }

            return $dec;
        }
        trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
    }

    private function long2ip_v6(string $dec): string
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
        for ($bit = 0; 7 >= $bit; ++$bit) {
            $bin_part = substr($bin, $bit * 16, 16);
            $ip[]     = dechex(bindec($bin_part));
        }
        $ip = implode(':', $ip);

        return inet_ntop(inet_pton($ip));
    }
}
