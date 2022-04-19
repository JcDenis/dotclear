<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Helper\Dt;

/**
 * Blogs record helpers.
 *
 * \Dotclear\Core\RsExt\RsExtBlog
 *
 * @ingroup  Core Blog Record
 */
class RsExtBlog extends RsExtend
{
    /**
     * Gets the blog update date timestamp.
     *
     * @return int the ts
     */
    public function getTS(): int
    {
        return (int) strtotime($this->rs->f('blog_upddt'));
    }

    /**
     * Returns blog update date formating according to the ISO 8601 standard.
     *
     * @param string $tz The timezone
     *
     * @return string the iso 8601 date
     */
    public function getISO8601Date(string $tz = ''): string
    {
        return Dt::iso8601($this->getTS(), $tz ?: dotclear()->blog()->settings()->get('system')->get('blog_timezone'));
    }

    /**
     * Returns blog update date formating according to RFC 822.
     *
     * @param string $tz The timezone
     *
     * @return string the rfc 822 date
     */
    public function getRFC822Date(string $tz = ''): string
    {
        return Dt::rfc822($this->getTS(), $tz ?: dotclear()->blog()->settings()->get('system')->get('blog_timezone'));
    }

    /**
     * Returns blog update date with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>date_format</var> blog setting.
     *
     * @param string $format The date format pattern
     *
     * @return string the date
     */
    public function getDate(string $format = ''): string
    {
        return Dt::dt2str($format ?: dotclear()->blog()->settings()->get('system')->get('date_format'), $this->rs->f('blog_upddt'));
    }

    /**
     * Returns blog update time with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>time_format</var> blog setting.
     *
     * @param string $format The time format pattern
     *
     * @return string the time
     */
    public function getTime(string $format): string
    {
        return Dt::dt2str($format ?: dotclear()->blog()->settings()->get('system')->get('time_format'), $this->rs->f('blog_upddt'));
    }
}
