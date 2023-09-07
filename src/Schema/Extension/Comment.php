<?php
/**
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Schema\Extension;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;

/**
 * @brief Dotclear comment record helpers
 *
 * This class adds new methods to database comment results.
 * You can call them on every record comming from Blog::getComments and similar
 * methods.
 *
 * @warning You should not give the first argument (usualy $rs) of every described function.
 */
class Comment
{
    /**
     * Returns comment date with <var>$format</var> as formatting pattern. If
     * format is empty, uses <var>date_format</var> blog setting.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     * @param      string    $format  The date format pattern
     * @param      string    $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     string  The date.
     */
    public static function getDate(MetaRecord $rs, ?string $format, string $type = ''): string
    {
        if (!$format) {
            $format = App::blog()->settings()->system->date_format;
        }

        if ($type === 'upddt') {
            return Date::dt2str($format, (string) $rs->comment_upddt, (string) $rs->comment_tz);
        }

        return Date::dt2str($format, (string) $rs->comment_dt);
    }

    /**
     * Returns comment time with <var>$format</var> as formatting pattern. If
     * format is empty, uses <var>time_format</var> blog setting.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     * @param      string    $format  The date format pattern
     * @param      string    $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     string  The time.
     */
    public static function getTime(MetaRecord $rs, ?string $format, string $type = ''): string
    {
        if (!$format) {
            $format = App::blog()->settings()->system->time_format;
        }

        if ($type === 'upddt') {
            return Date::dt2str($format, (string) $rs->comment_updt, (string) $rs->comment_tz);
        }

        return Date::dt2str($format, (string) $rs->comment_dt);
    }

    /**
     * Returns comment timestamp.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     * @param      string    $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     integer The timestamp.
     */
    public static function getTS(MetaRecord $rs, string $type = ''): int
    {
        if ($type === 'upddt') {
            return strtotime((string) $rs->comment_upddt);
        }

        return strtotime((string) $rs->comment_dt);
    }

    /**
     * Returns comment date formating according to the ISO 8601 standard.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     * @param      string    $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     string  The iso 8601 date.
     */
    public static function getISO8601Date(MetaRecord $rs, string $type = ''): string
    {
        if ($type === 'upddt') {
            return Date::iso8601($rs->getTS($type) + Date::getTimeOffset((string) $rs->comment_tz), (string) $rs->comment_tz);
        }

        return Date::iso8601($rs->getTS(), (string) $rs->comment_tz);
    }

    /**
     * Returns comment date formating according to RFC 822.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     * @param      string    $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     string  The rfc 822 date.
     */
    public static function getRFC822Date(MetaRecord $rs, string $type = ''): string
    {
        if ($type === 'upddt') {
            return Date::rfc822($rs->getTS($type) + Date::getTimeOffset((string) $rs->comment_tz), (string) $rs->comment_tz);
        }

        return Date::rfc822($rs->getTS(), (string) $rs->comment_tz);
    }

    /**
     * Returns comment content. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param      MetaRecord   $rs             Invisible parameter
     * @param      bool|int     $absolute_urls  With absolute URLs
     *
     * @return     string  The content.
     */
    public static function getContent(MetaRecord $rs, $absolute_urls = false): string
    {
        $res = (string) $rs->comment_content;

        if (App::blog()->settings()->system->comments_nofollow) {
            $res = preg_replace_callback(
                '#<a(.*?href=".*?".*?)>#ms',
                function ($m) {
                    if (preg_match('/rel="ugc nofollow"/', $m[1])) {
                        return $m[0];
                    }

                    return '<a' . $m[1] . ' rel="ugc nofollow">';
                },
                $res
            );
        } else {
            $res = preg_replace_callback(
                '#<a(.*?href=".*?".*?)>#ms',
                function ($m) {
                    if (preg_match('/rel="ugc"/', $m[1])) {
                        return $m[0];
                    }

                    return '<a' . $m[1] . ' rel="ugc">';
                },
                $res
            );
        }

        if ($absolute_urls) {
            $res = Html::absoluteURLs($res, $rs->getPostURL());
        }

        return $res;
    }

    /**
     * Returns comment author link to his website if he specified one.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     *
     * @return     mixed  The author url.
     */
    public static function getAuthorURL(MetaRecord $rs)
    {
        if (trim((string) $rs->comment_site)) {
            return trim((string) $rs->comment_site);
        }
    }

    /**
     * Returns comment post full URL.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     *
     * @return     string  The comment post url.
     */
    public static function getPostURL(MetaRecord $rs): string
    {
        return App::blog()->url() . App::postTypes()->get($rs->post_type)->publicUrl(
            Html::sanitizeURL($rs->post_url)
        );
    }

    /**
     * Returns comment author name in a link to his website if he specified one.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     *
     * @return     string  The author link.
     */
    public static function getAuthorLink(MetaRecord $rs): string
    {
        $res = '%1$s';
        $url = $rs->getAuthorURL();
        if ($url) {
            $res = '<a href="%2$s" rel="%3$s">%1$s</a>';
        }

        $rel = 'ugc';
        if (App::blog()->settings()->system->comments_nofollow) {
            $rel .= ' nofollow';
        }

        return sprintf($res, Html::escapeHTML($rs->comment_author), Html::escapeHTML($url), $rel);
    }

    /**
     * Returns comment author e-mail address. If <var>$encoded</var> is true,
     * "@" sign is replaced by "%40" and "." by "%2e".
     *
     * @param      MetaRecord  $rs     Invisible parameter
     * @param      bool      $encoded  Encode address
     *
     * @return     string  The email.
     */
    public static function getEmail(MetaRecord $rs, bool $encoded = true): string
    {
        return $encoded ? strtr((string) $rs->comment_email, ['@' => '%40', '.' => '%2e']) : $rs->comment_email;
    }

    /**
     * Returns trackback site title if comment is a trackback.
     *
     * @param      MetaRecord  $rs       Invisible parameter
     *
     * @return     string  The trackback title.
     */
    public static function getTrackbackTitle(MetaRecord $rs): string
    {
        if ($rs->comment_trackback == 1 && preg_match(
            '|<p><strong>(.*?)</strong></p>|msU',
            (string) $rs->comment_content,
            $match
        )) {
            return Html::decodeEntities($match[1]);
        }

        return '';
    }

    /**
     * Returns trackback content if comment is a trackback.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     *
     * @return     string  The trackback content.
     */
    public static function getTrackbackContent(MetaRecord $rs): string
    {
        if ($rs->comment_trackback == 1) {
            return preg_replace(
                '|<p><strong>.*?</strong></p>|msU',
                '',
                (string) $rs->comment_content
            );
        }

        return '';
    }

    /**
     * Returns comment feed unique ID.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     *
     * @return     string  The feed id.
     */
    public static function getFeedID(MetaRecord $rs): string
    {
        return 'urn:md5:' . md5(App::blog()->uid() . $rs->comment_id);
    }

    /**
     * Determines whether the specified comment is from the post author.
     *
     * @param      MetaRecord  $rs     Invisible parameter
     *
     * @return     bool    True if the specified comment is from the post author, False otherwise.
     */
    public static function isMe(MetaRecord $rs): bool
    {
        $user_prefs         = App::userPreferences((string) $rs->user_id, 'profile');
        $user_profile_mails = $user_prefs->profile->mails ?
            array_map('trim', explode(',', $user_prefs->profile->mails)) :
            [];
        $user_profile_urls = $user_prefs->profile->urls ?
            array_map('trim', explode(',', $user_prefs->profile->urls)) :
            [];

        return
            ($rs->comment_email && $rs->comment_site) && ($rs->comment_email == $rs->user_email || in_array($rs->comment_email, $user_profile_mails)) && ($rs->comment_site == $rs->user_url || in_array($rs->comment_site, $user_profile_urls));
    }
}
