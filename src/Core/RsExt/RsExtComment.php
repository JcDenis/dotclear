<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

// Dotclear\Core\RsExt\RsExtComment
use Dotclear\App;
use Dotclear\Core\User\Preference\Preference;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;

/**
 * Comments record helpers.
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
    public function getDate(string $format = '', string $type = ''): string
    {
        if (empty($format)) {
            $format = App::core()->blog()->settings()->getGroup('system')->getSetting('date_format');
        }

        return Clock::str(
            format: $format,
            date: ('upddt' == $type ? $this->rs->field('comment_upddt') : $this->rs->field('comment_dt')),
            to: App::core()->timezone()
        );
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
        if (empty($format)) {
            $format = App::core()->blog()->settings()->getGroup('system')->getSetting('time_format');
        }

        return Clock::str(
            format: $format,
            date: ('upddt' == $type ? $this->rs->field('comment_updt') : $this->rs->field('comment_dt')),
            to: App::core()->timezone()
        );
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
        return Clock::ts(
            date: ('upddt' == $type ? $this->rs->field('comment_upddt') : $this->rs->field('comment_dt')),
            to: App::core()->timezone()
        );
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
        return Clock::iso8601(
            date: $this->getTS('upddt' == $type ? $type : ''),
            from: App::core()->timezone(),
            to: App::core()->timezone()
        );
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
        return Clock::rfc822(
            date: $this->getTS('upddt' == $type ? $type : ''),
            from: App::core()->timezone(),
            to: App::core()->timezone()
        );
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
        $res = $this->rs->field('comment_content');

        $res = App::core()->blog()->settings()->getGroup('system')->getSetting('comments_nofollow') ?
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
        return trim((string) $this->rs->field('comment_site'));
    }

    /**
     * Returns comment post full URL.
     *
     * @return string the comment post url
     */
    public function getPostURL(): string
    {
        return App::core()->blog()->url . App::core()->posttype()->getPostPublicURL(
            type: $this->rs->field('post_type'),
            url: Html::sanitizeURL($this->rs->field('post_url'))
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
        if (App::core()->blog()->settings()->getGroup('system')->getSetting('comments_nofollow')) {
            $rel .= ' nofollow';
        }

        return sprintf($res, Html::escapeHTML($this->rs->field('comment_author')), Html::escapeHTML($url), $rel);
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
        return $encoded ? strtr($this->rs->field('comment_email'), ['@' => '%40', '.' => '%2e']) : $this->rs->field('comment_email');
    }

    /**
     * Returns trackback site title if comment is a trackback.
     *
     * @return string the trackback title
     */
    public function getTrackbackTitle(): string
    {
        return 1 == $this->rs->field('comment_trackback') && preg_match('|<p><strong>(.*?)</strong></p>|msU', $this->rs->field('comment_content'), $match) ?
            Html::decodeEntities($match[1]) : '';
    }

    /**
     * Returns trackback content if comment is a trackback.
     *
     * @return string the trackback content
     */
    public function getTrackbackContent(): string
    {
        return 1 == $this->rs->field('comment_trackback') ?
            preg_replace('|<p><strong>.*?</strong></p>|msU', '', $this->rs->field('comment_content')) : '';
    }

    /**
     * Returns comment feed unique ID.
     *
     * @return string the feed id
     */
    public function getFeedID(): string
    {
        return 'urn:md5:' . md5(App::core()->blog()->uid . $this->rs->field('comment_id'));
    }

    /**
     * Determines whether the specified comment is from the post author.
     *
     * @return bool true if the specified comment is from the post author, False otherwise
     */
    public function isMe(): bool
    {
        $user_prefs         = new Preference($this->rs->field('user_id'), 'profile');
        $user_profile_mails = $user_prefs->get('profile')->get('mails') ?
            array_map('trim', explode(',', $user_prefs->get('profile')->get('mails'))) :
            [];
        $user_profile_urls = $user_prefs->get('profile')->get('urls') ?
            array_map('trim', explode(',', $user_prefs->get('profile')->get('urls'))) :
            [];

        return $this->rs->field('comment_email')
            && $this->rs->field('comment_site')
            && ($this->rs->field('comment_email') == $this->rs->field('user_email')  || in_array($this->rs->field('comment_email'), $user_profile_mails))
            && ($this->rs->field('comment_site')  == $this->rs->field('user_url')    || in_array($this->rs->field('comment_site'), $user_profile_urls));
    }
}
