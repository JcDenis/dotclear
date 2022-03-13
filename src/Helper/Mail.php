<?php
/**
 * @class Dotclear\Utils\Mail
 * @brief Basic mail tool
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Utils;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

use Dotclear\Exception\UtilsException;

use Dotclear\Utils\Text;

class Mail
{
    /**
     * Send email
     *
     * Sends email to destination. If a function called _mail() exists it will
     * be used instead of PHP mail() function. _mail() function should have the
     * same signature. Headers could be provided as a string or an array.
     *
     * @param string        $to            Email destination
     * @param string        $subject        Email subject
     * @param string        $message        Email message
     * @param string|array    $headers        Email headers
     * @param string        $p            UNIX mail additionnal parameters
     * @return boolean                    true on success
     */
    public static function sendMail(string $to, string $subject, string $message, $headers = null, $p = null): bool
    {
        /**
         * User defined mail function
         *
         * @var        callable $f
         */
        $f   = function_exists('_mail') ? '_mail' : null;
        $eol = trim(ini_get('sendmail_path')) ? "\n" : "\r\n";

        if (is_array($headers)) {
            $headers = implode($eol, $headers);
        }

        if ($f == null) {
            if (!@mail($to, $subject, $message, $headers, $p)) {
                throw new UtilsException('Unable to send email');
            }
        } else {
            call_user_func($f, $to, $subject, $message, $headers, $p);
        }

        return true;
    }

    /**
     * Get Host MX
     *
     * Returns MX records sorted by weight for a given host.
     *
     * @param string    $host        Hostname
     * @return array|null
     */
    public static function getMX(string $host): ?array
    {
        if (!getmxrr($host, $mx_h, $mx_w) || count($mx_h) == 0) {
            return null;
        }

        $res = [];

        for ($i = 0; $i < count($mx_h); $i++) {
            $res[$mx_h[$i]] = $mx_w[$i];
        }

        asort($res);

        return $res;
    }

    /**
     * Quoted printable header
     *
     * Encodes given string as a quoted printable mail header.
     *
     * @param      string $str String to encode
     * @param      string $charset Charset (default UTF-8)
     * @return     string
     */
    public static function QPHeader(string $str, string $charset = 'UTF-8'): string
    {
        if (!preg_match('/[^\x00-\x3C\x3E-\x7E]/', $str)) {
            return $str;
        }

        return '=?' . $charset . '?Q?' . Text::QPEncode($str) . '?=';
    }

    /**
     * B64 header
     *
     * Encodes given string as a base64 mail header.
     *
     * @param      string $str String to encode
     * @param      string $charset Charset (default UTF-8)
     * @return string
     */
    public static function B64Header(string $str, string $charset = 'UTF-8'): string
    {
        if (!preg_match('/[^\x00-\x3C\x3E-\x7E]/', $str)) {
            return $str;
        }

        return '=?' . $charset . '?B?' . base64_encode($str) . '?=';
    }
}
