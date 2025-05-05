<?php

/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\antispam\Filters;

use Dotclear\Core\Backend\Notices;
use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Strong;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\antispam\Antispam;
use Dotclear\Plugin\antispam\SpamFilter;
use Exception;

/**
 * @brief   The module IPv6 spam filter.
 * @ingroup antispam
 */
class IpV6 extends SpamFilter
{
    /**
     * Filter id.
     */
    public string $id = 'dcFilterIPv6';

    /**
     * Filter name.
     */
    public string $name = 'IP Filter v6';

    /**
     * Filter has settings GUI?
     */
    public bool $has_gui = true;

    /**
     * Filter help ID.
     */
    public ?string $help = 'ip-filter-v6';

    /**
     * Table name.
     */
    private readonly string $table;

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->table = App::con()->prefix() . Antispam::SPAMRULE_TABLE_NAME;
    }

    /**
     * Sets the filter description.
     */
    protected function setInfo(): void
    {
        $this->description = __('IP v6 Blocklist / Allowlist Filter');
    }

    /**
     * Gets the status message.
     *
     * @param   string  $status         The status
     * @param   int     $comment_id     The comment identifier
     *
     * @return  string  The status message.
     */
    public function getStatusMessage(string $status, ?int $comment_id): string
    {
        return sprintf(__('Filtered by %1$s with rule %2$s.'), $this->guiLink(), $status);
    }

    /**
     * This method should return if a comment is a spam or not.
     *
     * If it returns true or false, execution of next filters will be stoped.
     * If should return nothing to let next filters apply.
     *
     * @param   string  $type       The comment type (comment / trackback)
     * @param   string  $author     The comment author
     * @param   string  $email      The comment author email
     * @param   string  $site       The comment author site
     * @param   string  $ip         The comment author IP
     * @param   string  $content    The comment content
     * @param   int     $post_id    The comment post_id
     * @param   string  $status     The comment status
     */
    public function isSpam(string $type, ?string $author, ?string $email, ?string $site, ?string $ip, ?string $content, ?int $post_id, string &$status): ?bool
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
            $status = (string) $s;

            return true;
        }

        return null;
    }

    /**
     * Filter settings.
     *
     * @param   string  $url    The GUI URL
     */
    public function gui(string $url): string
    {
        # Set current type and tab
        $ip_type = 'blackv6';
        if (!empty($_REQUEST['ip_type']) && $_REQUEST['ip_type'] == 'whitev6') {
            $ip_type = 'whitev6';
        }
        App::backend()->default_tab = 'tab_' . $ip_type;

        # Add IP to list
        if (!empty($_POST['addip'])) {
            try {
                $global = !empty($_POST['globalip']) && App::auth()->isSuperAdmin();

                $this->addIP($ip_type, $_POST['addip'], $global);
                Notices::addSuccessNotice(__('IP address has been successfully added.'));
                Http::redirect($url . '&ip_type=' . $ip_type);
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        # Remove IP from list
        if (!empty($_POST['delip']) && is_array($_POST['delip'])) {
            try {
                $this->removeRule($_POST['delip']);
                Notices::addSuccessNotice(__('IP addresses have been successfully removed.'));
                Http::redirect($url . '&ip_type=' . $ip_type);
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        // Display
        return
        $this->displayForms($url, 'blackv6', __('Blocklist')) .
        $this->displayForms($url, 'whitev6', __('Allowlist'));
    }

    /**
     * Return black/white list form.
     *
     * @param   string  $url    The url
     * @param   string  $type   The type
     * @param   string  $title  The title
     */
    private function displayForms(string $url, string $type, string $title): string
    {
        $rs = $this->getRules($type);
        if ($rs->isEmpty()) {
            $rules_form = (new Para())->items([
                (new Strong(__('No IP address in list.'))),
            ]);
        } else {
            $rules_local  = [];
            $rules_global = [];
            while ($rs->fetch()) {
                $pattern = $rs->rule_content;

                $disabled_ip = false;
                if (!$rs->blog_id) {
                    $disabled_ip = !App::auth()->isSuperAdmin();
                }

                $rule = (new Checkbox(['delip[]', $type . '-ip-' . $rs->rule_id]))
                    ->value($rs->rule_id)
                    ->label((new Label(Html::escapeHTML($pattern), Label::INSIDE_LABEL_AFTER)))
                    ->disabled($disabled_ip);
                if ($rs->blog_id) {
                    $rules_local[] = $rule;
                } else {
                    $rules_global[] = $rule;
                }
            }

            $local = $global = [];
            if ($rules_local !== []) {
                $local = [
                    (new Fieldset())
                        ->legend((new Legend(__('Local IPs (used only for this blog)'))))
                        ->class('two-boxes')
                        ->items($rules_local),
                ];
            }
            if ($rules_global !== []) {
                $global = [
                    (new Fieldset())
                        ->legend((new Legend(__('Global IPs (used for all blogs)'))))
                        ->class('two-boxes')
                        ->items($rules_global),
                ];
            }

            $rules_form = (new Form('form_rules_' . $type))
                ->action(Html::escapeURL($url))
                ->method('post')
                ->fields([
                    (new Fieldset())
                        ->legend((new Legend(__('IP list'))))
                        ->fields([
                            ...$local,
                            ...$global,
                            (new Para())->items([
                                (new Hidden(['ip_type'], $type)),
                                (new Submit('rules_delete_' . $type, __('Delete')))->class('delete'),
                                App::nonce()->formNonce(),
                            ]),
                        ]),
                ]);
        }

        $super = '';
        if (App::auth()->isSuperAdmin()) {
            $super = (new Checkbox(['globalip', 'globalip_' . $type]))
                ->label((new Label(__('Global IP (used for all blogs)'), Label::INSIDE_LABEL_AFTER))->class('classic'))
            ->render();
        }

        return (new Div('tab_' . $type))
            ->class('multi-part')
            ->title($title)
            ->items([
                (new Form('form_' . $type))
                    ->action(Html::escapeURL($url))
                    ->method('post')
                    ->class('fieldset')
                    ->fields([
                        (new Para())->items([
                            (new Input(['addip', 'addip_' . $type]))
                                ->size(32)
                                ->maxlength(255)
                                ->label((new Label(__('Add an IP address:'), Label::INSIDE_TEXT_BEFORE))->suffix($super)),
                        ]),
                        (new Para())->items([
                            (new Hidden(['ip_type'], $type)),
                            (new Submit('save_' . $type, __('Add'))),
                            App::nonce()->formNonce(),
                        ]),
                    ]),
                $rules_form,
            ])
        ->render();
    }

    /**
     * Adds an IP rule.
     *
     * @param   string  $type       The type
     * @param   string  $pattern    The pattern
     * @param   bool    $global     The global
     */
    public function addIP(string $type, string $pattern, bool $global): void
    {
        $pattern = $this->compact($pattern);

        $old = $this->getRuleCIDR($type, $global, $pattern);
        $cur = App::con()->openCursor($this->table);

        if ($old->isEmpty()) {
            $sql = new SelectStatement();
            $run = $sql
                ->column($sql->max('rule_id'))
                ->from($this->table)
                ->select();
            $max = $run instanceof MetaRecord ? $run->f(0) : 0;

            $cur->rule_id      = $max + 1;
            $cur->rule_type    = $type;
            $cur->rule_content = $pattern;
            $cur->blog_id      = $global && App::auth()->isSuperAdmin() ? null : App::blog()->id();

            $cur->insert();
        } else {
            $cur->rule_type    = $type;
            $cur->rule_content = $pattern;

            $sql = new UpdateStatement();
            $sql
                ->where('rule_id = ' . $old->rule_id)
                ->update($cur);
        }
    }

    /**
     * Gets the rules.
     *
     * @param   string  $type   The type
     *
     * @return  MetaRecord  The rules.
     */
    private function getRules(string $type = 'all'): MetaRecord
    {
        $sql = new SelectStatement();

        return $sql
            ->columns([
                'rule_id',
                'rule_type',
                'blog_id',
                'rule_content',
            ])
            ->from($this->table)
            ->where('rule_type = ' . $sql->quote($type))
            ->and($sql->orGroup([
                'blog_id = ' . $sql->quote(App::blog()->id()),
                'blog_id IS NULL',
            ]))
            ->order([
                'blog_id ASC',
                'rule_content ASC',
            ])
            ->select() ?? MetaRecord::newFromArray([]);
    }

    /**
     * Gets the rule CIDR.
     *
     * @param   string  $type       The type
     * @param   bool    $global     The global
     * @param   string  $pattern    The pattern
     *
     * @return  MetaRecord  The rules.
     */
    private function getRuleCIDR(string $type, bool $global, string $pattern): MetaRecord
    {
        // Search if we already have a rule for the given IP (ignoring mask in pattern if any)
        $this->ipmask($pattern, $ip, $mask);
        $ip = $this->long2ip_v6($ip);

        $sql = new SelectStatement();

        return $sql
            ->column('*')
            ->from($this->table)
            ->where('rule_type = ' . $sql->quote($type))
            ->and($sql->like('rule_content', $ip . '%'))
            ->and($global ? 'blog_id IS NULL' : 'blog_id = ' . $sql->quote(App::blog()->id()))
            ->select() ?? MetaRecord::newFromArray([]);
    }

    /**
     * Check an IP.
     *
     * @param   string  $cip    The IP
     * @param   string  $type   The type
     *
     * @return  bool|string
     */
    private function checkIP(string $cip, string $type)
    {
        $sql = new SelectStatement();
        $rs  = $sql
            ->distinct()
            ->column('rule_content')
            ->from($this->table)
            ->where('rule_type = ' . $sql->quote($type))
            ->and($sql->orGroup([
                'blog_id = ' . $sql->quote(App::blog()->id()),
                'blog_id IS NULL',
            ]))
            ->order('rule_content ASC')
            ->select();

        if ($rs instanceof MetaRecord) {
            while ($rs->fetch()) {
                $pattern = $rs->rule_content;
                if ($this->inrange($cip, $pattern)) {
                    return $pattern;
                }
            }
        }

        return false;
    }

    /**
     * Removes a rule.
     *
     * @param   mixed   $ids    The rules identifiers
     */
    private function removeRule($ids): void
    {
        $sql = new DeleteStatement();

        if (is_array($ids)) {
            foreach ($ids as $i => $v) {
                $ids[$i] = (int) $v;
            }
        } else {
            $ids = [(int) $ids];
        }

        if (!App::auth()->isSuperAdmin()) {
            $sql->and('blog_id = ' . $sql->quote(App::blog()->id()));
        }

        $sql
            ->from($this->table)
            ->where('rule_id' . $sql->in($ids));

        $sql->delete();
    }

    /**
     * Compact IPv6 pattern.
     *
     * @param   string  $pattern    The pattern
     */
    private function compact(string $pattern): string
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

    /**
     * Check if an IP is inside the range given by the pattern.
     *
     * @param   string  $ip         The IP
     * @param   string  $pattern    The pattern
     */
    private function inrange(string $ip, string $pattern): bool
    {
        $this->ipmask($pattern, $ipmin, $mask);
        $value = $this->ip2long_v6($ip);

        $ipmax = '';
        if (strpos((string) $mask, ':')) {
            // the mask is the last address of range
            $ipmax = $this->ip2long_v6($mask);
            if (function_exists('gmp_init')) {
                $ipmax = gmp_init($ipmax, 10);
            }
        } elseif (function_exists('gmp_init')) {
            // the mask is the number of addresses in range
            $ipmax = gmp_add(gmp_init($ipmin, 10), gmp_sub(gmp_init($mask, 10), gmp_init(1)));
        } elseif (function_exists('bcadd')) {
            $ipmax = bcadd((string) $ipmin, bcsub((string) $mask, '1'));    // @phpstan-ignore-line
        } else {
            trigger_error('GMP or BCMATH extension not installed!', E_USER_WARNING);
        }

        $min = $max = 0;
        if (function_exists('gmp_init')) {
            $min = gmp_cmp(gmp_init($value, 10), gmp_init($ipmin, 10));
            $max = gmp_cmp(gmp_init($value, 10), $ipmax);
        } elseif (function_exists('bcadd')) {
            $min = bccomp($value, (string) $ipmin);  // @phpstan-ignore-line
            $max = bccomp($value, $ipmax);  // @phpstan-ignore-line
        } else {
            trigger_error('GMP or BCMATH extension not installed!', E_USER_WARNING);
        }

        return ($min >= 0) && ($max <= 0);
    }

    /**
     * Extract IP and mask from rule pattern.
     *
     * @param   string  $pattern    The pattern
     * @param   mixed   $ip         The IP
     * @param   mixed   $mask       The mask
     *
     * @throws  Exception
     */
    private function ipmask(string $pattern, &$ip, &$mask): void
    {
        // Analyse pattern returning IP and mask if any
        // returned mask = IP address or number of addresses in range
        $bits = explode('/', $pattern);

        if (!filter_var($bits[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new Exception('Invalid IPv6 address');
        }

        $ip = $this->ip2long_v6($bits[0]);

        if (!$ip || $ip == -1) {
            throw new Exception('Invalid IP address');
        }

        # Set mask
        if (!isset($bits[1])) {
            $mask = '1';
        } elseif (strpos($bits[1], ':')) {
            $mask = $this->ip2long_v6($bits[1]);
            $mask = $mask === '' ? '1' : $this->long2ip_v6($mask);
        } elseif (function_exists('gmp_init')) {
            $mask = gmp_mul(gmp_init(1), gmp_pow(gmp_init(2), 128 - min((int) $bits[1], 128)));
        } elseif (function_exists('bcadd')) {
            $mask = bcmul('1', bcpow('2', (string) (128 - min((int) $bits[1], 128))));
        } else {
            trigger_error('GMP or BCMATH extension not installed!', E_USER_WARNING);
        }
    }

    /**
     * Convert IP v6 to long integer.
     *
     * @param   string  $ip     The IP
     */
    private function ip2long_v6(string $ip): string
    {
        $ip_n = (string) inet_pton($ip);
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
                $dec = bcadd($dec, $bin[$i], 0);    // @phpstan-ignore-line
            }

            return $dec;
        }
        trigger_error('GMP or BCMATH extension not installed!', E_USER_WARNING);

        return '';
    }

    /**
     * Convert long integer to IP v6.
     *
     * @param   string  $dec    The value
     */
    private function long2ip_v6($dec): string
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
            trigger_error('GMP or BCMATH extension not installed!', E_USER_WARNING);
        }

        $bin = str_pad($bin, 128, '0', STR_PAD_LEFT);
        $ip  = [];
        for ($bit = 0; $bit <= 7; $bit++) {
            $bin_part = substr($bin, $bit * 16, 16);
            $ip[]     = dechex((int) bindec($bin_part));
        }
        $ip = implode(':', $ip);

        return (string) inet_ntop((string) inet_pton($ip));
    }
}
