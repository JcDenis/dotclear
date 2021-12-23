<?php
/**
 * @class Dotclear\Core\RsExt\rsExtPost
 * @brief Dotclear posts record helpers.
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
use Dotclear\Core\RsExt\RsExtStaticRecord;

use Dotclear\Html\Html;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class rsExtPost
{
    /**
     * Determines whether the specified post is editable.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool    True if the specified rs is editable, False otherwise.
     */
    public static function isEditable($rs)
    {
        # If user is admin or contentadmin, true
        if ($rs->core->auth->check('contentadmin', $rs->core->blog->id)) {
            return true;
        }

        # No user id in result ? false
        if (!$rs->exists('user_id')) {
            return false;
        }

        # If user is usage and owner of the entrie
        if ($rs->core->auth->check('usage', $rs->core->blog->id)
            && $rs->user_id == $rs->core->auth->userID()) {
            return true;
        }

        return false;
    }

    /**
     * Determines whether the specified post is deletable.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool    True if the specified rs is deletable, False otherwise.
     */
    public static function isDeletable($rs)
    {
        # If user is admin, or contentadmin, true
        if ($rs->core->auth->check('contentadmin', $rs->core->blog->id)) {
            return true;
        }

        # No user id in result ? false
        if (!$rs->exists('user_id')) {
            return false;
        }

        # If user has delete rights and is owner of the entrie
        if ($rs->core->auth->check('delete', $rs->core->blog->id)
            && $rs->user_id == $rs->core->auth->userID()) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether post is the first one of its day.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool
     */
    public static function firstPostOfDay($rs)
    {
        if ($rs->isStart()) {
            return true;
        }

        $cdate = date('Ymd', strtotime($rs->post_dt));
        $rs->movePrev();
        $ndate = date('Ymd', strtotime($rs->post_dt));
        $rs->moveNext();

        return $ndate != $cdate;
    }

    /**
     * Returns whether post is the last one of its day.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool
     */
    public static function lastPostOfDay($rs)
    {
        if ($rs->isEnd()) {
            return true;
        }

        $cdate = date('Ymd', strtotime($rs->post_dt));
        $rs->moveNext();
        $ndate = date('Ymd', strtotime($rs->post_dt));
        $rs->movePrev();

        return $ndate != $cdate;
    }

    /**
     * Returns whether comments are enabled on post.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool
     */
    public static function commentsActive($rs)
    {
        return
        $rs->core->blog->settings->system->allow_comments
            && $rs->post_open_comment
            && ($rs->core->blog->settings->system->comments_ttl == 0 || time() - ($rs->core->blog->settings->system->comments_ttl * 86400) < $rs->getTS());
    }

    /**
     * Returns whether trackbacks are enabled on post.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool
     */
    public static function trackbacksActive($rs)
    {
        return
        $rs->core->blog->settings->system->allow_trackbacks
            && $rs->post_open_tb
            && ($rs->core->blog->settings->system->trackbacks_ttl == 0 || time() - ($rs->core->blog->settings->system->trackbacks_ttl * 86400) < $rs->getTS());
    }

    /**
     * Returns whether post has at least one comment.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool
     */
    public static function hasComments($rs)
    {
        return $rs->nb_comment > 0;
    }

    /**
     * Returns whether post has at least one trackbacks.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool
     */
    public static function hasTrackbacks($rs)
    {
        return $rs->nb_trackback > 0;
    }

    /**
     * Returns whether post has been updated since publication.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool
     */
    public static function isRepublished($rs)
    {
        // Take care of post_dt does not store seconds
        return (($rs->getTS('upddt') + Dt::getTimeOffset($rs->post_tz, $rs->getTS('upddt'))) > ($rs->getTS() + 60));
    }

    /**
     * Gets the full post url.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     string  The url.
     */
    public static function getURL($rs)
    {
        return $rs->core->blog->url . $rs->core->getPostPublicURL(
            $rs->post_type, Html::sanitizeURL($rs->post_url)
        );
    }

    /**
     * Returns full post category URL.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     string  The category url.
     */
    public static function getCategoryURL($rs)
    {
        return $rs->core->blog->url . $rs->core->url->getURLFor('category', Html::sanitizeURL($rs->cat_url));
    }

    /**
     * Returns whether post has an excerpt.
     *
     * @param      record  $rs     Invisible parameter
     *
     * @return     bool
     */
    public static function isExtended($rs)
    {
        return $rs->post_excerpt_xhtml != '';
    }

    /**
     * Gets the post timestamp.
     *
     * @param      record  $rs     Invisible parameter
     * @param      string  $type   The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return     integer  The ts.
     */
    public static function getTS($rs, $type = '')
    {
        if ($type == 'upddt') {
            return strtotime($rs->post_upddt);
        } elseif ($type == 'creadt') {
            return strtotime($rs->post_creadt);
        }

        return strtotime($rs->post_dt);
    }

    /**
     * Returns post date formating according to the ISO 8601 standard.
     *
     * @param      record  $rs     Invisible parameter
     * @param      string  $type   The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return     string  The iso 8601 date.
     */
    public static function getISO8601Date($rs, $type = '')
    {
        if ($type == 'upddt' || $type == 'creadt') {
            return Dt::iso8601($rs->getTS($type) + Dt::getTimeOffset($rs->post_tz), $rs->post_tz);
        }

        return Dt::iso8601($rs->getTS(), $rs->post_tz);
    }

    /**
     * Returns post date formating according to RFC 822.
     *
     * @param      record  $rs     Invisible parameter
     * @param      string  $type   The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return     string  The rfc 822 date.
     */
    public static function getRFC822Date($rs, $type = '')
    {
        if ($type == 'upddt' || $type == 'creadt') {
            return Dt::rfc822($rs->getTS($type) + Dt::getTimeOffset($rs->post_tz), $rs->post_tz);
        }

        return Dt::rfc822($rs->getTS($type), $rs->post_tz);
    }

    /**
     * Returns post date with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>date_format</var> blog setting.
     *
     * @param      record  $rs      Invisible parameter
     * @param      string  $format  The date format pattern
     * @param      string  $type    The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return     string  The date.
     */
    public static function getDate($rs, $format, $type = '')
    {
        if (!$format) {
            $format = $rs->core->blog->settings->system->date_format;
        }

        if ($type == 'upddt') {
            return Dt::dt2str($format, $rs->post_upddt, $rs->post_tz);
        } elseif ($type == 'creadt') {
            return Dt::dt2str($format, $rs->post_creadt, $rs->post_tz);
        }

        return Dt::dt2str($format, $rs->post_dt);
    }

    /**
     * Returns post time with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>time_format</var> blog setting.
     *
     * @param      record  $rs      Invisible parameter
     * @param      string  $format  The time format pattern
     * @param      string  $type    The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return     string  The time.
     */
    public static function getTime($rs, $format, $type = '')
    {
        if (!$format) {
            $format = $rs->core->blog->settings->system->time_format;
        }

        if ($type == 'upddt') {
            return Dt::dt2str($format, $rs->post_upddt, $rs->post_tz);
        } elseif ($type == 'creadt') {
            return Dt::dt2str($format, $rs->post_creadt, $rs->post_tz);
        }

        return Dt::dt2str($format, $rs->post_dt);
    }

    /**
     * Returns author common name using user_id, user_name, user_firstname and
     * user_displayname fields.
     *
     * @param      record  $rs      Invisible parameter
     *
     * @return     string  The author common name.
     */
    public static function getAuthorCN($rs)
    {
        return Utils::getUserCN($rs->user_id, $rs->user_name,
            $rs->user_firstname, $rs->user_displayname);
    }

    /**
     * Returns author common name with a link if he specified one in its preferences.
     *
     * @param      record  $rs      Invisible parameter
     *
     * @return     string
     */
    public static function getAuthorLink($rs)
    {
        $res = '%1$s';
        $url = $rs->user_url;
        if ($url) {
            $res = '<a href="%2$s">%1$s</a>';
        }

        return sprintf($res, Html::escapeHTML($rs->getAuthorCN()), Html::escapeHTML($url));
    }

    /**
     * Returns author e-mail address. If <var>$encoded</var> is true, "@" sign is
     * replaced by "%40" and "." by "%2e".
     *
     * @param      record  $rs       Invisible parameter
     * @param      bool    $encoded  Encode address
     *
     * @return     string  The author email.
     */
    public static function getAuthorEmail($rs, $encoded = true)
    {
        if ($encoded) {
            return strtr($rs->user_email, ['@' => '%40', '.' => '%2e']);
        }

        return $rs->user_email;
    }

    /**
     * Gets the post feed unique id.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     string  The feed id.
     */
    public static function getFeedID($rs)
    {
        return 'urn:md5:' . md5($rs->core->blog->uid . $rs->post_id);
    }

    /**
     * Returns trackback RDF information block in HTML comment.
     *
     * @param      record  $rs       Invisible parameter
     * @param      string  $format   The format (html|xml)
     *
     * @return     string
     */
    public static function getTrackbackData($rs, $format = 'html')
    {
        return
        ($format == 'xml' ? "<![CDATA[>\n" : '') .
        "<!--\n" .
        '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . "\n" .
        '  xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n" .
        '  xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/">' . "\n" .
        "<rdf:Description\n" .
        '  rdf:about="' . $rs->getURL() . '"' . "\n" .
        '  dc:identifier="' . $rs->getURL() . '"' . "\n" .
        '  dc:title="' . htmlspecialchars($rs->post_title, ENT_COMPAT, 'UTF-8') . '"' . "\n" .
        '  trackback:ping="' . $rs->getTrackbackLink() . '" />' . "\n" .
            "</rdf:RDF>\n" .
            ($format == 'xml' ? '<!]]><!--' : '') .
            "-->\n";
    }

    /**
     * Gets the post trackback full URL.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     string  The trackback link.
     */
    public static function getTrackbackLink($rs)
    {
        return $rs->core->blog->url . $rs->core->url->getURLFor('trackback', $rs->post_id);
    }

    /**
     * Returns post content. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param      record  $rs              Invisible parameter
     * @param      bool    $absolute_urls   With absolute URLs
     *
     * @return     string  The content.
     */
    public static function getContent($rs, $absolute_urls = false)
    {
        if ($absolute_urls) {
            return Html::absoluteURLs($rs->post_content_xhtml, $rs->getURL());
        }

        return $rs->post_content_xhtml;
    }

    /**
     * Returns post excerpt. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param      record  $rs              Invisible parameter
     * @param      bool    $absolute_urls   With absolute URLs
     *
     * @return     string  The excerpt.
     */
    public static function getExcerpt($rs, $absolute_urls = false)
    {
        if ($absolute_urls) {
            return Html::absoluteURLs($rs->post_excerpt_xhtml, $rs->getURL());
        }

        return $rs->post_excerpt_xhtml;
    }

    /**
     * Returns post media count using a subquery.
     *
     * @param      record  $rs              Invisible parameter
     * @param      mixed   $link_type  The link type
     *
     * @return     integer Number of media.
     */
    public static function countMedia($rs, $link_type = null)
    {
        if (isset($rs->_nb_media[$rs->index()])) {
            return $rs->_nb_media[$rs->index()];
        }
        $strReq = 'SELECT count(media_id) ' .
            'FROM ' . $rs->core->prefix . 'post_media ' .
            'WHERE post_id = ' . (integer) $rs->post_id . ' ';
        if ($link_type != null) {
            $strReq .= "AND link_type = '" . $rs->core->con->escape($link_type) . "'";
        }

        $res                         = (integer) $rs->core->con->select($strReq)->f(0);
        $rs->_nb_media[$rs->index()] = $res;

        return $res;
    }

    /**
     * Returns true if current category if in given cat_url subtree
     *
     * @param      record   $rs       Invisible parameter
     * @param      string   $cat_url  The cat url
     *
     * @return     boolean  true if current cat is in given cat subtree
     */
    public static function underCat($rs, $cat_url)
    {
        return $rs->core->blog->IsInCatSubtree($rs->cat_url, $cat_url);
    }
}
