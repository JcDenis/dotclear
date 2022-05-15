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
class Notice
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
     * @see self::get() whitout parameter order.
     *
     * @param array $params The parameters
     *
     * @return int The notices count
     */
    public function count(array $params = []): int
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->from(App::core()->prefix() . 'notice')
            ->column($sql->count('notice_id'))
        ;

        return $this->query($params, $sql)->fInt(0);
    }

    /**
     * Get notices.
     *
     * Parameters can be :
     * - ses_id => (string) session id
     * - notice_id => one or more notice id
     * - notice_type => one or more notice type (alias notice_format)
     * - order
     * - limit
     * - sql
     *
     * @param array $params The params
     *
     * @return Record Notices record
     */
    public function get(array $params = []): Record
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->from(App::core()->prefix() . 'notice')
            ->columns([
                'notice_id',
                'ses_id',
                'notice_type',
                'notice_ts',
                'notice_msg',
                'notice_format',
                'notice_options',
            ])
            ->order(
                empty($params['order']) ?
                'notice_ts DESC' :
                $sql->escape($params['order'])
            )
        ;

        return $this->query($params, $sql);
    }

    /**
     * Query notice table.
     *
     * @param array           $params The params
     * @param SelectStatement $sql    The partial sql statement
     *
     * @return Record The result
     */
    private function query(array $params, SelectStatement $sql): Record
    {
        $session_id = isset($params['ses_id']) && '' !== $params['ses_id'] ? (string) $params['ses_id'] : (string) session_id();
        $sql->where('ses_id = ' . $sql->quote($session_id));

        if (isset($params['notice_id']) && '' !== $params['notice_id']) {
            if (is_array($params['notice_id'])) {
                array_walk($params['notice_id'], function (&$v, $k) {
                    if (null !== $v) {
                        $v = (int) $v;
                    }
                });
            } else {
                $params['notice_id'] = [(int) $params['notice_id']];
            }
            $sql->and('notice_id' . $sql->in($params['notice_id']));
        }

        if (!empty($params['notice_type'])) {
            $sql->and('notice_type' . $sql->in($params['notice_type']));
        }

        if (!empty($params['notice_format'])) {
            $sql->and('notice_format' . $sql->in($params['notice_format']));
        }

        if (!empty($params['sql'])) {
            $sql->sql($params['sql']);
        }

        if (!empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        return $sql->select();
    }

    /**
     * Add a notice.
     *
     * @param Cursor $cur The cursor
     */
    public function add(Cursor $cur): int
    {
        App::core()->con()->writeLock(App::core()->prefix() . 'notice');

        try {
            // Get ID
            $sql = new SelectStatement(__METHOD__);
            $rs  = $sql
                ->column($sql->max('notice_id'))
                ->from(App::core()->prefix() . 'notice')
                ->select()
            ;

            $cur->setField('notice_id', $rs->fInt() + 1);
            $cur->setField('ses_id', (string) session_id());

            $this->cursor($cur, $cur->getField('notice_id'));

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
     * @param array|int $notice_id The notice id
     */
    public function del(int|array $notice_id): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->from(App::core()->prefix() . 'notice')
            ->where('notice_id' . $sql->in($notice_id))
            ->delete()
        ;
    }

    /**
     * Delete all session notices.
     */
    public function delSession(): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->from(App::core()->prefix() . 'notice')
            ->where('ses_id = ' . $sql->quote((string) session_id()))
            ->delete()
        ;
    }

    /**
     * Get notices cursor.
     *
     * @param Cursor   $cur       The cursor
     * @param null|int $notice_id The notice id
     */
    private function cursor(Cursor $cur, int $notice_id = null): void
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
    public function getNotices(): string
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
            if (2 == $step) {
                // Static notifications
                $params = [
                    'notice_type' => 'static',
                ];
            } else {
                // Normal notifications
                $params = [
                    'sql' => "AND notice_type != 'static'",
                ];
            }
            $counter = $this->count($params);
            if (0 < $counter) {
                $lines = $this->get($params);
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
        $this->delSession();

        return $res;
    }

    /**
     * Adds a notice.
     *
     * @param string $type    The type
     * @param string $message The message
     * @param array  $options The options
     */
    public function addNotice(string $type, string $message, array $options = []): void
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

        $this->add($cur);
    }

    /**
     * Adds a success notice.
     *
     * @param string $message The message
     * @param array  $options The options
     */
    public function addSuccessNotice(string $message, array $options = []): void
    {
        $this->addNotice('success', $message, $options);
    }

    /**
     * Adds a warning notice.
     *
     * @param string $message The message
     * @param array  $options The options
     */
    public function addWarningNotice(string $message, array $options = []): void
    {
        $this->addNotice('warning', $message, $options);
    }

    /**
     * Adds an error notice.
     *
     * @param string $message The message
     * @param array  $options The options
     */
    public function addErrorNotice(string $message, array $options = []): void
    {
        $this->addNotice('error', $message, $options);
    }

    /**
     * Gets the notification.
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
