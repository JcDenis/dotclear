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

use Dotclear\Core\Utils;
use Dotclear\Core\Prefs;

use Dotclear\Html\Html;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class RsExtComment
{
    /**
     * Returns comment date with <var>$format</var> as formatting pattern. If
     * format is empty, uses <var>date_format</var> blog setting.
     *
     * @param      record  $rs      Invisible parameter
     * @param      string  $format  The date format pattern
     * @param      string  $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     string  The date.
     */
    public static function getDate($rs, $format, $type = '')
    {
        if (!$format) {
            $format = $rs->core->blog->settings->system->date_format;
        }

        if ($type == 'upddt') {
            return Dt::dt2str($format, $rs->comment_upddt, $rs->comment_tz);
        }

        return Dt::dt2str($format, $rs->comment_dt);
    }

    /**
     * Returns comment time with <var>$format</var> as formatting pattern. If
     * format is empty, uses <var>time_format</var> blog setting.
     *
     * @param      record  $rs      Invisible parameter
     * @param      string  $format  The date format pattern
     * @param      string  $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     string  The time.
     */
    public static function getTime($rs, $format, $type = '')
    {
        if (!$format) {
            $format = $rs->core->blog->settings->system->time_format;
        }

        if ($type == 'upddt') {
            return Dt::dt2str($format, $rs->comment_updt, $rs->comment_tz);
        }

        return Dt::dt2str($format, $rs->comment_dt);
    }

    /**
     * Returns comment timestamp.
     *
     * @param      record  $rs      Invisible parameter
     * @param      string  $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     integer The timestamp.
     */
    public static function getTS($rs, $type = '')
    {
        if ($type == 'upddt') {
            return strtotime($rs->comment_upddt);
        }

        return strtotime($rs->comment_dt);
    }

    /**
     * Returns comment date formating according to the ISO 8601 standard.
     *
     * @param      record  $rs      Invisible parameter
     * @param      string  $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     string  The iso 8601 date.
     */
    public static function getISO8601Date($rs, $type = '')
    {
        if ($type == 'upddt') {
            return Dt::iso8601($rs->getTS($type) + Dt::getTimeOffset($rs->comment_tz), $rs->comment_tz);
        }

        return Dt::iso8601($rs->getTS(), $rs->comment_tz);
    }

    /**
     * Returns comment date formating according to RFC 822.
     *
     * @param      record  $rs      Invisible parameter
     * @param      string  $type    The type, (dt|upddt) defaults to comment_dt
     *
     * @return     string  The rfc 822 date.
     */
    public static function getRFC822Date($rs, $type = '')
    {
        if ($type == 'upddt') {
            return Dt::rfc822($rs->getTS($type) + Dt::getTimeOffset($rs->comment_tz), $rs->comment_tz);
        }

        return Dt::rfc822($rs->getTS(), $rs->comment_tz);
    }

    /**
     * Returns comment content. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param      record  $rs              Invisible parameter
     * @param      bool    $absolute_urls   With absolute URLs
     *
     * @return     string  The content.
     */
    public static function getContent($rs, $absolute_urls = false)
    {
        $res = $rs->comment_content;

        if ($rs->core->blog->settings->system->comments_nofollow) {
            $res = preg_replace_callback('#<a(.*?href=".*?".*?)>#ms', ['self', 'noFollowURL'], $res);
        } else {
            $res = preg_replace_callback('#<a(.*?href=".*?".*?)>#ms', ['self', 'UgcURL'], $res);
        }

        if ($absolute_urls) {
            $res = Html::absoluteURLs($res, $rs->getPostURL());
        }

        return $res;
    }

    private static function noFollowURL($m)
    {
        if (preg_match('/rel="ugc nofollow"/', $m[1])) {
            return $m[0];
        }

        return '<a' . $m[1] . ' rel="ugc nofollow">';
    }

    private static function UgcURL($m)
    {
        if (preg_match('/rel="ugc"/', $m[1])) {
            return $m[0];
        }

        return '<a' . $m[1] . ' rel="ugc">';
    }

    /**
     * Returns comment author link to his website if he specified one.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     mixed  The author url.
     */
    public static function getAuthorURL($rs)
    {
        if (trim($rs->comment_site)) {
            return trim($rs->comment_site);
        }
    }

    /**
     * Returns comment post full URL.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     string  The comment post url.
     */
    public static function getPostURL($rs)
    {
        return $rs->core->blog->url . $rs->core->getPostPublicURL(
            $rs->post_type, Html::sanitizeURL($rs->post_url)
        );
    }

    /**
     * Returns comment author name in a link to his website if he specified one.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     string  The author link.
     */
    public static function getAuthorLink($rs)
    {
        $res = '%1$s';
        $url = $rs->getAuthorURL();
        if ($url) {
            $res = '<a href="%2$s" rel="%3$s">%1$s</a>';
        }

        $rel = 'ugc';
        if ($rs->core->blog->settings->system->comments_nofollow) {
            $rel .= ' nofollow';
        }

        return sprintf($res, Html::escapeHTML($rs->comment_author), Html::escapeHTML($url), $rel);
    }

    /**
     * Returns comment author e-mail address. If <var>$encoded</var> is true,
     * "@" sign is replaced by "%40" and "." by "%2e".
     *
     * @param      record  $rs       Invisible parameter
     * @param      bool    $encoded  Encode address
     *
     * @return     string  The email.
     */
    public static function getEmail($rs, $encoded = true)
    {
        return $encoded ? strtr($rs->comment_email, ['@' => '%40', '.' => '%2e']) : $rs->comment_email;
    }

    /**
     * Returns trackback site title if comment is a trackback.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     mixed  The trackback title.
     */
    public static function getTrackbackTitle($rs)
    {
        if ($rs->comment_trackback == 1 && preg_match('|<p><strong>(.*?)</strong></p>|msU', $rs->comment_content,
                $match)) {
            return Html::decodeEntities($match[1]);
        }
    }

    /**
     * Returns trackback content if comment is a trackback.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     mixed  The trackback content.
     */
    public static function getTrackbackContent($rs)
    {
        if ($rs->comment_trackback == 1) {
            return preg_replace('|<p><strong>.*?</strong></p>|msU', '',
                $rs->comment_content);
        }
    }

    /**
     * Returns comment feed unique ID.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     string  The feed id.
     */
    public static function getFeedID($rs)
    {
        return 'urn:md5:' . md5($rs->core->blog->uid . $rs->comment_id);
    }

    /**
     * Determines whether the specified comment is from the post author.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     bool    True if the specified comment is from the post author, False otherwise.
     */
    public static function isMe($rs)
    {
        $user_prefs = new Prefs($rs->core, $rs->user_id, 'profile');
        $user_prefs->addWorkspace('profile');
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
