<?php
/**
 * @class Dotclear\Utils\Statistic
 * @brief Basic time and memory usage
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Utils;

class Statistic
{
    public static $start_time;
    public static $start_memory;

    /**
     * Save start time and memory usage
     */
    public static function start(): void
    {
        if (!static::$start_time) {
            static::$start_time = microtime(true);
        }
        if (!static::$start_memory) {
            static::$start_memory = memory_get_usage(false);
        }
    }

    /**
     * Return elapsed time since script has been started
     *
     * @param   int|null    $mtime  Timestamp (microtime format) to evaluate delta from current time is taken if null
     *
     * @return  string  The elapsed time.
     */
    public static function time(?int $mtime = null): string
    {
        $start = static::$start_time ?: microtime(true);

        return strval(round(($mtime === null ? microtime(true) - $start : $mtime - $start), 5));
    }

    /**
     * Return memory consumed since script has been started
     *
     * @param   int|null    $mmem   Memory usage to evaluate
     * delta from current memory usage is taken if null
     *
     * @return  string  The consumed memory.
     */
    public static function memory(?int $mmem = null): string
    {
        $start = static::$start_memory ?: memory_get_usage(false);

        $usage = $mmem === null ? memory_get_usage(false) - $start : $mmem - $start;
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return strval(round($usage / pow(1024, ($i = floor(log($usage, 1024)))), 2)) . ' ' . $unit[$i];
    }
}
