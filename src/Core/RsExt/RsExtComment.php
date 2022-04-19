<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Core\User\Preference\Preference;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Dt;

/**
 * Comments record helpers.
 *
 * \Dotclear\Core\RsExt\RsExtComment
 *
 * This class adds new methods to database comment results.
 * You can call them on every record comming from dcBlog::getComments and similar
 * methods.
 *
 * @warning You should not give the first argument (usualy $rs) of every described
 *
 * @ingroup  Core Comment Record
 */
class RsExtComment extends RsExtend
{
    /**
     * Returns comment date with <var>$format</var> as formatting pattern. If
     * format is empty, uses <var>date_format</var> blog setting.
     *
     * @param string $format The date format pattern
     * @param string $type   The type, (dt|upddt) defaults to comment_dt
     *
     * @return string the date
     */
    public function getDate(string $format, string $type = ''): string
    {
        if (!$format) {
            $format = dotclear()->blog()->settings()->get('system')->get('date_format');
        }

        return 'upddt' == $type ?
            Dt::dt2str($format, $this->rs->f('comment_upddt'), $this->rs->f('comment_tz')) :
            Dt::dt2str($format, $this->rs->f('comment_dt'));
    }

    /**
     * Returns comment time with <var>$format</var> as formatting pattern. If
     * format is empty, uses <var>time_format</var> blog setting.
     *
     * @param string $format The date format pattern
     * @param string $type   The type, (dt|upddt) defaults to comment_dt
     *
     * @return string the time
     */
    public function getTime(string $format, string $type = ''): string
    {
        if (!$format) {
            $format = dotclear()->blog()->settings()->get('system')->get('time_format');
        }

        return 'upddt' == $type ?
            Dt::dt2str($format, $this->rs->f('comment_updt'), $this->rs->f('comment_tz')) :
            Dt::dt2str($format, $this->rs->f('comment_dt'));
    }

    /**
     * Returns comment timestamp.
     *
     * @param string $type The type, (dt|upddt) defaults to comment_dt
     *
     * @return int the timestamp
     */
    public function getTS(string $type = ''): int
    {
        return 'upddt' == $type ?
            (int) strtotime($this->rs->f('comment_upddt')) :
            (int) strtotime($this->rs->f('comment_dt'));
    }

    /**
     * Returns comment date formating according to the ISO 8601 standard.
     *
     * @param string $type The type, (dt|upddt) defaults to comment_dt
     *
     * @return string the iso 8601 date
     */
    public function getISO8601Date(string $type = ''): string
    {
        return 'upddt' == $type ?
            Dt::iso8601($this->getTS($type) + Dt::getTimeOffset($this->rs->f('comment_tz')), $this->rs->f('comment_tz')) :
            Dt::iso8601($this->getTS(), $this->rs->f('comment_tz'));
    }

    /**
     * Returns comment date formating according to RFC 822.
     *
     * @param string $type The type, (dt|upddt) defaults to comment_dt
     *
     * @return string the rfc 822 date
     */
    public function getRFC822Date(string $type = ''): string
    {
        return 'upddt' == $type ?
            Dt::rfc822($this->getTS($type) + Dt::getTimeOffset($this->rs->f('comment_tz')), $this->rs->f('comment_tz')) :
            Dt::rfc822($this->getTS(), $this->rs->f('comment_tz'));
    }

    /**
     * Returns comment content. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param bool $absolute_urls With absolute URLs
     *
     * @return string the content
     */
    public function getContent(bool $absolute_urls = false): string
    {
        $res = $this->rs->f('comment_content');

        $res = dotclear()->blog()->settings()->get('system')->get('comments_nofollow') ?
            preg_replace_callback('#<a(.*?href=".*?".*?)>#ms', [$this, 'noFollowURL'], $res) :
            preg_replace_callback('#<a(.*?href=".*?".*?)>#ms', [$this, 'UgcURL'], $res);

        return $absolute_urls ? Html::absoluteURLs($res, $this->getPostURL()) : $res;
    }

    private function noFollowURL(array $m): string
    {
        return preg_match('/rel="ugc nofollow"/', $m[1]) ? $m[0] : '<a' . $m[1] . ' rel="ugc nofollow">';
    }

    private function UgcURL(array $m): string
    {
        return preg_match('/rel="ugc"/', $m[1]) ? $m[0] : '<a' . $m[1] . ' rel="ugc">';
    }

    /**
     * Returns comment author link to his website if he specified one.
     *
     * @return string the author url
     */
    public function getAuthorURL(): string
    {
        return trim((string) $this->rs->f('comment_site'));
    }

    /**
     * Returns comment post full URL.
     *
     * @return string the comment post url
     */
    public function getPostURL(): string
    {
        return dotclear()->blog()->url . dotclear()->posttype()->getPostPublicURL(
            $this->rs->f('post_type'),
            Html::sanitizeURL($this->rs->f('post_url'))
        );
    }

    /**
     * Returns comment author name in a link to his website if he specified one.
     *
     * @return string the author link
     */
    public function getAuthorLink(): string
    {
        $res = '%1$s';
        $url = $this->getAuthorURL();
        if ($url) {
            $res = '<a href="%2$s" rel="%3$s">%1$s</a>';
        }

        $rel = 'ugc';
        if (dotclear()->blog()->settings()->get('system')->get('comments_nofollow')) {
            $rel .= ' nofollow';
        }

        return sprintf($res, Html::escapeHTML($this->rs->f('comment_author')), Html::escapeHTML($url), $rel);
    }

    /**
     * Returns comment author e-mail address. If <var>$encoded</var> is true,
     * "@" sign is replaced by "%40" and "." by "%2e".
     *
     * @param bool $encoded Encode address
     *
     * @return string the email
     */
    public function getEmail(bool $encoded = true): string
    {
        return $encoded ? strtr($this->rs->f('comment_email'), ['@' => '%40', '.' => '%2e']) : $this->rs->f('comment_email');
    }

    /**
     * Returns trackback site title if comment is a trackback.
     *
     * @return string the trackback title
     */
    public function getTrackbackTitle(): string
    {
        return 1 == $this->rs->f('comment_trackback') && preg_match('|<p><strong>(.*?)</strong></p>|msU', $this->rs->f('comment_content'), $match) ?
            Html::decodeEntities($match[1]) : '';
    }

    /**
     * Returns trackback content if comment is a trackback.
     *
     * @return string the trackback content
     */
    public function getTrackbackContent(): string
    {
        return 1 == $this->rs->f('comment_trackback') ?
            preg_replace('|<p><strong>.*?</strong></p>|msU', '', $this->rs->f('comment_content')) : '';
    }

    /**
     * Returns comment feed unique ID.
     *
     * @return string the feed id
     */
    public function getFeedID(): string
    {
        return 'urn:md5:' . md5(dotclear()->blog()->uid . $this->rs->f('comment_id'));
    }

    /**
     * Determines whether the specified comment is from the post author.
     *
     * @return bool true if the specified comment is from the post author, False otherwise
     */
    public function isMe(): bool
    {
        $user_prefs         = new Preference($this->rs->f('user_id'), 'profile');
        $user_profile_mails = $user_prefs->get('profile')->get('mails') ?
            array_map('trim', explode(',', $user_prefs->get('profile')->get('mails'))) :
            [];
        $user_profile_urls = $user_prefs->get('profile')->get('urls') ?
            array_map('trim', explode(',', $user_prefs->get('profile')->get('urls'))) :
            [];

        return
            ($this->rs->f('comment_email') && $this->rs->f('comment_site'))
                                           && ($this->rs->f('comment_email') == $this->rs->f('user_email')  || in_array($this->rs->f('comment_email'), $user_profile_mails))
                                           && ($this->rs->f('comment_site')  == $this->rs->f('user_url')    || in_array($this->rs->f('comment_site'), $user_profile_urls));
    }
}
