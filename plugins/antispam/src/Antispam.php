<?php

/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\antispam;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Span;
use Dotclear\Helper\Html\Form\Strong;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Interface\Core\BlogInterface;

/**
 * @brief   The module antispam handler.
 * @ingroup antispam
 *
 * @since   2.36, dcCore::app()->spamfilters is no longer taken into account, use AntispamInitFilters behavior instead
 */
class Antispam
{
    /**
     * Spam rules table name.
     *
     * @var     string  SPAMRULE_TABLE_NAME
     */
    public const SPAMRULE_TABLE_NAME = 'spamrule';

    /**
     * The spam filters stacks.
     *
     * @var     list<class-string<SpamFilter>|SpamFilter>  $spamfilters
     */
    private static array $spamfilters = [];

    /**
     * Antispam Filters.
     *
     * @var     SpamFilters     $filters
     */
    public static $filters;

    /**
     * Initializes the filters.
     */
    public static function initFilters(): void
    {
        if (self::$spamfilters !== []) {
            return;
        }

        $spamfilters = new ArrayObject();
        # --BEHAVIOR-- AntispamInitFilters -- ArrayObject
        App::behavior()->callBehavior('AntispamInitFilters', $spamfilters);

        foreach ($spamfilters as $spamfilter) {
            self::$spamfilters[] = $spamfilter;
        }

        self::$filters = new SpamFilters();
        self::$filters->init(self::$spamfilters);
    }

    /**
     * Determines whether the specified Cursor content is spam.
     *
     * The Cursor may be modified (or deleted) according to the result
     *
     * @param   Cursor  $cur    The current
     */
    public static function isSpam(Cursor $cur): void
    {
        self::initFilters();
        self::$filters->isSpam($cur);
    }

    /**
     * Train the filters with current record.
     *
     * @param   BlogInterface   $blog   The blog
     * @param   Cursor          $cur    The Cursor
     * @param   MetaRecord      $rs     The comment record
     */
    public static function trainFilters(BlogInterface $blog, Cursor $cur, MetaRecord $rs): void
    {
        $status    = null;
        $junk      = App::status()->comment()::JUNK;
        $published = App::status()->comment()::PUBLISHED;

        // From ham to spam
        if ($rs->comment_status != $junk && $cur->comment_status == $junk) {
            $status = 'spam';
        }

        // From spam to ham
        if ($rs->comment_status == $junk && $cur->comment_status == $published) {
            $status = 'ham';
        }

        // the status of this comment has changed
        if ($status) {
            $filter_name = $rs->exists('comment_spam_filter') ? $rs->comment_spam_filter : '';

            self::initFilters();
            self::$filters->trainFilters($rs, $status, $filter_name);
        }
    }

    /**
     * Get filter status message.
     *
     * @param   MetaRecord  $rs     The comment record
     */
    public static function statusMessage(MetaRecord $rs): string
    {
        if ($rs->exists('comment_status') && $rs->comment_status == App::status()->comment()::JUNK) {
            $filter_name = $rs->exists('comment_spam_filter') ? $rs->comment_spam_filter : '';

            self::initFilters();

            return
            (new Para())->items([
                (new Text())
                    ->separator(' ')
                    ->items([
                        (new Strong(__('This comment is a spam:'))),
                        (new Text(null, self::$filters->statusMessage($rs, $filter_name))),
                    ]),
            ])
            ->render();
        }

        return '';
    }

    /**
     * Return additional information about existing spams.
     */
    public static function dashboardIconTitle(): string
    {
        if (($count = self::countSpam()) > 0) {
            $str = ($count > 1) ? __('(including %d spam comments)') : __('(including %d spam comment)');

            return (new Link())
                ->href(App::backend()->url()->get('admin.comments', ['status' => (string) App::status()->comment()::JUNK]))
                ->items([
                    (new Span(sprintf($str, $count)))
                        ->class('db-icon-title-spam'),
                ])
            ->render();
        }

        return '';
    }

    /**
     * Load antispam dashboard script.
     */
    public static function dashboardHeaders(): string
    {
        return My::jsLoad('dashboard');
    }

    /**
     * Counts the number of spam.
     */
    public static function countSpam(): int
    {
        return (int) App::blog()->getComments(['comment_status' => App::status()->comment()::JUNK], true)->f(0);
    }

    /**
     * Counts the number of published comments.
     */
    public static function countPublishedComments(): int
    {
        return (int) App::blog()->getComments(['comment_status' => App::status()->comment()::PUBLISHED], true)->f(0);
    }

    /**
     * Delete all spam older than a given date, else every.
     *
     * @param   null|string     $beforeDate     The before date
     */
    public static function delAllSpam(?string $beforeDate = null): void
    {
        $sql = new SelectStatement();
        $sql
            ->column('comment_id')
            ->from($sql->as(App::db()->con()->prefix() . App::blog()::COMMENT_TABLE_NAME, 'C'))
            ->join(
                (new JoinStatement())
                    ->from($sql->as(App::db()->con()->prefix() . App::blog()::POST_TABLE_NAME, 'P'))
                    ->on('P.post_id = C.post_id')
                    ->statement()
            )
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('comment_status = ' . App::status()->comment()::JUNK);

        if ($beforeDate) {
            $sql->and('comment_dt < \'' . $beforeDate . '\' ');
        }

        $r  = [];
        $rs = $sql->select();
        if ($rs instanceof MetaRecord) {
            while ($rs->fetch()) {
                $r[] = (int) $rs->comment_id;
            }
        }

        if ($r === []) {
            return;
        }

        $sql = new DeleteStatement();
        $sql
            ->from(App::db()->con()->prefix() . App::blog()::COMMENT_TABLE_NAME)
            ->where('comment_id ' . $sql->in($r))
            ->delete();
    }

    /**
     * Gets the user code (used for antispam feeds URL).
     */
    public static function getUserCode(): string
    {
        $code = pack('a32', App::auth()->userID()) .
        hash(App::config()->cryptAlgo(), App::auth()->cryptLegacy(App::auth()->getInfo('user_pwd')));

        return bin2hex($code);
    }

    /**
     * Check if a user code is valid and if so return the user ID.
     *
     * @param   string  $code   The code
     *
     * @return  bool|string
     */
    public static function checkUserCode(string $code)
    {
        $code = pack('H*', $code);

        $user_id = trim(@pack('a32', substr($code, 0, 32)));
        $pwd     = substr($code, 32);

        if ($user_id === '' || $pwd === '') {
            return false;
        }

        $sql = new SelectStatement();
        $rs  = $sql
            ->columns([
                'user_id',
                'user_pwd',
            ])
            ->from(App::db()->con()->prefix() . App::auth()::USER_TABLE_NAME)
            ->where('user_id = ' . $sql->quote($user_id))
            ->select();

        if (!$rs instanceof MetaRecord || $rs->isEmpty()) {
            return false;
        }

        if (hash(App::config()->cryptAlgo(), App::auth()->cryptLegacy($rs->user_pwd)) !== $pwd) {
            return false;
        }

        $permissions = App::blogs()->getBlogPermissions(App::blog()->id());

        if (empty($permissions[$rs->user_id])) {
            return false;
        }

        return $rs->user_id;
    }

    /**
     * Purge old spam.
     */
    public static function purgeOldSpam(): void
    {
        $defaultDateLastPurge = time();
        $defaultModerationTTL = '7';
        $init                 = false;

        // settings
        $dateLastPurge = My::settings()->antispam_date_last_purge;
        if ($dateLastPurge === null) {
            $init = true;
            My::settings()->put('antispam_date_last_purge', $defaultDateLastPurge, 'integer', 'Antispam Date Last Purge (unix timestamp)', true, false);
            $dateLastPurge = $defaultDateLastPurge;
        }
        $moderationTTL = My::settings()->antispam_moderation_ttl;
        if ($moderationTTL === null) {
            My::settings()->put('antispam_moderation_ttl', $defaultModerationTTL, 'integer', 'Antispam Moderation TTL (days)', true, false);
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
                My::settings()->put('antispam_date_last_purge', time(), null, null, true, false);
            }
            $date = date('Y-m-d H:i:s', (int) (time() - $moderationTTL * 86400));
            Antispam::delAllSpam($date);
        }
    }
}
