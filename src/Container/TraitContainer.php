<?php
/**
 * @class Dotclear\Container\TraitContainer
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


use Dotclear\Helper\Dt;

trait TraitContainer
{
    /** @var integer Password lengh (by default we talk about encoded password!) */
    public static $password_len = 40;

    public static function isType(mixed $arg, string $type)
    {
        return gettype($arg) == $type;
    }

    public static function toType(mixed $arg, ?string $type)
    {
        if ($type !== null) {
            if (!@settype($arg, $type)) {
                throw new \Exception('Could not convert type');
            }
        }

        return $arg;
    }

    public static function isBinary(mixed $arg, $strict = true): bool
    {
        return is_int($arg) && in_array($arg, [0, 1]);
    }

    public static function toBinary(mixed $arg): int
    {
        return (int) (bool) $arg;
    }

    public static function isString(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && trim((string) $arg) != '' : is_string($arg);
    }

    public static function toString(mixed $arg): string
    {
        return trim((string) $arg);
    }

    public static function isInteger(mixed $arg, $strict = true): bool
    {
        return is_int($arg);
    }

    public static function toInteger(mixed $arg): int
    {
        return (int) $arg;
    }

    public static function isPassword(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && strlen((string) $arg) >= (int) self::$password_len : is_string($arg);
    }

    public static function toPassword(mixed $arg): string
    {
        return (string) $arg;
    }

    public static function isEmail(mixed $arg, $strict = true): bool
    {
        return $strict ? filter_var((string) $arg, FILTER_VALIDATE_EMAIL) !== false : is_string($arg);
    }

    public static function toEmail(mixed $arg): string
    {
        return trim((string) $arg);
    }

    public static function isURL(mixed $arg, $strict = true): bool
    {
        return $strict ? filter_var((string) $arg, FILTER_VALIDATE_URL) !== false : is_string($arg);
    }

    public static function toURL(mixed $arg): string
    {
        $arg = trim((string) $arg);

        return empty($arg) || preg_match('|^http(s?)://|', (string) $arg) ? $arg : 'http://' . $arg;
    }

    public static function isLang(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $arg) : is_string($arg);
    }

    public static function toLang(mixed $arg): string
    {
        return trim((string) $arg);
    }

    public static function isTZ(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && in_array($arg, Dt::getZones(true)) : is_string($arg);
    }

    public static function toTZ(mixed $arg): string
    {
        return trim((string) $arg);
    }
}
