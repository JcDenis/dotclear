<?php
/**
 * @class Dotclear\Plugin\Antispam\Common\Antispam
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

use ArrayObject;

use Dotclear\Admin\Page\Action\Action;
use Dotclear\Core\Blog;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Plugin\Antispam\Common\Spamfilters;
use Dotclear\Plugin\Antispam\Common\Filter\FilterIp;
use Dotclear\Plugin\Antispam\Common\Filter\FilterIpv6;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Antispam
{
    /** @var Spamfilters Spamfitlers instance */
    public static $filters;

    public static function initAntispam()
    {
        dotclear()->blog()->settings()->addNamespace('antispam');

        if ('Public' == DOTCLEAR_PROCESS) {
            dotclear()->behavior()->add('publicBeforeCommentCreate', [__CLASS__, 'isSpam']);
            dotclear()->behavior()->add('publicBeforeTrackbackCreate', [__CLASS__, 'isSpam']);
            dotclear()->behavior()->add('publicBeforeDocument', [__CLASS__, 'purgeOldSpam']);
        } elseif ('Admin' == DOTCLEAR_PROCESS) {
            dotclear()->behavior()->add('coreAfterCommentUpdate', [__CLASS__, 'trainFilters']);
            dotclear()->behavior()->add('adminAfterCommentDesc', [__CLASS__, 'statusMessage']);
            dotclear()->behavior()->add('adminDashboardHeaders', [__CLASS__, 'dashboardHeaders']);
            dotclear()->behavior()->add('adminCommentsActionsPage', [__CLASS__, 'commentsActionsPage']);
            dotclear()->behavior()->add('coreBlogGetComments', [__CLASS__, 'blogGetComments']);
            dotclear()->behavior()->add('adminCommentListHeader', [__CLASS__, 'commentListHeader']);
            dotclear()->behavior()->add('adminCommentListValue', [__CLASS__, 'commentListValue']);
        }
    }

    public static function initFilters(): void
    {
        $spamfilters = new ArrayObject(self::defaultFilters());

        # --BEHAVIOR-- antispamInitFilters , ArrayObject
        dotclear()->behavior()->call('antispamInitFilters', $spamfilters);
        $spamfilters = $spamfilters->getArrayCopy();

        self::$filters = new Spamfilters();
        self::$filters->init($spamfilters);
    }

    public static function defaultFilters()
    {
        $ns = __NAMESPACE__ . '\\Filter\\';
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

    public static function isSpam(Cursor $cur): void
    {
        self::initFilters();
        self::$filters->isSpam($cur);
    }

    public static function trainFilters(Blog $blog, Cursor $cur, Record $rs): void
    {
        $status = null;
        # From ham to spam
        if ($rs->comment_status != -2 && $cur->comment_status == -2) {
            $status = 'spam';
        }

        # From spam to ham
        if ($rs->comment_status == -2 && $cur->comment_status == 1) {
            $status = 'ham';
        }

        # the status of this comment has changed
        if ($status) {
            $filter_name = $rs->spamFilter() ?: null;

            self::initFilters();
            self::$filters->trainFilters($rs, $status, $filter_name);
        }
    }

    public static function statusMessage(Record $rs): string
    {
        if ($rs->exists('comment_status') && $rs->comment_status == -2) {
            $filter_name = $rs->spamFilter() ?: null;

            self::initFilters();

            return
            '<p><strong>' . __('This comment is a spam:') . '</strong> ' .
            self::$filters->statusMessage($rs, $filter_name) . '</p>';
        }
    }

    public static function dashboardIconTitle(): string
    {
        if (($count = self::countSpam()) > 0) {
            $str = ($count > 1) ? __('(including %d spam comments)') : __('(including %d spam comment)');

            return '</span></a> <a href="' . dotclear()->adminurl()->get('admin.comments', ['status' => '-2']) . '"><span class="db-icon-title-spam">' .
            sprintf($str, $count);
        }

        return '';
    }

    public static function dashboardHeaders(): string
    {
        return Utils::jsLoad('?mf=Plugin/Antispam/files/js/dashboard.js');
    }

    public static function countSpam(): int
    {
        return (int) dotclear()->blog()->comments()->getComments(['comment_status' => -2], true)->f(0);
    }

    public static function countPublishedComments(): int
    {
        return (int) dotclear()->blog()->comments()->getComments(['comment_status' => 1], true)->f(0);
    }

    public static function delAllSpam(?string $beforeDate = null): void
    {
        $strReq = 'SELECT comment_id ' .
        'FROM ' . dotclear()->prefix . 'comment C ' .
        'JOIN ' . dotclear()->prefix . 'post P ON P.post_id = C.post_id ' .
        "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            'AND comment_status = -2 ';
        if ($beforeDate) {
            $strReq .= 'AND comment_dt < \'' . $beforeDate . '\' ';
        }

        $rs = dotclear()->con()->select($strReq);
        $r  = [];
        while ($rs->fetch()) {
            $r[] = (int) $rs->comment_id;
        }

        if (empty($r)) {
            return;
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'comment ' .
        'WHERE comment_id ' . dotclear()->con()->in($r) . ' ';

        dotclear()->con()->execute($strReq);
    }

    public static function getUserCode(): string
    {
        $code = pack('a32', dotclear()->user()->userID()) .
        hash(dotclear()->config()->crypt_algo, dotclear()->user()->cryptLegacy(dotclear()->user()->getInfo('user_pwd')));

        return bin2hex($code);
    }

    public static function checkUserCode(string $code): string|false
    {
        $code = pack('H*', $code);

        $user_id = trim(@pack('a32', substr($code, 0, 32)));
        $pwd     = substr($code, 32);

        if ($user_id === '' || $pwd === '') {
            return false;
        }

        $strReq = 'SELECT user_id, user_pwd ' .
        'FROM ' . dotclear()->prefix . 'user ' .
        "WHERE user_id = '" . dotclear()->con()->escape($user_id) . "' ";

        $rs = dotclear()->con()->select($strReq);

        if ($rs->isEmpty()) {
            return false;
        }

        if (hash(dotclear()->config()->crypt_algo, dotclear()->user()->cryptLegacy($rs->user_pwd)) != $pwd) {
            return false;
        }

        $permissions = dotclear()->blogs()->getBlogPermissions(dotclear()->blog()->id);

        if (empty($permissions[$rs->user_id])) {
            return false;
        }

        return $rs->user_id;
    }

    public static function purgeOldSpam(): void
    {
        $defaultDateLastPurge = time();
        $defaultModerationTTL = '7';
        $init                 = false;

        $dateLastPurge = dotclear()->blog()->settings()->antispam->antispam_date_last_purge;
        if ($dateLastPurge === null) {
            $init = true;
            dotclear()->blog()->settings()->antispam->put('antispam_date_last_purge', $defaultDateLastPurge, 'integer', 'Antispam Date Last Purge (unix timestamp)', true, false);
            $dateLastPurge = $defaultDateLastPurge;
        }
        $moderationTTL = dotclear()->blog()->settings()->antispam->antispam_moderation_ttl;
        if ($moderationTTL === null) {
            dotclear()->blog()->settings()->antispam->put('antispam_moderation_ttl', $defaultModerationTTL, 'integer', 'Antispam Moderation TTL (days)', true, false);
            $moderationTTL = $defaultModerationTTL;
        }

        if ($moderationTTL < 0) {
            // disabled
            return;
        }

        // we call the purge every day
        if ((time() - $dateLastPurge) > (86400)) {
            // update dateLastPurge
            if (!$init) {
                dotclear()->blog()->settings()->antispam->put('antispam_date_last_purge', time(), null, null, true, false);
            }
            $date = date('Y-m-d H:i:s', time() - $moderationTTL * 86400);
            self::delAllSpam($date);
        }
    }

    public static function blogGetComments(Record $rs): void
    {
        $rs->extend(__NAMESPACE__ . '\\RsExtComment');
    }

    public static function commentListHeader(Record $rs, ArrayObject $cols, bool $spam): void
    {
        if ($spam) {
            $cols['spam_filter'] = '<th scope="col">' . __('Spam filter') . '</th>';
        }
    }

    public static function commentListValue(Record $rs, ArrayObject $cols, bool $spam): void
    {
        if ($spam) {
            $filter_name = '';
            if ($rs->spamFilter()) {
                if (!self::$filters) {
                    self::initFilters();
                }
                $filter_name = (null !== ($f = self::$filters->getFilter($rs->spamFilter()))) ? $f->name : $rs->spamFilter();
            }
            $cols['spam_filter'] = '<td class="nowrap">' . $filter_name . '</td>';
        }
    }

    //! todo: manage IPv6
    public static function commentsActionsPage(Action $ap): void
    {
        $ip_filter_active = true;
        if (dotclear()->blog()->settings()->antispam->antispam_filters !== null) {
            $filters_opt = dotclear()->blog()->settings()->antispam->antispam_filters;
            if (is_array($filters_opt)) {
                $ip_filter_active = isset($filters_opt['FilterIp']) && is_array($filters_opt['FilterIp']) && $filters_opt['FilterIp'][0] == 1;
            }
        }

        if ($ip_filter_active) {
            $blocklist_actions = [__('Blocklist IP') => 'blocklist'];
            if (dotclear()->user()->isSuperAdmin()) {
                $blocklist_actions[__('Blocklist IP (global)')] = 'blocklist_global';
            }

            $ap->addAction(
                [__('IP address') => $blocklist_actions],
                [__CLASS__, 'doBlocklistIP']
            );
        }
    }

    public static function doBlocklistIP(Action $ap, $post)
    {
        $action = $ap->getAction();
        $co_ids = $ap->getIDs();
        if (empty($co_ids)) {
            throw new AdminException(__('No comment selected'));
        }

        $global = !empty($action) && $action == 'blocklist_global' && dotclear()->user()->isSuperAdmin();

        $rs = $ap->getRS();

        $ip_filter_v4 = new FilterIp();
        $ip_filter_v6 = new FilterIpv6();

        while ($rs->fetch()) {
            if (filter_var($rs->comment_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                // IP is an IPv6
                $ip_filter_v6->addIP('blackv6', $rs->comment_ip, $global);
            } else {
                // Assume that IP is IPv4
                $ip_filter_v4->addIP('black', $rs->comment_ip, $global);
            }
        }

        dotclear()->notice()->addSuccessNotice(__('IP addresses for selected comments have been blocklisted.'));
        $ap->redirect(true);
    }
}
