<?php
/**
 * @class Dotclear\Admin\Notices
 * @brief Dotclear backend notices handling facilities
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Core\Core;

use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Notices
{
    /** @var    Core        Core instance */
    public static $core;

    /** @var    array       notices types */
    private static $N_TYPES = [
        // id → CSS class
        'success' => 'success',
        'warning' => 'warning-msg',
        'error'   => 'error',
        'message' => 'message',
        'static'  => 'static-msg'];

    /** @var    bool        is displayed error */
    private static $error_displayed = false;

    /**
     * Gets the HTML code of notices.
     *
     * @return  string  The notices.
     */
    public static function getNotices(): string
    {
        $res = '';

        # Return error messages if any
        if (self::$core->error->flag() && !self::$error_displayed) {

            # --BEHAVIOR-- before:AdminNotices:getNotices, Dotclear\Core\Error //duplicate as core is now passed to behaviors?
            $notice_error = self::$core->behaviors->call('before:Admin:Notices:getNotices', self::$core->error);

            if (isset($notice_error) && !empty($notice_error)) {
                $res .= $notice_error;
            } else {
                $res .= sprintf(
                    '<div class="error" role="alert"><p><strong>%s</strong></p>%s</div>',
                    count(self::$core->error->getErrors()) > 1 ? __('Errors:') : __('Error:'),
                    self::$core->error->toHTML()
                );
            }
            self::$error_displayed = true;
        } else {
            self::$error_displayed = false;
        }

        # Return notices if any
        # Should retrieve static notices first, then others
        $step = 2;
        do {
            if ($step == 2) {
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
            $counter = self::$core->notices->get($params, true);
            if ($counter) {
                $lines = self::$core->notices->get($params);
                while ($lines->fetch()) {
                    if (isset(self::$N_TYPES[$lines->notice_type])) {
                        $class = self::$N_TYPES[$lines->notice_type];
                    } else {
                        $class = $lines->notice_type;
                    }
                    $notification = [
                        'type'   => $lines->notice_type,
                        'class'  => $class,
                        'ts'     => $lines->notice_ts,
                        'text'   => $lines->notice_msg,
                        'format' => $lines->notice_format
                    ];
                    if ($lines->notice_options !== null) {
                        $notifications = array_merge($notification, @json_decode($lines->notice_options, true));
                    }
                    # --BEHAVIOR-- after:Admin:Notices:getNotices, array
                    $notice = self::$core->behaviors->call('after:Admin:Notices:getNotices', $notification);

                    $res .= !empty($notice) ? $notice : self::getNotification($notification);
                }
            }
        } while (--$step);

        # Delete returned notices
        self::$core->notices->del(null, true);

        return $res;
    }

    /**
     * Adds a notice.
     *
     * @param   string  $type       The type
     * @param   string  $message    The message
     * @param   array   $options    The options
     */
    public static function addNotice(string $type, string $message, array $options = []): void
    {
        $cur = self::$core->con->openCursor(self::$core->prefix . self::$core->notices->table());

        $cur->notice_type    = $type;
        $cur->notice_ts      = isset($options['ts']) && $options['ts'] ? $options['ts'] : date('Y-m-d H:i:s');
        $cur->notice_msg     = $message;
        $cur->notice_options = json_encode($options);

        if (isset($options['divtag']) && $options['divtag']) {
            $cur->notice_format = 'html';
        }
        if (isset($options['format']) && $options['format']) {
            $cur->notice_format = $options['format'];
        }

        self::$core->notices->add($cur);
    }

    /**
     * Adds a success notice.
     *
     * @param   string  $message    The message
     * @param   array   $options    The options
     */
    public static function addSuccessNotice(string $message, array $options = []): void
    {
        self::addNotice('success', $message, $options);
    }

    /**
     * Adds a warning notice.
     *
     * @param   string  $message    The message
     * @param   array   $options    The options
     */
    public static function addWarningNotice(string $message, array $options = []): void
    {
        self::addNotice('warning', $message, $options);
    }

    /**
     * Adds an error notice.
     *
     * @param   string  $message    The message
     * @param   array   $options    The options
     */
    public static function addErrorNotice(string $message, array $options = []): void
    {
        self::addNotice('error', $message, $options);
    }

    /**
     * Gets the notification.
     *
     * @param   array   $notification   The notification
     *
     * @return  string  The notification.
     */
    private static function getNotification(array $notification): string
    {
        $tag = isset($notification['format']) && $notification['format'] === 'html' ? 'div' : 'p';
        $ts  = '';
        if (!isset($notification['with_ts']) || ($notification['with_ts'] == true)) {
            $ts = sprintf(
                '<span class="notice-ts"><time datetime="%s">%s</time></span>',
                Dt::iso8601(strtotime($notification['ts']), self::$core->auth->getInfo('user_tz')),
                Dt::dt2str(__('%H:%M:%S'), $notification['ts'], self::$core->auth->getInfo('user_tz')),
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
    public static function message(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true, string $class = 'message'): string
    {
        $res = '';
        if ($msg != '') {
            $ts = '';
            if ($timestamp) {
                $ts = sprintf(
                    '<span class="notice-ts"><time datetime="%s">%s</time></span>',
                    Dt::iso8601(time(), self::$core->auth->getInfo('user_tz')),
                    Dt::str(__('%H:%M:%S'), null, self::$core->auth->getInfo('user_tz')),
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
    public static function success(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true): string
    {
        return self::message($msg, $timestamp, $div, $echo, 'success');
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
    public static function warning(string $msg, bool $timestamp = true, bool $div = false, bool $echo = true): string
    {
        return self::message($msg, $timestamp, $div, $echo, 'warning-msg');
    }
}
