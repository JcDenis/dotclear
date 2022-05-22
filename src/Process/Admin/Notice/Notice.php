<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Notice;

// Dotclear\Process\Admin\Notice\Notice
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Exception;

/**
 * Backend notices handling facilities.
 *
 * Accessible from App::core()->notice()->
 *
 * @ingroup  Admin
 */
final class Notice
{
    /**
     * @var array<string,string> $N_TYPES
     *                           notices types
     */
    private $N_TYPES = [
        // id → CSS class
        'success' => 'success',
        'warning' => 'warning-msg',
        'error'   => 'error',
        'message' => 'message',
        'static'  => 'static-msg', ];

    /**
     * @var bool $error_displayed
     *           is displayed error
     */
    private $error_displayed = false;

    /**
     * Retrieve notices count.
     *
     * @see NoticeParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The notices count
     */
    public function countNotices(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $param = new NoticeParam($param);
        $param->unset('order');
        $param->unset('limit');

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->column($query->count('notice_id'));

        return $this->queryNoticeTable(param: $param, sql: $query)->fInt(0);
    }

    /**
     * Get notices.
     *
     * @see NoticeParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return Record Notices record
     */
    public function getNotices(?Param $param = null, ?SelectStatement $sql = null): Record
    {
        $param = new NoticeParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->columns([
            'notice_id',
            'ses_id',
            'notice_type',
            'notice_ts',
            'notice_msg',
            'notice_format',
            'notice_options',
        ]);
        $query->order($query->escape($param->order('notice_ts DESC')));

        if (!empty($param->limit())) {
            $query->limit($param->limit());
        }

        return $this->queryNoticeTable(param: $param, sql: $query);
    }

    /**
     * Query notice table.
     *
     * @param NoticeParam     $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return Record The result
     */
    private function queryNoticeTable(NoticeParam $param, SelectStatement $sql): Record
    {
        $sql->from(App::core()->prefix() . 'notice', false, true);
        $sql->where('ses_id = ' . $sql->quote($param->ses_id((string) session_id())));

        if (!empty($param->notice_id())) {
            $sql->and('notice_id' . $sql->in($param->notice_id()));
        }

        if (!empty($param->notice_type())) {
            $sql->and('notice_type' . $sql->in($param->notice_type()));
        }

        if (!empty($param->notice_format())) {
            $sql->and('notice_format' . $sql->in($param->notice_format()));
        }

        if (null !== $param->sql()) {
            $sql->sql($param->sql());
        }

        return $sql->select();
    }

    /**
     * Add a notice.
     *
     * @param Cursor $cur The cursor
     */
    public function addNotice(Cursor $cur): int
    {
        App::core()->con()->writeLock(App::core()->prefix() . 'notice');

        try {
            // Get ID
            $sql = new SelectStatement(__METHOD__);
            $sql->column($sql->max('notice_id'));
            $sql->from(App::core()->prefix() . 'notice');
            $rs = $sql->select();

            $cur->setField('notice_id', $rs->fInt() + 1);
            $cur->setField('ses_id', (string) session_id());

            $this->cleanNoticeCursor($cur, $cur->getField('notice_id'));

            // --BEHAVIOR-- coreBeforeNoticeCreate
            App::core()->behavior()->call('adminBeforeNoticeCreate', $this, $cur);

            $cur->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterNoticeCreate
        App::core()->behavior()->call('adminAfterNoticeCreate', $this, $cur);

        return $cur->getField('notice_id');
    }

    /**
     * Delete a notice.
     *
     * @param int $id The notice id
     */
    public function deleteNotice(int $id): void
    {
        $this->deleteNotices([$id]);
    }

    /**
     * Delete given notices.
     *
     * @param array<int,int> $id The notices ids
     */
    public function deleteNotices(array $id): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'notice');
        $sql->where('notice_id' . $sql->in($id));
        $sql->delete();
    }

    /**
     * Delete all session notices.
     */
    public function deleteSessionNotice(): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'notice');
        $sql->where('ses_id = ' . $sql->quote((string) session_id()));
        $sql->delete();
    }

    /**
     * Get notices cursor.
     *
     * @param Cursor   $cur       The cursor
     * @param null|int $notice_id The notice id
     */
    private function cleanNoticeCursor(Cursor $cur, int $notice_id = null): void
    {
        if ('' === $cur->getField('notice_msg')) {
            throw new AdminException(__('No notice message'));
        }

        if ('' === $cur->getField('notice_ts') || null === $cur->getField('notice_ts')) {
            $cur->setField('notice_ts', Clock::database());
        }

        if ('' === $cur->getField('notice_format') || null === $cur->getField('notice_format')) {
            $cur->setField('notice_format', 'text');
        }

        $notice_id = is_int($notice_id) ? $notice_id : $cur->getField('notice_id');
    }

    /**
     * Gets the HTML code of notices.
     *
     * @return string the notices
     */
    public function getHtmlNotices(): string
    {
        $res = '';

        // Return error messages if any
        if (App::core()->error()->flag() && !$this->error_displayed) {
            // --BEHAVIOR-- adminPageNotificationError
            $notice_error = App::core()->behavior()->call('adminPageNotificationError');

            if (isset($notice_error) && !empty($notice_error)) {
                $res .= $notice_error;
            } else {
                $res .= sprintf(
                    '<div class="error" role="alert"><p><strong>%s</strong></p>%s</div>',
                    count(App::core()->error()->dump()) > 1 ? __('Errors:') : __('Error:'),
                    App::core()->error()->toHTML()
                );
            }
            $this->error_displayed = true;
        } else {
            $this->error_displayed = false;
        }

        // Return notices if any
        // Should retrieve static notices first, then others
        $step = 2;
        do {
            $param = new Param();
            if (2 == $step) {
                // Static notifications
                $param->set('notice_type', 'static');
            } else {
                // Normal notifications
                $param->push('sql', "AND notice_type != 'static'");
            }
            $counter = $this->countNotices(param: $param);
            if (0 < $counter) {
                $lines = $this->getNotices(param: $param);
                while ($lines->fetch()) {
                    if (isset($this->N_TYPES[$lines->f('notice_type')])) {
                        $class = $this->N_TYPES[$lines->f('notice_type')];
                    } else {
                        $class = $lines->f('notice_type');
                    }
                    $notification = [
                        'type'   => $lines->f('notice_type'),
                        'class'  => $class,
                        'ts'     => $lines->f('notice_ts'),
                        'text'   => $lines->f('notice_msg'),
                        'format' => $lines->f('notice_format'),
                    ];
                    if (null !== $lines->f('notice_options')) {
                        $notifications = array_merge($notification, @json_decode($lines->f('notice_options'), true));
                    }
                    // --BEHAVIOR-- adminPageNotification, array
                    $notice = App::core()->behavior()->call('adminPageNotification', $notification);

                    $res .= !empty($notice) ? $notice : $this->getNotification($notification);
                }
            }
        } while (--$step);

        // Delete returned notices
        $this->deleteSessionNotice();

        return $res;
    }

    /**
     * Add a success notice.
     *
     * @param string $message The message
     * @param array  $options The options
     */
    public function addSuccessNotice(string $message, array $options = []): void
    {
        $this->addTypedNotice('success', $message, $options);
    }

    /**
     * Add a warning notice.
     *
     * @param string $message The message
     * @param array  $options The options
     */
    public function addWarningNotice(string $message, array $options = []): void
    {
        $this->addTypedNotice('warning', $message, $options);
    }

    /**
     * Add an error notice.
     *
     * @param string $message The message
     * @param array  $options The options
     */
    public function addErrorNotice(string $message, array $options = []): void
    {
        $this->addTypedNotice('error', $message, $options);
    }

    /**
     * Add a notice.
     *
     * @param string $type    The type
     * @param string $message The message
     * @param array  $options The options
     */
    private function addTypedNotice(string $type, string $message, array $options = []): void
    {
        $cur = App::core()->con()->openCursor(App::core()->prefix() . 'notice');

        $cur->setField('notice_type', $type);
        $cur->setField('notice_ts', isset($options['ts']) && $options['ts'] ? $options['ts'] : Clock::database());
        $cur->setField('notice_msg', $message);
        $cur->setField('notice_options', json_encode($options));

        if (isset($options['divtag']) && $options['divtag']) {
            $cur->setField('notice_format', 'html');
        }
        if (isset($options['format']) && $options['format']) {
            $cur->setField('notice_format', $options['format']);
        }

        $this->addNotice($cur);
    }

    /**
     * Get the notification.
     *
     * @param array $notification The notification
     *
     * @return string the notification
     */
    private function getNotification(array $notification): string
    {
        $tag = isset($notification['format']) && 'html' === $notification['format'] ? 'div' : 'p';
        $ts  = '';
        if (!isset($notification['with_ts']) || (true == $notification['with_ts'])) {
            $ts = sprintf(
                '<span class="notice-ts"><time datetime="%s">%s</time></span>',
                Clock::iso8601(date: $notification['ts'], to: App::core()->timezone()),
                Clock::str(format: __('%H:%M:%S'), date: $notification['ts'], to: App::core()->timezone()),
            );
        }

        return '<' . $tag . ' class="' . $notification['class'] . '" role="alert">' . $ts . $notification['text'] . '</' . $tag . '>';
    }

    /**
     * Direct messages, usually immediately displayed.
     *
     * @param string $msg       The message
     * @param bool   $timestamp With the timestamp
     * @param bool   $div       Inside a div (else in a p)
     * @param bool   $echo      Display the message?
     * @param string $class     The class of block (div/p)
     */
    public function message(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true, string $class = 'message'): string
    {
        $res = '';
        if ('' != $msg) {
            $ts = '';
            if ($timestamp) {
                $ts = sprintf(
                    '<span class="notice-ts"><time datetime="%s">%s</time></span>',
                    Clock::iso8601(to: App::core()->timezone()),
                    Clock::str(format: __('%H:%M:%S'), to: App::core()->timezone()),
                );
            }
            $res = ($div ? '<div class="' . $class . '">' : '') . '<p' . ($div ? '' : ' class="' . $class . '"') . '>' .
                $ts . $msg .
                '</p>' . ($div ? '</div>' : '');
            if ($echo) {
                echo $res;
            }
        }

        return $res;
    }

    /**
     * Display a success message.
     *
     * @param string $msg       The message
     * @param bool   $timestamp With the timestamp
     * @param bool   $div       Inside a div (else in a p)
     * @param bool   $echo      Display the message?
     */
    public function success(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true): string
    {
        return $this->message($msg, $timestamp, $div, $echo, 'success');
    }

    /**
     * Display a warning message.
     *
     * @param string $msg       The message
     * @param bool   $timestamp With the timestamp
     * @param bool   $div       Inside a div (else in a p)
     * @param bool   $echo      Display the message?
     */
    public function warning(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true): string
    {
        return $this->message($msg, $timestamp, $div, $echo, 'warning-msg');
    }
}
