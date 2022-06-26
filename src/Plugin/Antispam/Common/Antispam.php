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
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Helper\Mapper\NamedStrings;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Plugin\Antispam\Common\Filter\FilterIp;
use Dotclear\Plugin\Antispam\Common\Filter\FilterIpv6;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\ActionItem;

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
        if (App::core()->isProcess('Public')) {
            App::core()->behavior('coreBeforeCreateComment')->add([$this, 'isSpam']);
            App::core()->behavior('publicBeforeGetDocument')->add([$this, 'purgeOldSpam']);
        } elseif (App::core()->isProcess('Admin')) {
            App::core()->behavior('coreAfterUpdateComment')->add([$this, 'trainFilters']);
            App::core()->behavior('adminAfterGetComment')->add([$this, 'statusMessage']);
            App::core()->behavior('adminDashboardHeaders')->add([$this, 'dashboardHeaders']);
            App::core()->behavior('adminCommentsActionsPage')->add([$this, 'commentsActionsPage']);
            App::core()->behavior('coreAfterGetComments')->add([$this, 'blogGetComments']);
            App::core()->behavior('adminBeforeGetCommentListHeader')->add([$this, 'commentListHeader']);
            App::core()->behavior('adminBeforeGetCommentListValue')->add([$this, 'commentListValue']);
        }
    }

    public function initFilters(): void
    {
        $ns          = __NAMESPACE__ . '\\Filter\\Filter';
        $spamfilters = new Strings();
        $spamfilters->add($ns . 'Ip');
        $spamfilters->add($ns . 'Iplookup');
        $spamfilters->add($ns . 'Words');
        $spamfilters->add($ns . 'Linkslookup');

        if (function_exists('gmp_init') || function_exists('bcadd')) {
            $spamfilters->add($ns . 'Ipv6');
        }

        // --BEHAVIOR-- antispamInitFilters , Strings
        App::core()->behavior('antispamInitFilters')->call(spamfilters: $spamfilters);

        $this->filters = new Spamfilters();
        $this->filters->init($spamfilters->dump());
    }

    public function isSpam(Cursor $cursor): void
    {
        $this->initFilters();
        $this->filters->isSpam($cursor);
    }

    public function trainFilters(Cursor $cursor, Record $record): void
    {
        $status = null;
        // From ham to spam
        if (-2 != $record->integer('comment_status') && -2 == $cursor->getField('comment_status')) {
            $status = 'spam';
        }

        // From spam to ham
        if (-2 == $record->field('comment_status') && 1 == $cursor->getField('comment_status')) {
            $status = 'ham';
        }

        // the status of this comment has changed
        if (null !== $status) {
            $filter_name = $record->call('spamFilter') ?: null;

            $this->initFilters();
            $this->filters->trainFilters($record, $status, $filter_name);
        }
    }

    public function statusMessage(Record $record): string
    {
        if ($record->exists('comment_status') && -2 == $record->integer('comment_status')) {
            $filter_name = $record->call('spamFilter') ?: null;

            $this->initFilters();

            return
            '<p><strong>' . __('This comment is a spam:') . '</strong> ' .
            $this->filters->statusMessage($record, $filter_name) . '</p>';
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
        $join = new JoinStatement();
        $join->from(App::core()->getPrefix() . 'post P');
        $join->on('P.post_id = C.post_id');

        $sql = new SelectStatement();
        $sql->column('comment_id');
        $sql->from(App::core()->getPrefix() . 'comment C');
        $sql->join($join->statement());
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('comment_status = -2');

        if ($beforeDate) {
            $sql->and('comment_dt < ' . $sql->quote($beforeDate));
        }

        $record = $sql->select();
        $ids    = new Integers();
        while ($record->fetch()) {
            $ids->add($record->integer('comment_id'));
        }

        if (!$ids->count()) {
            return;
        }

        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'comment');
        $sql->where('comment_id' . $sql->in($ids->dump()));
        $sql->delete();
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

        $sql = new SelectStatement();
        $sql->columns([
            'user_id',
            'user_pwd',
        ]);
        $sql->where('user_id = ' . $sql->quote($user_id));
        $sql->from(App::core()->getPrefix() . 'user');
        $record = $sql->select();

        if ($record->isEmpty()) {
            return false;
        }

        if (hash(App::core()->config()->get('crypt_algo'), App::core()->user()->cryptLegacy($record->field('user_pwd'))) != $pwd) {
            return false;
        }

        $permissions = App::core()->permission()->getBlogPermissions(id: App::core()->blog()->id);

        if (empty($permissions[$record->field('user_id')])) {
            return false;
        }

        return $record->field('user_id');
    }

    public function purgeOldSpam(): void
    {
        $defaultDateLastPurge = Clock::ts();
        $defaultModerationTTL = '7';
        $init                 = false;

        $dateLastPurge = App::core()->blog()->settings()->getGroup('antispam')->getSetting('antispam_date_last_purge');
        if (null === $dateLastPurge) {
            $init = true;
            App::core()->blog()->settings()->getGroup('antispam')->putSetting('antispam_date_last_purge', $defaultDateLastPurge, 'integer', 'Antispam Date Last Purge (unix timestamp)', true, false);
            $dateLastPurge = $defaultDateLastPurge;
        }
        $moderationTTL = App::core()->blog()->settings()->getGroup('antispam')->getSetting('antispam_moderation_ttl');
        if (null === $moderationTTL) {
            App::core()->blog()->settings()->getGroup('antispam')->putSetting('antispam_moderation_ttl', $defaultModerationTTL, 'integer', 'Antispam Moderation TTL (days)', true, false);
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
                App::core()->blog()->settings()->getGroup('antispam')->putSetting('antispam_date_last_purge', $defaultDateLastPurge, null, null, true, false);
            }
            $date = Clock::database(date: ($defaultDateLastPurge - $moderationTTL * 86400));
            self::delAllSpam($date);
        }
    }

    public function blogGetComments(Record $record): void
    {
        $record->extend(new RsExtComment());
    }

    public function commentListHeader(Record $record, NamedStrings $cols, bool $spam): void
    {
        if ($spam) {
            $cols->set('spam_filter', '<th scope="col">' . __('Spam filter') . '</th>');
        }
    }

    public function commentListValue(Record $record, NamedStrings $cols, bool $spam): void
    {
        if ($spam) {
            $filter_name = '';
            if ($record->call('spamFilter')) {
                if (!$this->filters) {
                    $this->initFilters();
                }
                $filter_name = (null !== ($f = $this->filters->getFilter($record->call('spamFilter')))) ? $f->name : $record->call('spamFilter');
            }
            $cols->set('spam_filter', '<td class="nowrap">' . $filter_name . '</td>');
        }
    }

    // ! todo: manage IPv6
    public function commentsActionsPage(Action $ap): void
    {
        $ip_filter_active = true;
        if (null !== App::core()->blog()->settings()->getGroup('antispam')->getSetting('antispam_filters')) {
            $filters_opt = App::core()->blog()->settings()->getGroup('antispam')->getSetting('antispam_filters');
            if (is_array($filters_opt)) {
                $ip_filter_active = isset($filters_opt['FilterIp']) && is_array($filters_opt['FilterIp']) && 1 == $filters_opt['FilterIp'][0];
            }
        }

        if ($ip_filter_active) {
            $blocklist_actions = [__('Blocklist IP') => 'blocklist'];
            if (App::core()->user()->isSuperAdmin()) {
                $blocklist_actions[__('Blocklist IP (global)')] = 'blocklist_global';
            }

            $ap->addAction(new ActionItem(
                group: __('IP address'),
                actions: $blocklist_actions,
                callback: [$this, 'doBlocklistIP'],
            ));
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
            if (false !== filter_var($rs->field('comment_ip'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // IP is an IPv6
                $ip_filter_v6->addIP('blackv6', $rs->field('comment_ip'), $global);
            } else {
                // Assume that IP is IPv4
                $ip_filter_v4->addIP('black', $rs->field('comment_ip'), $global);
            }
        }

        App::core()->notice()->addSuccessNotice(__('IP addresses for selected comments have been blocklisted.'));
        $ap->redirect(true);
    }
}
