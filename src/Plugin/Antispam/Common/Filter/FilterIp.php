<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common\Filter;

// Dotclear\Plugin\Antispam\Common\Filter\FilterIp
use Dotclear\App;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Antispam\Common\Spamfilter;
use Exception;

/**
 * Antispam IP filter.
 *
 * @ingroup  Plugin Antispam
 */
class FilterIp extends Spamfilter
{
    public $name    = 'IP Filter';
    public $has_gui = true;
    public $help    = 'ip-filter';

    private $tab;

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

        // White list check
        if (false !== $this->checkIP($ip, 'white')) {
            return false;
        }

        // Black list check
        if (false !== ($s = $this->checkIP($ip, 'black'))) {
            $status = $s;

            return true;
        }

        return null;
    }

    public function gui(string $url): string
    {
        // Set current type and tab
        $ip_type = 'black';
        if ('white' == GPC::request()->string('ip_type')) {
            $ip_type = 'white';
        }
        $this->tab = 'tab_' . $ip_type;

        // Add IP to list
        if (!GPC::post()->empty('addip')) {
            try {
                $global = !GPC::post()->empty('globalip') && App::core()->user()->isSuperAdmin();

                $this->addIP($ip_type, GPC::post()->string('addip'), $global);
                App::core()->notice()->addSuccessNotice(__('IP address has been successfully added.'));
                Http::redirect($url . '&ip_type=' . $ip_type);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Remove IP from list
        if (!GPC::post()->empty('delip')) {
            try {
                $this->removeRule(GPC::post()->array('delip'));
                App::core()->notice()->addSuccessNotice(__('IP addresses have been successfully removed.'));
                Http::redirect($url . '&ip_type=' . $ip_type);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        /* DISPLAY
        ---------------------------------------------- */
        return $this->displayForms($url, 'black', __('Blocklist')) .
        $this->displayForms($url, 'white', __('Allowlist'));
    }

    public function guiTab(): ?string
    {
        return $this->tab;
    }

    private function displayForms(string $url, string $type, string $title): string
    {
        $res = '<div class="multi-part" id="tab_' . $type . '" title="' . $title . '">' .

        '<form action="' . html::escapeURL($url) . '" method="post" class="fieldset">' .

        '<p>' .
        form::hidden(['ip_type'], $type) .
        '<label class="classic" for="addip_' . $type . '">' . __('Add an IP address: ') . '</label> ' .
        form::field(['addip', 'addip_' . $type], 18, 255);
        if (App::core()->user()->isSuperAdmin()) {
            $res .= '<label class="classic" for="globalip_' . $type . '">' . form::checkbox(['globalip', 'globalip_' . $type], 1) . ' ' .
            __('Global IP (used for all blogs)') . '</label> ';
        }

        $res .= App::core()->nonce()->form() .
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
                $bits    = explode(':', $rs->f('rule_content'));
                $pattern = $bits[0];
                $ip      = $bits[1];
                $bitmask = $bits[2];

                $disabled_ip = false;
                $p_style     = '';
                if (!$rs->f('blog_id')) {
                    $disabled_ip = !App::core()->user()->isSuperAdmin();
                    $p_style .= ' global';
                }

                $item = '<p class="' . $p_style . '"><label class="classic" for="' . $type . '-ip-' . $rs->f('rule_id') . '">' .
                form::checkbox(
                    ['delip[]', $type . '-ip-' . $rs->f('rule_id')],
                    $rs->f('rule_id'),
                    [
                        'disabled' => $disabled_ip,
                    ]
                ) . ' ' .
                html::escapeHTML($pattern) .
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
            App::core()->nonce()->form() .
            form::hidden(['ip_type'], $type) .
                '</p>' .
                '</form>';
        }

        $res .= '</div>';

        return $res;
    }

    private function ipmask(string $pattern, &$ip, &$mask): void
    {
        $bits = explode('/', $pattern);

        // Set IP
        $bits[0] .= str_repeat('.0', 3 - substr_count($bits[0], '.'));

        if (!filter_var($bits[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new Exception('Invalid IPv4 address');
        }

        $ip = ip2long($bits[0]);

        if (!$ip || -1 == $ip) {
            throw new Exception('Invalid IP address');
        }

        // Set mask
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

    public function addIP(string $type, string $pattern, bool $global): void
    {
        $this->ipmask($pattern, $ip, $mask);
        $pattern = long2ip($ip) . (-1 != $mask ? '/' . long2ip($mask) : '');
        $content = $pattern . ':' . $ip . ':' . $mask;

        $old = $this->getRuleCIDR($type, $global, $ip, $mask);
        $cur = App::core()->con()->openCursor(App::core()->prefix() . 'spamrule');

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
                        ->from(App::core()->prefix() . 'spamrule')
                        ->select()
                        ->fInt() + 1,
                    $sql->quote($type),
                    $sql->quote($content),
                    $global && App::core()->user()->isSuperAdmin() ? 'NULL' : $sql->quote(App::core()->blog()->id),
                ]])
                ->from(App::core()->prefix() . 'spamrule')
                ->insert()
            ;
        } else {
            $sql = new UpdateStatement(__METHOD__);
            $sql
                ->set('rule_type = ' . $sql->quote($type))
                ->set('rule_content = ' . $sql->quote($content))
                ->where('rule_id = ' . $old->fInt('rule_id'))
                ->from(App::core()->prefix() . 'spamrule')
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
                'blog_id = ' . $sql->quote(App::core()->blog()->id),
                'blog_id IS NULL',
            ]))
            ->order([
                'blog_id ASC',
                'rule_content ASC',
            ])
            ->from(App::core()->prefix() . 'spamrule')
            ->select()
        ;
    }

    private function getRuleCIDR(string $type, bool $global, $ip, $mask): Record
    {
        $sql = new SelectStatement(__METHOD__);

        return $sql
            ->column('*')
            ->where('rule_type = ' . $sql->quote($type))
            ->and($sql->like('rule_content', '%:' . (int) $ip . ':' . (int) $mask))
            ->and(
                $global ?
                'blog_id IS NULL' :
                'blog_id = ' . $sql->quote(App::core()->blog()->id)
            )
            ->from(App::core()->prefix() . 'spamrule')
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
                'blog_id = ' . $sql->quote(App::core()->blog()->id),
                'blog_id IS NULL',
            ]))
            ->order('rule_content ASC')
            ->from(App::core()->prefix() . 'spamrule')
            ->select()
        ;

        while ($rs->fetch()) {
            [$pattern, $ip, $mask] = explode(':', $rs->f('rule_content'));
            if ((ip2long($cip) & (int) $mask) == ((int) $ip & (int) $mask)) {
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

        if (!App::core()->user()->isSuperAdmin()) {
            $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        }

        $sql
            ->from(App::core()->prefix() . 'spamrule')
            ->delete()
        ;
    }
}
