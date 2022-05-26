<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

// Dotclear\Plugin\Antispam\Common\Antispam
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Clock;
use Dotclear\Plugin\Antispam\Common\Filter\FilterIp;
use Dotclear\Plugin\Antispam\Common\Filter\FilterIpv6;
use Dotclear\Process\Admin\Action\Action;

/**
 * Antispam main class.
 *
 * @ingroup  Plugin Antispam
 */
class Antispam
{
    /**
     * @var null|Spamfilters $filters
     *                       Spamfitlers instance
     */
    public $filters;

    public function __construct()
    {
        if (App::core()->processed('Public')) {
            App::core()->behavior()->add('publicBeforeCommentCreate', [$this, 'isSpam']);
            App::core()->behavior()->add('publicBeforeTrackbackCreate', [$this, 'isSpam']);
            App::core()->behavior()->add('publicBeforeDocument', [$this, 'purgeOldSpam']);
        } elseif (App::core()->processed('Admin')) {
            App::core()->behavior()->add('coreAfterCommentUpdate', [$this, 'trainFilters']);
            App::core()->behavior()->add('adminAfterCommentDesc', [$this, 'statusMessage']);
            App::core()->behavior()->add('adminDashboardHeaders', [$this, 'dashboardHeaders']);
            App::core()->behavior()->add('adminCommentsActionsPage', [$this, 'commentsActionsPage']);
            App::core()->behavior()->add('coreBlogAfterGetComments', [$this, 'blogGetComments']);
            App::core()->behavior()->add('adminCommentListHeader', [$this, 'commentListHeader']);
            App::core()->behavior()->add('adminCommentListValue', [$this, 'commentListValue']);
        }
    }

    public function initFilters(): void
    {
        $spamfilters = new ArrayObject($this->defaultFilters());

        // --BEHAVIOR-- antispamInitFilters , ArrayObject
        App::core()->behavior()->call('antispamInitFilters', $spamfilters);
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
        return App::core()->resource()->load('dashboard.js', 'Plugin', 'Antispam');
    }

    public function countSpam(): int
    {
        $param = new Param();
        $param->set('comment_status', -2);

        return App::core()->blog()->comments()->countComments(param: $param);
    }

    public function countPublishedComments(): int
    {
        $param = new Param();
        $param->set('comment_status', 1);

        return App::core()->blog()->comments()->countComments(param: $param);
    }

    public function delAllSpam(?string $beforeDate = null): void
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->column('comment_id')
            ->from(App::core()->prefix() . 'comment C')
            ->join(
                JoinStatement::init(__METHOD__)
                    ->from(App::core()->prefix() . 'post P')
                    ->on('P.post_id = C.post_id')
                    ->statement()
            )
            ->where('blog_id = ' . $sql->quote(App::core()->blog()->id))
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
            ->from(App::core()->prefix() . 'comment')
            ->where('comment_id' . $sql->in($r))
            ->delete()
        ;
    }

    public function getUserCode(): string
    {
        $code = pack('a32', App::core()->user()->userID()) .
        hash(App::core()->config()->get('crypt_algo'), App::core()->user()->cryptLegacy(App::core()->user()->getInfo('user_pwd')));

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
            ->from(App::core()->prefix() . 'user')
            ->select()
        ;

        if ($rs->isEmpty()) {
            return false;
        }

        if (hash(App::core()->config()->get('crypt_algo'), App::core()->user()->cryptLegacy($rs->f('user_pwd'))) != $pwd) {
            return false;
        }

        $permissions = App::core()->blogs()->getBlogPermissions(id: App::core()->blog()->id);

        if (empty($permissions[$rs->f('user_id')])) {
            return false;
        }

        return $rs->f('user_id');
    }

    public function purgeOldSpam(): void
    {
        $defaultDateLastPurge = Clock::ts();
        $defaultModerationTTL = '7';
        $init                 = false;

        $dateLastPurge = App::core()->blog()->settings()->get('antispam')->get('antispam_date_last_purge');
        if (null === $dateLastPurge) {
            $init = true;
            App::core()->blog()->settings()->get('antispam')->put('antispam_date_last_purge', $defaultDateLastPurge, 'integer', 'Antispam Date Last Purge (unix timestamp)', true, false);
            $dateLastPurge = $defaultDateLastPurge;
        }
        $moderationTTL = App::core()->blog()->settings()->get('antispam')->get('antispam_moderation_ttl');
        if (null === $moderationTTL) {
            App::core()->blog()->settings()->get('antispam')->put('antispam_moderation_ttl', $defaultModerationTTL, 'integer', 'Antispam Moderation TTL (days)', true, false);
            $moderationTTL = $defaultModerationTTL;
        }

        if (0 > $moderationTTL) {
            // disabled
            return;
        }

        // we call the purge every day
        if (86400 < ($defaultDateLastPurge - $dateLastPurge)) {
            // update dateLastPurge
            if (!$init) {
                App::core()->blog()->settings()->get('antispam')->put('antispam_date_last_purge', $defaultDateLastPurge, null, null, true, false);
            }
            $date = Clock::database(date: ($defaultDateLastPurge - $moderationTTL * 86400));
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
        if (null !== App::core()->blog()->settings()->get('antispam')->get('antispam_filters')) {
            $filters_opt = App::core()->blog()->settings()->get('antispam')->get('antispam_filters');
            if (is_array($filters_opt)) {
                $ip_filter_active = isset($filters_opt['FilterIp']) && is_array($filters_opt['FilterIp']) && 1 == $filters_opt['FilterIp'][0];
            }
        }

        if ($ip_filter_active) {
            $blocklist_actions = [__('Blocklist IP') => 'blocklist'];
            if (App::core()->user()->isSuperAdmin()) {
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

        $global = !empty($action) && 'blocklist_global' == $action && App::core()->user()->isSuperAdmin();

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

        App::core()->notice()->addSuccessNotice(__('IP addresses for selected comments have been blocklisted.'));
        $ap->redirect(true);
    }
}
