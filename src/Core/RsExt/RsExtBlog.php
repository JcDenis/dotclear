<?php
/**
 * @class Dotclear\Core\RsExt\rsExtBlog
 * @brief Dotclear blogs record helpers.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Container\User as ContainerUser;

use Dotclear\Database\Record;

use Dotclear\Html\Html;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class RsExtBlog
{
    /**
     * Gets the blog update date timestamp.
     *
     * @param  Record   $rs     Invisible parameter
     *
     * @return integer          The ts.
     */
    public static function getTS(Record $rs)
    {
        return strtotime($rs->blog_upddt);
    }

    /**
     * Returns blog update date formating according to the ISO 8601 standard.
     *
     * @param   Record  $rs     Invisible parameter
     * @param   string  $tz     The timezone
     *
     * @return  string          The iso 8601 date.
     */
    public static function getISO8601Date(Record $rs, string $tz = ''): string
    {
        if (!$tz) {
            $tz = $rs->core->blog->settings->system->blog_timezone;
        }
        return Dt::iso8601($rs->getTS(), $tz);
    }

    /**
     * Returns blog update date formating according to RFC 822.
     *
     * @param   Record  $rs     Invisible parameter
     * @param   string  $tz     The timezone
     *
     * @return  string          The rfc 822 date.
     */
    public static function getRFC822Date(Record $rs, string $tz = ''): string
    {
        if (!$tz) {
            $tz = $rs->core->blog->settings->system->blog_timezone;
        }

        return Dt::rfc822($rs->getTS(), $tz);
    }

    /**
     * Returns blog update date with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>date_format</var> blog setting.
     *
     * @param   Record  $rs         Invisible parameter
     * @param   string  $format     The date format pattern
     *
     * @return  string              The date.
     */
    public static function getDate(Record $rs, string $format = ''): string
    {
        if (!$format) {
            $format = $rs->core->blog->settings->system->date_format;
        }

        return Dt::dt2str($format, $rs->blog_upddt);
    }

    /**
     * Returns blog update time with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>time_format</var> blog setting.
     *
     * @param   Record  $rs         Invisible parameter
     * @param   string  $format     The time format pattern
     *
     * @return  string              The time.
     */
    public static function getTime(Record $rs, string $format): string
    {
        if (!$format) {
            $format = $rs->core->blog->settings->system->time_format;
        }

        return Dt::dt2str($format, $rs->blog_upddt);
    }
}
