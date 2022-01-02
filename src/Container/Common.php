<?php
/**
 * @class Dotclear\Container\Common
 * @brief Dotclear simple Container helper
 *
 * @package Dotclear
 * @subpackage Container
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Container;

use Dotclear\Utils\Dt;

class Common
{
    public static $check_password_len = 6;

    public static function checkType(mixed $arg, string $type)
    {
        return gettype($arg) == $type;
    }

    public static function toType(mixed $arg, $type)
    {
        if (!settype($arg, $type)) {
            throw new Exception('Unknow type') ;
        }

        return $arg;
    }

    public static function checkBinary(mixed $arg, $strict = true): bool
    {
        return is_int($arg) && in_array($arg, [0, 1]);
    }

    public static function toBinary(mixed $arg): int
    {
        return (int) (bool) $arg;
    }

    public static function checkString(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && trim((string) $arg) != '' : is_string($arg);
    }

    public static function toString(mixed $arg): string
    {
        return trim((string) $arg);
    }

    public static function checkInteger(mixed $arg, $strict = true): bool
    {
        return is_int($arg);
    }

    public static function toInteger(mixed $arg): int
    {
        return (int) $arg;
    }

    public static function checkPassword(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && strlen((string) $arg) >= (int) self::$check_password_len : is_string($arg);
    }

    public static function toPassword(mixed $arg): string
    {
        return (string) $arg;
    }

    public static function checkEmail(mixed $arg, $strict = true): bool
    {
        return $strict ? filter_var((string) $arg, FILTER_VALIDATE_EMAIL) !== false : is_string($arg);
    }

    public static function toEmail(mixed $arg): string
    {
        return trim((string) $arg);
    }

    public static function checkURL(mixed $arg, $strict = true): bool
    {
        return $strict ? filter_var((string) $arg, FILTER_VALIDATE_URL) !== false : is_string($arg);
    }

    public static function toURL(mixed $arg): string
    {
        $arg = trim((string) $arg);

        return empty($arg) || preg_match('|^http(s?)://|', (string) $arg) ? $arg : 'http://' . $arg;
    }

    public static function checkLang(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $arg) : is_string($arg);
    }

    public static function toLang(mixed $arg): string
    {
        return trim((string) $arg);
    }

    public static function checkTZ(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && in_array($arg, Dt::getZones(true)) : is_string($arg);
    }

    public static function toTZ(mixed $arg): string
    {
        return trim((string) $arg);
    }
}
