<?php
/**
 * @note Dotclear\Plugin\Antispam\Common\Antispam
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

use ArrayObject;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Exception\ModuleException;
use Dotclear\Plugin\Antispam\Common\Filter\FilterIp;
use Dotclear\Plugin\Antispam\Common\Filter\FilterIpv6;
use Dotclear\Process\Admin\Action\Action;

class Antispam
{
    /** @var null|Spamfilters Spamfitlers instance */
    public $filters;

    public function __construct()
    {
        if (dotclear()->processed('Public')) {
            dotclear()->behavior()->add('publicBeforeCommentCreate', [$this, 'isSpam']);
            dotclear()->behavior()->add('publicBeforeTrackbackCreate', [$this, 'isSpam']);
            dotclear()->behavior()->add('publicBeforeDocument', [$this, 'purgeOldSpam']);
        } elseif (dotclear()->processed('Admin')) {
            dotclear()->behavior()->add('coreAfterCommentUpdate', [$this, 'trainFilters']);
            dotclear()->behavior()->add('adminAfterCommentDesc', [$this, 'statusMessage']);
            dotclear()->behavior()->add('adminDashboardHeaders', [$this, 'dashboardHeaders']);
            dotclear()->behavior()->add('adminCommentsActionsPage', [$this, 'commentsActionsPage']);
            dotclear()->behavior()->add('coreBlogGetComments', [$this, 'blogGetComments']);
            dotclear()->behavior()->add('adminCommentListHeader', [$this, 'commentListHeader']);
            dotclear()->behavior()->add('adminCommentListValue', [$this, 'commentListValue']);
        }
    }

    public function initFilters(): void
    {
        $spamfilters = new ArrayObject($this->defaultFilters());

        // --BEHAVIOR-- antispamInitFilters , ArrayObject
        dotclear()->behavior()->call('antispamInitFilters', $spamfilters);
        $spamfilters = $spamfilters->getArrayCopy();

        $this->filters = new Spamfilters();
        $this->filters->init($spamfilters);
    }

    public function defaultFilters(): array
    {
        $ns             = __NAMESPACE__ . '\\Filter\\';
        $defaultfilters = [
            $ns . 'FilterIp',
            $ns . 'FilterIplookup',
            $ns . 'FilterWords',
            $ns . 'FilterLinkslookup',
        ];

        if (function_exists('gmp_init') || function_exists('bcadd')) {
            $defaultfilters[] = $ns . 'FilterIpv6';
        }

        return $defaultfilters;
    }

    public function isSpam(Cursor $cur): void
    {
        $this->initFilters();
        $this->filters->isSpam($cur);
    }

    public function trainFilters(Cursor $cur, Record $rs): void
    {
        $status = null;
        // From ham to spam
        if (-2 != $rs->fInt('comment_status') && -2 == $cur->getField('comment_status')) {
            $status = 'spam';
        }

        // From spam to ham
        if (-2 == $rs->f('comment_status') && 1 == $cur->getField('comment_status')) {
            $status = 'ham';
        }

        // the status of this comment has changed
        if (null !== $status) {
            $filter_name = $rs->call('spamFilter') ?: null;

            $this->initFilters();
            $this->filters->trainFilters($rs, $status, $filter_name);
        }
    }

    public function statusMessage(Record $rs): string
    {
        if ($rs->exists('comment_status') && -2 == $rs->fInt('comment_status')) {
            $filter_name = $rs->call('spamFilter') ?: null;

            $this->initFilters();

            return
            '<p><strong>' . __('This comment is a spam:') . '</strong> ' .
            $this->filters->statusMessage($rs, $filter_name) . '</p>';
        }

        return '';
    }

    public function dashboardHeaders(): string
    {
        return dotclear()->resource()->load('dashboard.js', 'Plugin', 'Antispam');
    }

    public function countSpam(): int
    {
        return dotclear()->blog()->comments()->getComments(['comment_status' => -2], true)->fInt();
    }

    public function countPublishedComments(): int
    {
        return dotclear()->blog()->comments()->getComments(['comment_status' => 1], true)->fInt();
    }

    public function delAllSpam(?string $beforeDate = null): void
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->column('comment_id')
            ->from(dotclear()->prefix . 'comment C')
            ->join(
                JoinStatement::init(__METHOD__)
                    ->from(dotclear()->prefix . 'post P')
                    ->on('P.post_id = C.post_id')
                    ->statement()
            )
            ->where('blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->and('comment_status = -2')
        ;

        if ($beforeDate) {
            $sql->and('comment_dt < ' . $sql->quote($beforeDate));
        }

        $rs = $sql->select();
        $r  = [];
        while ($rs->fetch()) {
            $r[] = $rs->fInt('comment_id');
        }

        if (empty($r)) {
            return;
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->from(dotclear()->prefix . 'comment')
            ->where('comment_id' . $sql->in($r))
            ->delete()
        ;
    }

    public function getUserCode(): string
    {
        $code = pack('a32', dotclear()->user()->userID()) .
        hash(dotclear()->config()->get('crypt_algo'), dotclear()->user()->cryptLegacy(dotclear()->user()->getInfo('user_pwd')));

        return bin2hex($code);
    }

    public function checkUserCode(string $code): string|false
    {
        $code = pack('H*', $code);

        $user_id = trim(@pack('a32', substr($code, 0, 32)));
        $pwd     = substr($code, 32);

        if ('' === $user_id || '' === $pwd) {
            return false;
        }

        $sql = new SelectStatement(__METHOD__);
        $rs  = $sql
            ->columns([
                'user_id',
                'user_pwd',
            ])
            ->where('user_id = ' . $sql->quote($user_id))
            ->from(dotclear()->prefix . 'user')
            ->select()
        ;

        if ($rs->isEmpty()) {
            return false;
        }

        if (hash(dotclear()->config()->get('crypt_algo'), dotclear()->user()->cryptLegacy($rs->f('user_pwd'))) != $pwd) {
            return false;
        }

        $permissions = dotclear()->blogs()->getBlogPermissions(dotclear()->blog()->id);

        if (empty($permissions[$rs->f('user_id')])) {
            return false;
        }

        return $rs->f('user_id');
    }

    public function purgeOldSpam(): void
    {
        $defaultDateLastPurge = time();
        $defaultModerationTTL = '7';
        $init                 = false;

        $dateLastPurge = dotclear()->blog()->settings()->get('antispam')->get('antispam_date_last_purge');
        if (null === $dateLastPurge) {
            $init = true;
            dotclear()->blog()->settings()->get('antispam')->put('antispam_date_last_purge', $defaultDateLastPurge, 'integer', 'Antispam Date Last Purge (unix timestamp)', true, false);
            $dateLastPurge = $defaultDateLastPurge;
        }
        $moderationTTL = dotclear()->blog()->settings()->get('antispam')->get('antispam_moderation_ttl');
        if (null === $moderationTTL) {
            dotclear()->blog()->settings()->get('antispam')->put('antispam_moderation_ttl', $defaultModerationTTL, 'integer', 'Antispam Moderation TTL (days)', true, false);
            $moderationTTL = $defaultModerationTTL;
        }

        if (0 > $moderationTTL) {
            // disabled
            return;
        }

        // we call the purge every day
        if (86400 < (time() - $dateLastPurge)) {
            // update dateLastPurge
            if (!$init) {
                dotclear()->blog()->settings()->get('antispam')->put('antispam_date_last_purge', time(), null, null, true, false);
            }
            $date = date('Y-m-d H:i:s', time() - $moderationTTL * 86400);
            self::delAllSpam($date);
        }
    }

    public function blogGetComments(Record $rs): void
    {
        $rs->extend(new RsExtComment());
    }

    public function commentListHeader(Record $rs, ArrayObject $cols, bool $spam): void
    {
        if ($spam) {
            $cols['spam_filter'] = '<th scope="col">' . __('Spam filter') . '</th>';
        }
    }

    public function commentListValue(Record $rs, ArrayObject $cols, bool $spam): void
    {
        if ($spam) {
            $filter_name = '';
            if ($rs->call('spamFilter')) {
                if (!$this->filters) {
                    $this->initFilters();
                }
                $filter_name = (null !== ($f = $this->filters->getFilter($rs->call('spamFilter')))) ? $f->name : $rs->call('spamFilter');
            }
            $cols['spam_filter'] = '<td class="nowrap">' . $filter_name . '</td>';
        }
    }

    // ! todo: manage IPv6
    public function commentsActionsPage(Action $ap): void
    {
        $ip_filter_active = true;
        if (null !== dotclear()->blog()->settings()->get('antispam')->get('antispam_filters')) {
            $filters_opt = dotclear()->blog()->settings()->get('antispam')->get('antispam_filters');
            if (is_array($filters_opt)) {
                $ip_filter_active = isset($filters_opt['FilterIp']) && is_array($filters_opt['FilterIp']) && 1 == $filters_opt['FilterIp'][0];
            }
        }

        if ($ip_filter_active) {
            $blocklist_actions = [__('Blocklist IP') => 'blocklist'];
            if (dotclear()->user()->isSuperAdmin()) {
                $blocklist_actions[__('Blocklist IP (global)')] = 'blocklist_global';
            }

            $ap->addAction(
                [__('IP address') => $blocklist_actions],
                [$this, 'doBlocklistIP']
            );
        }
    }

    public function doBlocklistIP(Action $ap, $post)
    {
        $action = $ap->getAction();
        $co_ids = $ap->getIDs();
        if (empty($co_ids)) {
            throw new ModuleException(__('No comment selected'));
        }

        $global = !empty($action) && 'blocklist_global' == $action && dotclear()->user()->isSuperAdmin();

        $rs = $ap->getRS();

        $ip_filter_v4 = new FilterIp();
        $ip_filter_v6 = new FilterIpv6();

        while ($rs->fetch()) {
            if (false !== filter_var($rs->f('comment_ip'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // IP is an IPv6
                $ip_filter_v6->addIP('blackv6', $rs->f('comment_ip'), $global);
            } else {
                // Assume that IP is IPv4
                $ip_filter_v4->addIP('black', $rs->f('comment_ip'), $global);
            }
        }

        dotclear()->notice()->addSuccessNotice(__('IP addresses for selected comments have been blocklisted.'));
        $ap->redirect(true);
    }
}
