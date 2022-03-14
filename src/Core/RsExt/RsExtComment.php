<?php
/**
 * @class Dotclear\Core\RsExt\RsExtComment
 * @brief Dotclear comment record helpers.

 * This class adds new methods to database comment results.
 * You can call them on every record comming from dcBlog::getComments and similar
 * methods.

 * @warning You should not give the first argument (usualy $rs) of every described
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Core\RsExt\RsExtend;
use Dotclear\Core\User\Preference\Preference;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Dt;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class RsExtComment extends RsExtend
{
    /**
     * Returns comment date with <var>$format</var> as formatting pattern. If
     * format is empty, uses <var>date_format</var> blog setting.
     *
     * @param   string  $format     The date format pattern
     * @param   string  $type       The type, (dt|upddt) defaults to comment_dt
     *
     * @return  string              The date.
     */
    public function getDate(string $format, string $type = ''): string
    {
        if (!$format) {
            $format = dotclear()->blog()->settings()->system->date_format;
        }

        return $type == 'upddt' ?
            Dt::dt2str($format, $this->rs->comment_upddt, $this->rs->comment_tz) :
            Dt::dt2str($format, $this->rs->comment_dt);
    }

    /**
     * Returns comment time with <var>$format</var> as formatting pattern. If
     * format is empty, uses <var>time_format</var> blog setting.
     *
     * @param   string  $format     The date format pattern
     * @param   string  $type       The type, (dt|upddt) defaults to comment_dt
     *
     * @return  string              The time.
     */
    public function getTime(string $format, string $type = ''): string
    {
        if (!$format) {
            $format = dotclear()->blog()->settings()->system->time_format;
        }

        return $type == 'upddt' ?
            Dt::dt2str($format, $this->rs->comment_updt, $this->rs->comment_tz) :
            Dt::dt2str($format, $this->rs->comment_dt);
    }

    /**
     * Returns comment timestamp.
     *
     * @param   string  $type   The type, (dt|upddt) defaults to comment_dt
     *
     * @return  int             The timestamp.
     */
    public function getTS(string $type = ''): int
    {
        return $type == 'upddt' ?
            (int) strtotime($this->rs->comment_upddt) :
            (int) strtotime($this->rs->comment_dt);
    }

    /**
     * Returns comment date formating according to the ISO 8601 standard.
     *
     * @param   string  $type   The type, (dt|upddt) defaults to comment_dt
     *
     * @return  string          The iso 8601 date.
     */
    public function getISO8601Date(string $type = ''): string
    {
        return $type == 'upddt' ?
            Dt::iso8601($this->getTS($type) + Dt::getTimeOffset($this->rs->comment_tz), $this->rs->comment_tz) :
            Dt::iso8601($this->getTS(), $this->rs->comment_tz);
    }

    /**
     * Returns comment date formating according to RFC 822.
     *
     * @param   string  $type   The type, (dt|upddt) defaults to comment_dt
     *
     * @return  string          The rfc 822 date.
     */
    public function getRFC822Date(string $type = ''): string
    {
        return $type == 'upddt' ?
            Dt::rfc822($this->getTS($type) + Dt::getTimeOffset($this->rs->comment_tz), $this->rs->comment_tz) :
            Dt::rfc822($this->getTS(), $this->rs->comment_tz);
    }

    /**
     * Returns comment content. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param   bool    $absolute_urls  With absolute URLs
     *
     * @return  string                  The content.
     */
    public function getContent(bool $absolute_urls = false): string
    {
        $res = $this->rs->comment_content;

        $res = dotclear()->blog()->settings()->system->comments_nofollow ?
            preg_replace_callback('#<a(.*?href=".*?".*?)>#ms', [$this, 'noFollowURL'], $res) :
            preg_replace_callback('#<a(.*?href=".*?".*?)>#ms', [$this, 'UgcURL'], $res);

        return $absolute_urls ?
            Html::absoluteURLs($res, $this->getPostURL()) :
            $res;
    }

    private function noFollowURL(array $m): string
    {
        if (preg_match('/rel="ugc nofollow"/', $m[1])) {
            return $m[0];
        }

        return '<a' . $m[1] . ' rel="ugc nofollow">';
    }

    private function UgcURL(array $m): string
    {
        if (preg_match('/rel="ugc"/', $m[1])) {
            return $m[0];
        }

        return '<a' . $m[1] . ' rel="ugc">';
    }

    /**
     * Returns comment author link to his website if he specified one.
     *
     * @return  string          The author url.
     */
    public function getAuthorURL(): string
    {
        return trim((string) $this->rs->comment_site);
    }

    /**
     * Returns comment post full URL.
     *
     * @return  string          The comment post url.
     */
    public function getPostURL(): string
    {
        return dotclear()->blog()->url . dotclear()->posttype()->getPostPublicURL(
            $this->rs->post_type, Html::sanitizeURL($this->rs->post_url)
        );
    }

    /**
     * Returns comment author name in a link to his website if he specified one.
     *
     * @return  string          The author link.
     */
    public function getAuthorLink(): string
    {
        $res = '%1$s';
        $url = $this->getAuthorURL();
        if ($url) {
            $res = '<a href="%2$s" rel="%3$s">%1$s</a>';
        }

        $rel = 'ugc';
        if (dotclear()->blog()->settings()->system->comments_nofollow) {
            $rel .= ' nofollow';
        }

        return sprintf($res, Html::escapeHTML($this->rs->comment_author), Html::escapeHTML($url), $rel);
    }

    /**
     * Returns comment author e-mail address. If <var>$encoded</var> is true,
     * "@" sign is replaced by "%40" and "." by "%2e".
     *
     * @param   bool    $encoded    Encode address
     *
     * @return  string              The email.
     */
    public function getEmail(bool $encoded = true): string
    {
        return $encoded ? strtr($this->rs->comment_email, ['@' => '%40', '.' => '%2e']) : $this->rs->comment_email;
    }

    /**
     * Returns trackback site title if comment is a trackback.
     *
     * @return  string          The trackback title.
     */
    public function getTrackbackTitle(): string
    {
        if ($this->rs->comment_trackback == 1 && preg_match('|<p><strong>(.*?)</strong></p>|msU', $this->rs->comment_content, $match)) {
            return Html::decodeEntities($match[1]);
        }

        return '';
    }

    /**
     * Returns trackback content if comment is a trackback.
     *
     * @return  string          The trackback content.
     */
    public static function getTrackbackContent(): string
    {
        if ($this->rs->comment_trackback == 1) {
            return preg_replace('|<p><strong>.*?</strong></p>|msU', '',
                $this->rs->comment_content);
        }

        return '';
    }

    /**
     * Returns comment feed unique ID.
     *
     * @return  string          The feed id.
     */
    public function getFeedID(): string
    {
        return 'urn:md5:' . md5(dotclear()->blog()->uid . $this->rs->comment_id);
    }

    /**
     * Determines whether the specified comment is from the post author.
     *
     * @return  bool            True if the specified comment is from the post author, False otherwise.
     */
    public function isMe(): bool
    {
        $user_prefs = new Preference($this->rs->user_id, 'profile');
        $user_profile_mails = $user_prefs->profile->mails ?
            array_map('trim', explode(',', $user_prefs->profile->mails)) :
            [];
        $user_profile_urls = $user_prefs->profile->urls ?
            array_map('trim', explode(',', $user_prefs->profile->urls)) :
            [];

        return
            ($this->rs->comment_email && $this->rs->comment_site)
            && ($this->rs->comment_email == $this->rs->user_email || in_array($this->rs->comment_email, $user_profile_mails))
            && ($this->rs->comment_site == $this->rs->user_url || in_array($this->rs->comment_site, $user_profile_urls));
    }
}
