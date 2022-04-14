<?php
/**
 * @class Dotclear\Process\Admin\Notice\Notice
 * @brief Dotclear backend notices handling facilities
 *
 * Accessible from dotclear()->notice()->
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Notice;

use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Dt;

class Notice
{
    /** @var    string  notices table prefix */
    protected $prefix;

    /** @var    string  notices table */
    protected $table_name = 'notice';

    /** @var    string  notices table prefixed */
    protected $table;

    /** @var    array   notices types */
    private $N_TYPES = [
        // id → CSS class
        'success' => 'success',
        'warning' => 'warning-msg',
        'error'   => 'error',
        'message' => 'message',
        'static'  => 'static-msg'];

    /** @var    bool    is displayed error */
    private $error_displayed = false;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->table = dotclear()->prefix . $this->table_name;
    }

    /**
     * Get notice table name
     *
     * @return  string  The table name
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * Get notices
     *
     * Parameters can be :
     * - ses_id => (string) session id
     * - notice_id => one or more notice id
     * - notice_type => one or more notice type (alias notice_format)
     * - order
     * - limit
     * - sql
     *
     * @param   array   $params         The params
     * @param   bool    $count_only     Count only
     * @return  Record                  Notices record
     */
    public function get(array $params = [], bool $count_only = false): Record
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->from($this->table);

        // Return a recordset of notices
        if ($count_only) {
            $sql->column($sql->count('notice_id'));
        } else {
            $sql->columns([
                'notice_id',
                'ses_id',
                'notice_type',
                'notice_ts',
                'notice_msg',
                'notice_format',
                'notice_options',
            ]);
        }

        $session_id = isset($params['ses_id']) && $params['ses_id'] !== '' ? (string) $params['ses_id'] : (string) session_id();
        $sql->where('ses_id = ' . $sql->quote($session_id));

        if (isset($params['notice_id']) && $params['notice_id'] !== '') {
            if (is_array($params['notice_id'])) {
                array_walk($params['notice_id'], function (&$v, $k) { if ($v !== null) {$v = (int) $v;}});
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

        if (!$count_only) {
            if (!empty($params['order'])) {
                $sql->order($sql->escape($params['order']));
            } else {
                $sql->order('notice_ts DESC');
            }
        }

        if (!empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        $rs = $sql->select();

        return $rs;
    }

    /**
     * Add a notice
     *
     * @param   Cursor  $cur    The cursor
     */
    public function add(Cursor $cur): int
    {
        dotclear()->con()->writeLock($this->table);

        try {
            # Get ID
            $sql = new SelectStatement(__METHOD__);
            $rs = $sql
                ->column($sql->max('notice_id'))
                ->from($this->table)
                ->select();

            $cur->setField('notice_id', $rs->fInt() + 1);
            $cur->setField('ses_id', (string) session_id());

            $this->cursor($cur, $cur->getField('notice_id'));

            # --BEHAVIOR-- coreBeforeNoticeCreate
            dotclear()->behavior()->call('adminBeforeNoticeCreate', $this, $cur);

            $cur->insert();
            dotclear()->con()->unlock();
        } catch (\Exception $e) {
            dotclear()->con()->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterNoticeCreate
        dotclear()->behavior()->call('adminAfterNoticeCreate', $this, $cur);

        return $cur->getField('notice_id');
    }

    /**
     * Delete a notice
     *
     * @param   int|null    $notice_id      The notice id
     * @param   bool        $delete_all     Delete all notices
     */
    public function del(?int $notice_id, bool $delete_all = false): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->from($this->table)
            ->where($delete_all ? 
                'ses_id = ' . $sql->quote((string) session_id()) :
                'notice_id' . $sql->in($notice_id)
            )
            ->delete();
    }

    /**
     * Get notices cursor
     *
     * @param   Cursor      $cur        The cursor
     * @param   int|null    $notice_id  The notice id
     */
    private function cursor(Cursor $cur, int $notice_id = null): void
    {
        if ('' === $cur->getField('notice_msg')) {
            throw new AdminException(__('No notice message'));
        }

        if ('' === $cur->getField('notice_ts') || null === $cur->getField('notice_ts')) {
            $cur->setField('notice_ts', date('Y-m-d H:i:s'));
        }

        if ('' === $cur->getField('notice_format') || null === $cur->getField('notice_format')) {
            $cur->setField('notice_format', 'text');
        }

        $notice_id = is_int($notice_id) ? $notice_id : $cur->getField('notice_id');
    }

    /**
     * Gets the HTML code of notices.
     *
     * @return  string  The notices.
     */
    public function getNotices(): string
    {
        $res = '';

        # Return error messages if any
        if (dotclear()->error()->flag() && !$this->error_displayed) {

            # --BEHAVIOR-- adminPageNotificationError
            $notice_error = dotclear()->behavior()->call('adminPageNotificationError');

            if (isset($notice_error) && !empty($notice_error)) {
                $res .= $notice_error;
            } else {
                $res .= sprintf(
                    '<div class="error" role="alert"><p><strong>%s</strong></p>%s</div>',
                    count(dotclear()->error()->dump()) > 1 ? __('Errors:') : __('Error:'),
                    dotclear()->error()->toHTML()
                );
            }
            $this->error_displayed = true;
        } else {
            $this->error_displayed = false;
        }

        # Return notices if any
        # Should retrieve static notices first, then others
        $step = 2;
        do {
            if (2 == $step) {
                // Static notifications
                $params = [
                    'notice_type' => 'static'
                ];
            } else {
                // Normal notifications
                $params = [
                    'sql' => "AND notice_type != 'static'"
                ];
            }
            $counter = $this->get($params, true)->fInt();
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
                        'format' => $lines->f('notice_format')
                    ];
                    if (null !== $lines->f('notice_options')) {
                        $notifications = array_merge($notification, @json_decode($lines->f('notice_options'), true));
                    }
                    # --BEHAVIOR-- adminPageNotification, array
                    $notice = dotclear()->behavior()->call('adminPageNotification', $notification);

                    $res .= !empty($notice) ? $notice : $this->getNotification($notification);
                }
            }
        } while (--$step);

        # Delete returned notices
        $this->del(null, true);

        return $res;
    }

    /**
     * Adds a notice.
     *
     * @param   string  $type       The type
     * @param   string  $message    The message
     * @param   array   $options    The options
     */
    public function addNotice(string $type, string $message, array $options = []): void
    {
        $cur = dotclear()->con()->openCursor($this->table());

        $cur->setField('notice_type', $type);
        $cur->setField('notice_ts', isset($options['ts']) && $options['ts'] ? $options['ts'] : date('Y-m-d H:i:s'));
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
     * @param   string  $message    The message
     * @param   array   $options    The options
     */
    public function addSuccessNotice(string $message, array $options = []): void
    {
        $this->addNotice('success', $message, $options);
    }

    /**
     * Adds a warning notice.
     *
     * @param   string  $message    The message
     * @param   array   $options    The options
     */
    public function addWarningNotice(string $message, array $options = []): void
    {
        $this->addNotice('warning', $message, $options);
    }

    /**
     * Adds an error notice.
     *
     * @param   string  $message    The message
     * @param   array   $options    The options
     */
    public function addErrorNotice(string $message, array $options = []): void
    {
        $this->addNotice('error', $message, $options);
    }

    /**
     * Gets the notification.
     *
     * @param   array   $notification   The notification
     *
     * @return  string  The notification.
     */
    private function getNotification(array $notification): string
    {
        $tag = isset($notification['format']) && $notification['format'] === 'html' ? 'div' : 'p';
        $ts  = '';
        if (!isset($notification['with_ts']) || ($notification['with_ts'] == true)) {
            $ts = sprintf(
                '<span class="notice-ts"><time datetime="%s">%s</time></span>',
                Dt::iso8601(strtotime($notification['ts']), dotclear()->user()->getInfo('user_tz')),
                Dt::dt2str(__('%H:%M:%S'), $notification['ts'], dotclear()->user()->getInfo('user_tz')),
            );
        }
        $res = '<' . $tag . ' class="' . $notification['class'] . '" role="alert">' . $ts . $notification['text'] . '</' . $tag . '>';

        return $res;
    }

    /**
     * Direct messages, usually immediately displayed
     *
     * @param   string  $msg        The message
     * @param   bool    $timestamp  With the timestamp
     * @param   bool    $div        Inside a div (else in a p)
     * @param   bool    $echo       Display the message?
     * @param   string  $class      The class of block (div/p)
     *
     * @return  string
     */
    public function message(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true, string $class = 'message'): string
    {
        $res = '';
        if ($msg != '') {
            $ts = '';
            if ($timestamp) {
                $ts = sprintf(
                    '<span class="notice-ts"><time datetime="%s">%s</time></span>',
                    Dt::iso8601(time(), dotclear()->user()->getInfo('user_tz')),
                    Dt::str(__('%H:%M:%S'), null, dotclear()->user()->getInfo('user_tz')),
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
     * Display a success message
     *
     * @param   string  $msg        The message
     * @param   bool    $timestamp  With the timestamp
     * @param   bool    $div        Inside a div (else in a p)
     * @param   bool    $echo       Display the message?
     *
     * @return  string
     */
    public function success(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true): string
    {
        return $this->message($msg, $timestamp, $div, $echo, 'success');
    }

    /**
     * Display a warning message
     *
     * @param   string  $msg        The message
     * @param   bool    $timestamp  With the timestamp
     * @param   bool    $div        Inside a div (else in a p)
     * @param   bool    $echo       Display the message?
     *
     * @return  string
     */
    public function warning(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true): string
    {
        return $this->message($msg, $timestamp, $div, $echo, 'warning-msg');
    }
}
