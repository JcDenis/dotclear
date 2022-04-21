<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\Mail
use Dotclear\Exception\HelperException;

/**
 * Basic mail tool.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Mail
 */
class Mail
{
    /**
     * Send email.
     *
     * Sends email to destination. If a function called _mail() exists it will
     * be used instead of PHP mail() function. _mail() function should have the
     * same signature. Headers could be provided as a string or an array.
     *
     * $f is a user defined mail function
     *
     * @param string       $to      Email destination
     * @param string       $subject Email subject
     * @param string       $message Email message
     * @param array|string $headers Email headers
     * @param string       $p       UNIX mail additionnal parameters
     *
     * @return bool true on success
     */
    public static function sendMail(string $to, string $subject, string $message, $headers = null, $p = null): bool
    {
        $eol = trim(ini_get('sendmail_path')) ? "\n" : "\r\n";

        if (is_array($headers)) {
            $headers = implode($eol, $headers);
        }

        $f = '_mail';
        if (function_exists($f)) {
            /** @phpstan-ignore-next-line */
            call_user_func($f, $to, $subject, $message, $headers, $p);
        } else {
            if (!@mail($to, $subject, $message, $headers, $p)) {
                throw new HelperException('Unable to send email');
            }
        }

        return true;
    }

    /**
     * Get Host MX.
     *
     * Returns MX records sorted by weight for a given host.
     *
     * @param string $host Hostname
     */
    public static function getMX(string $host): ?array
    {
        if (!getmxrr($host, $mx_h, $mx_w) || count($mx_h) == 0) {
            return null;
        }

        $res = [];

        for ($i = 0; count($mx_h) > $i; ++$i) {
            $res[$mx_h[$i]] = $mx_w[$i];
        }

        asort($res);

        return $res;
    }

    /**
     * Quoted printable header.
     *
     * Encodes given string as a quoted printable mail header.
     *
     * @param string $str     String to encode
     * @param string $charset Charset (default UTF-8)
     */
    public static function QPHeader(string $str, string $charset = 'UTF-8'): string
    {
        if (!preg_match('/[^\x00-\x3C\x3E-\x7E]/', $str)) {
            return $str;
        }

        return '=?' . $charset . '?Q?' . Text::QPEncode($str) . '?=';
    }

    /**
     * B64 header.
     *
     * Encodes given string as a base64 mail header.
     *
     * @param string $str     String to encode
     * @param string $charset Charset (default UTF-8)
     */
    public static function B64Header(string $str, string $charset = 'UTF-8'): string
    {
        if (!preg_match('/[^\x00-\x3C\x3E-\x7E]/', $str)) {
            return $str;
        }

        return '=?' . $charset . '?B?' . base64_encode($str) . '?=';
    }
}
