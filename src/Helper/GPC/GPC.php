<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\GPC;

// Dotclear\Helper\GPC\GPC;

/**
 * HTTP GPC parser.
 *
 * This static class reads and parses all _GET, _POST, (_REQUEST), _COOKIE values
 * on the first call to one of their methods. Once done, these PHP variables are deleted.
 * Each method of this class returns a GPCGroup instance of read-only values
 * with methods to get typped value.
 *
 * _REQUEST includes _GET then _POST, but not _COOKIE.
 *
 * @ingroup Core Helper Http
 */
final class GPC
{
    /**
     * @var array<string,GPCGroup> $values
     *                             Array of groups instances
     */
    private static $values;

    /**
     * Group _GET instance.
     *
     * @return GPCGroup The _GET representation
     */
    public static function get(): GPCGroup
    {
        return self::getValues('get');
    }

    /**
     * Group _POST instance.
     *
     * @return GPCGroup The _POST representation
     */
    public static function post(): GPCGroup
    {
        return self::getValues('post');
    }

    /**
     * Group _REQUEST instance.
     *
     * @return GPCGroup The _REQUEST representation
     */
    public static function request(): GPCGroup
    {
        return self::getValues('request');
    }

    /**
     * Group _COOKIE instance.
     *
     * @return GPCGroup The _COOKIE representation
     */
    public static function cookie(): GPCGroup
    {
        return self::getValues('cookie');
    }

    /**
     * Get a group instance.
     *
     * @return GPCGroup The group instance
     */
    private static function getValues(string $type): GPCGroup
    {
        self::parseValues();

        return self::$values[$type];
    }

    /**
     * Parse PHP GPC values.
     */
    private static function parseValues(): void
    {
        if (!self::$values) {
            $g = [
                'get'     => [],
                'post'    => [],
                'request' => [],
                'cookie'  => [],
            ];

            foreach ($_GET as $k => $v) {
                $g['get'][$k] = self::trimValues($v);
            }
            foreach ($_POST as $k => $v) {
                $g['post'][$k] = self::trimValues($v);
            }
            foreach ($_COOKIE as $k => $v) {
                $g['cookie'][$k] = self::trimValues($v);
            }

            self::$values = [
                'get'     => new GPCGroup($g['get']),
                'post'    => new GPCGroup($g['post']),
                'request' => new GPCGroup(array_merge($g['get'], $g['post'])),
                'cookie'  => new GPCGroup($g['cookie']),
            ];

            // $_GET = $_POST = $_REQUEST = $_COOKIE = [];
            unset($_GET, $_POST, $_REQUEST, $_COOKIE, $g);
        }
    }

    /**
     * Trim a value.
     *
     * @param mixed $value The value
     *
     * @return mixed The value
     */
    private static function trimValues(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = self::trimValues($v);
                } else {
                    $value[$k] = trim($v);
                }
            }
        } else {
            $value = trim($value);
        }

        return $value;
    }
}
