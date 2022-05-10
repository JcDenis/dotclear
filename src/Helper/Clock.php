<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\Clock;
use DateTime;
use DateTimeZone;
use Dotclear\Exception\HelperException;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Basic date and time handling tool.
 *
 * This class uses UTC as default timezone.
 * All methods of this class support named arguments.
 *
 * Dotclear timezones:
 * - All date and time registered in database must be in UTC format,
 * - Admin Process date should be display in user timezone,
 * - Public Process date should be display to user in blog timezone
 * - files handling should be in UTC
 *
 * @ingroup  Helper Date
 */
class Clock
{
    /**
     * @var DateTimeZone $tz
     *                   The default timezone
     */
    private static $tz;

    /**
     * @var array<string,string> $timezones
     *                           The list a known timezones
     */
    private static $timezones = [];

    /**
     * Get default timezone.
     *
     * @return string The timezone name
     */
    public static function getTZ(): string
    {
        if (!(self::$tz instanceof DateTimeZone)) {
            self::setTZ();
        }

        return self::$tz->getName();
    }

    /**
     * Set default timezone.
     *
     * @param string $timezone The timezone
     *
     * @return string The timezone
     */
    public static function setTZ(string $timezone = 'UTC'): string
    {
        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set($timezone);
        } elseif (!ini_get('safe_mode')) {
            putenv('TZ=' . $timezone);
        }

        self::$tz = new DateTimeZone($timezone);

        return self::getTZ();
    }

    /**
     * Format a date.
     *
     * This method take care off the input and ouput timezones.
     * If input and/or output timezone is ommitted, defaut timezone is used.
     * If date is ommitted, now is used.
     * Supported $format formats are described at https://www.php.net/manual/fr/datetime.format.php
     * Supported $date formats are described at https://www.php.net/manual/en/datetime.formats.php
     *
     * @param string          $format The format
     * @param null|int|string $date   The date
     * @param null|string     $from   The input timezone
     * @param null|string     $to     The output timezone
     *
     * @return string The timezoned formatted date (or empty string on error)
     */
    public static function format(string $format = 'Y-m-d H:i:s', int|string|null $date = null, ?string $from = null, ?string $to = null): string
    {
        // check TZ init
        self::getTZ();

        // If date is a timestamp, convert it to string
        if (preg_match('/^[0-9]+$/', (string) $date)) {
            $date = '@' . $date;
        }

        $from = is_null($from) ? self::$tz : new DateTimeZone($from);
        $to   = is_null($to) ? self::$tz : new DateTimeZone($to);

        try {
            return (new DateTime((string) ($date ?? 'now'), $from))->setTimezone($to)->format($format);
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Format a date into timestamp.
     *
     * @param null|int|string $date The date
     * @param null|string     $from The input timezone
     * @param null|string     $to   The output timezone
     *
     * @return int The timezoned timestamp (or 0 on error)
     */
    public static function ts(int|string|null $date = null, ?string $from = null, ?string $to = null): int
    {
        return (int) self::format('U', $date, $from, $to);
    }

    /**
     * Format a date into a database style format.
     *
     * @param null|int|string $date The date
     * @param null|string     $from The input timezone
     * @param null|string     $to   The output timezone
     *
     * @return string The timezoned formatted date (or empty string on error)
     */
    public static function database(int|string|null $date = null, ?string $from = null, ?string $to = null): string
    {
        return self::format('Y-m-d H:i:s', $date, $from, $to);
    }

    /**
     * Format a date into a form field date recognized by javascript calendar.
     *
     * @param null|int|string $date The date
     * @param null|string     $from The input timezone
     * @param null|string     $to   The output timezone
     *
     * @return string The timezoned formatted date (or empty string on error)
     */
    public static function formfield(int|string|null $date = null, ?string $from = null, ?string $to = null): string
    {
        return Html::escapeHTML(self::str('%Y-%m-%d\T%H:%M', $date, $from, $to));
    }

    /**
     * Format a date into iso8601 format (Use Atom format).
     *
     * @param null|int|string $date The date
     * @param null|string     $from The input timezone
     * @param null|string     $to   The output timezone
     *
     * @return string The timezoned formatted date (or empty string on error)
     */
    public static function iso8601(int|string|null $date = null, ?string $from = null, ?string $to = null): string
    {
        return self::format(DateTime::ATOM, $date, $from, $to);
    }

    /**
     * Format a date into rcf822 format.
     *
     * @param null|int|string $date The date
     * @param null|string     $from The input timezone
     * @param null|string     $to   The output timezone
     *
     * @return string The timezoned formatted date (or empty string on error)
     */
    public static function rfc822(int|string|null $date = null, ?string $from = null, ?string $to = null): string
    {
        return self::format(DateTime::RFC822, $date, $from, $to);
    }

    /**
     * Format a date using strftime() wildcards.
     *
     * Supported $format formats are from function strftime
     * described at http://www.php.net/manual/en/function.strftime.php
     * Special cases %a, %A, %b and %B are handled by L10n library.
     *
     * @param string          $format The format
     * @param null|int|string $date   The date
     * @param null|string     $from   The input timezone
     * @param null|string     $to     The output timezone
     *
     * @return string The timezoned formatted date (or empty string on error)
     */
    public static function str(string $format, int|string|null $date = null, ?string $from = null, ?string $to = null): string
    {
        $patterns = [
            '/(?<!%)%a/' => '{{__\a%w__}}',
            '/(?<!%)%A/' => '{{__\A%w__}}',
            '/(?<!%)%b/' => '{{__\b%m__}}',
            '/(?<!%)%B/' => '{{__\B%m__}}',
        ];

        $format = preg_replace(array_keys($patterns), array_values($patterns), $format);
        $format = self::strftimeToDateFormat($format);

        $res = self::format($format, $date, $from, $to);

        return preg_replace_callback('/{{__(a|A|b|B)([0-9]{1,2})__}}/', ['self', '_callback'], $res);
    }

    /**
     * Convert strftime() format to date() format.
     *
     * @param string $src The strftime() format
     *
     * @throws HelperException Thrown if a invalid format is used
     *
     * @return string The date() format
     */
    private static function strftimeToDateFormat(string $src = ''): string
    {
        $invalid  = ['%U', '%V', '%C', '%g', '%G'];
        $invalids = [];

        // It is important to note that some do not translate accurately ie. lowercase L is supposed to convert to number with a preceding space if it is under 10, there is no accurate conversion so we just use 'g'
        $converts = [
            '%a' => 'D',        // day name of week (3 characters)
            '%A' => 'l',        // day name of week (full)
            '%d' => 'd',        // day of month (2 digits)
            '%e' => 'j',        // day of month (trim with date(), with a space for single digit with strftime())
            '%u' => 'N',        // ISO-8601 numeric representation of the day of the week (1 for monday to 7 for sunday)
            '%w' => 'w',        // day of the week (0 for sunday to 6 for saturday)
            '%W' => 'W',        // week of year
            '%b' => 'M',        // month name (3 characters)
            '%h' => 'M',
            '%B' => 'F',        // month name (full)
            '%m' => 'm',        // month of year (2 digits)
            '%y' => 'y',        // year (last two digits)
            '%Y' => 'Y',        // year
            '%D' => 'm/d/y',    // date
            '%F' => 'Y-m-d',    // date
            '%x' => 'm/d/y',    // date
            '%n' => "\n",       // newline
            '%t' => "\t",       // tab
            '%H' => 'H',        // hour (2 digits)
            '%k' => 'G',        // hour
            '%I' => 'h',        // hour (12 hour format, 2 digits)
            '%l' => 'g',        // hour (12 hour format)
            '%M' => 'i',        // minutes (2 digits)
            '%p' => 'A',        // AM or PM
            '%P' => 'a',        // am or pm
            '%r' => 'h:i:s A',  // time %I:%M:%S %p
            '%R' => 'H:i',      // time %H:%M
            '%S' => 's',        // seconds (2 digits)
            '%T' => 'H:i:s',    // time %H:%M:%S
            '%X' => 'H:i:s',
            '%z' => 'O',                // Timezone offset
            '%Z' => 'T',                // Timezone abbreviation
            '%c' => 'D M j H:i:s Y',    // date as Sun May 13 02:15:10 1962
            '%s' => 'U',                // Unix Epoch Time timestamp
            '%%' => '%',                // literal % character
        ];

        foreach ($invalid as $format) {
            if (str_contains($src, $format)) {
                $invalids[] = $format;
            }
        }
        if (!empty($invalids)) {
            throw new HelperException('Found these invalid chars: ' . implode(',', $invalids) . ' in ' . $src);
        }

        return str_replace(array_keys($converts), array_values($converts), $src);
    }

    private static function _callback($args): string
    {
        $b = [
            1  => '_Jan',
            2  => '_Feb',
            3  => '_Mar',
            4  => '_Apr',
            5  => '_May',
            6  => '_Jun',
            7  => '_Jul',
            8  => '_Aug',
            9  => '_Sep',
            10 => '_Oct',
            11 => '_Nov',
            12 => '_Dec', ];

        $B = [
            1  => 'January',
            2  => 'February',
            3  => 'March',
            4  => 'April',
            5  => 'May',
            6  => 'June',
            7  => 'July',
            8  => 'August',
            9  => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December', ];

        $a = [
            1 => '_Mon',
            2 => '_Tue',
            3 => '_Wed',
            4 => '_Thu',
            5 => '_Fri',
            6 => '_Sat',
            0 => '_Sun', ];

        $A = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            0 => 'Sunday', ];

        return __(${$args[1]}[(int) $args[2]]);
    }

    /**
     * Timzones.
     *
     * Returns an array of supported timezones, codes are keys and names are values.
     *
     * @param bool $flip   Names are keys and codes are values
     * @param bool $groups Return timezones in arrays of continents
     */
    public static function getZones(bool $flip = false, bool $groups = false): array
    {
        if (empty(self::$timezones)) {
            $tz  = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
            $res = [];
            foreach ($tz as $v) {
                $v = trim($v);
                if ($v) {
                    $res[$v] = str_replace('_', ' ', $v);
                }
            }
            // Store timezones for further accesses
            self::$timezones = $res;
        } else {
            // Timezones already read from file
            $res = self::$timezones;
        }

        if ($flip) {
            $res = array_flip($res);
            if ($groups) {
                $tmp = [];
                foreach ($res as $k => $v) {
                    $g              = explode('/', $k);
                    $tmp[$g[0]][$k] = $v;
                }
                $res = $tmp;
            }
        }

        return $res;
    }

    /**
     * Check if a timezone exists.
     *
     * @return bool Ture if it is a timezone
     */
    public static function zoneExists(string $timezone): bool
    {
        return array_key_exists($timezone, self::getZones());
    }

    public static function getTimeOffset(int|string|null $date = null, ?string $from = null, ?string $to = null): int
    {
        return (int) self::format('Z', $date, $from, $to);
    }

    /**
     * Get a random integer.
     *
     * @return int The random integer
     */
    public static function rand(): int
    {
        return self::ts() * rand();
    }
}
