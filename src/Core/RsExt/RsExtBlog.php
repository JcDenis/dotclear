<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

// Dotclear\Core\RsExt\RsExtBlog
use Dotclear\App;
use Dotclear\Helper\Clock;

/**
 * Blogs record helpers.
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
        return Clock::ts(
            date: $this->rs->f('blog_upddt'),
            to: App::core()->timezone()
        );
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
        return Clock::iso8601(
            date: $this->getTS(),
            from: App::core()->timezone(),
            to: ($tz ?: App::core()->timezone())
        );
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
        return Clock::rfc822(
            date: $this->getTS(),
            from: App::core()->timezone(),
            to: ($tz ?: App::core()->timezone())
        );
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
        return Clock::str(
            format: ($format ?: App::core()->blog()->settings()->getGroup('system')->getSetting('date_format')),
            date: $this->rs->f('blog_upddt'),
            to: App::core()->timezone()
        );
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
        return Clock::str(
            format: ($format ?: App::core()->blog()->settings()->getGroup('system')->getSetting('time_format')),
            date: $this->rs->f('blog_upddt'),
            to: App::core()->timezone()
        );
    }
}
