<?php
/**
 * @class Dotclear\Core\RsExt\RsExtPost
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

use Dotclear\Container\UserContainer;
use Dotclear\Core\RsExt\RsExtend;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Dt;

class RsExtPost extends RsExtend
{
    /**
     * Determines whether the specified post is editable.
     *
     * @return  bool    True if the specified rs is editable, False otherwise.
     */
    public function isEditable(): bool
    {
        # If user is admin or contentadmin, true
        if (dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            return true;
        }

        # No user id in result ? false
        if (!$this->rs->exists('user_id')) {
            return false;
        }

        # If user is usage and owner of the entrie
        if (dotclear()->user()->check('usage', dotclear()->blog()->id)
            && $this->rs->user_id == dotclear()->user()->userID()) {
            return true;
        }

        return false;
    }

    /**
     * Determines whether the specified post is deletable.
     *
     * @return  bool    True if the specified rs is deletable, False otherwise.
     */
    public function isDeletable(): bool
    {
        # If user is admin, or contentadmin, true
        if (dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            return true;
        }

        # No user id in result ? false
        if (!$this->rs->exists('user_id')) {
            return false;
        }

        # If user has delete rights and is owner of the entrie
        if (dotclear()->user()->check('delete', dotclear()->blog()->id)
            && $this->rs->user_id == dotclear()->user()->userID()) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether post is the first one of its day.
     *
     * @return  bool
     */
    public function firstPostOfDay(): bool
    {
        if ($this->rs->isStart()) {
            return true;
        }

        $cdate = date('Ymd', strtotime($this->rs->post_dt));
        $this->rs->movePrev();
        $ndate = date('Ymd', strtotime($this->rs->post_dt));
        $this->rs->moveNext();

        return $ndate != $cdate;
    }

    /**
     * Returns whether post is the last one of its day.
     *
     * @return  bool
     */
    public function lastPostOfDay(): bool
    {
        if ($this->rs->isEnd()) {
            return true;
        }

        $cdate = date('Ymd', strtotime($this->rs->post_dt));
        $this->rs->moveNext();
        $ndate = date('Ymd', strtotime($this->rs->post_dt));
        $this->rs->movePrev();

        return $ndate != $cdate;
    }

    /**
     * Returns whether comments are enabled on post.
     *
     * @return  bool
     */
    public function commentsActive(): bool
    {
        return
        dotclear()->blog()->settings()->system->allow_comments
            && $this->rs->post_open_comment
            && (dotclear()->blog()->settings()->system->comments_ttl == 0 || time() - (dotclear()->blog()->settings()->system->comments_ttl * 86400) < $this->getTS());
    }

    /**
     * Returns whether trackbacks are enabled on post.
     *
     * @return  bool
     */
    public function trackbacksActive(): bool
    {
        return
        dotclear()->blog()->settings()->system->allow_trackbacks
            && $this->rs->post_open_tb
            && (dotclear()->blog()->settings()->system->trackbacks_ttl == 0 || time() - (dotclear()->blog()->settings()->system->trackbacks_ttl * 86400) < $this->getTS());
    }

    /**
     * Returns whether post has at least one comment.
     *
     * @return  bool
     */
    public function hasComments(): bool
    {
        return $this->rs->nb_comment > 0;
    }

    /**
     * Returns whether post has at least one trackbacks.
     *
     * @return  bool
     */
    public function hasTrackbacks(): bool
    {
        return $this->rs->nb_trackback > 0;
    }

    /**
     * Returns whether post has been updated since publication.
     *
     * @return  bool
     */
    public function isRepublished(): bool
    {
        // Take care of post_dt does not store seconds
        return (($this->getTS('upddt') + Dt::getTimeOffset($this->rs->post_tz, $this->getTS('upddt'))) > ($this->getTS() + 60));
    }

    /**
     * Gets the full post url.
     *
     * @return  string  The url.
     */
    public function getURL(): string
    {
        return dotclear()->blog()->url . dotclear()->posttype()->getPostPublicURL(
            $this->rs->post_type, Html::sanitizeURL($this->rs->post_url)
        );
    }

    /**
     * Returns full post category URL.
     *
     * @return string   The category url.
     */
    public function getCategoryURL(): string
    {
        return dotclear()->blog()->getURLFor('category', Html::sanitizeURL($this->rs->cat_url));
    }

    /**
     * Returns whether post has an excerpt.
     *
     * @return  bool
     */
    public function isExtended(): bool
    {
        return $this->rs->post_excerpt_xhtml != '';
    }

    /**
     * Gets the post timestamp.
     *
     * @param   string  $type   The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return  int             The ts.
     */
    public function getTS(string $type = ''): int
    {
        if ($type == 'upddt') {
            return strtotime($this->rs->post_upddt);
        } elseif ($type == 'creadt') {
            return strtotime($this->rs->post_creadt);
        }

        return strtotime($this->rs->post_dt);
    }

    /**
     * Returns post date formating according to the ISO 8601 standard.
     *
     * @param   string  $type   The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return  string          The iso 8601 date.
     */
    public function getISO8601Date(string $type = ''): string
    {
        if ($type == 'upddt' || $type == 'creadt') {
            return Dt::iso8601($this->getTS($type) + Dt::getTimeOffset($this->rs->post_tz), $this->rs->post_tz);
        }

        return Dt::iso8601($this->getTS(), $this->rs->post_tz);
    }

    /**
     * Returns post date formating according to RFC 822.
     *
     * @param   string  $type   The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return  string          The rfc 822 date.
     */
    public function getRFC822Date(string $type = ''): string
    {
        if ($type == 'upddt' || $type == 'creadt') {
            return Dt::rfc822($this->getTS($type) + Dt::getTimeOffset($this->rs->post_tz), $this->rs->post_tz);
        }

        return Dt::rfc822($this->getTS($type), $this->rs->post_tz);
    }

    /**
     * Returns post date with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>date_format</var> blog setting.
     *
     * @param   string  $format     The date format pattern
     * @param   string  $type       The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return  string              The date.
     */
    public function getDate(string $format, string $type = ''): string
    {
        if (!$format) {
            $format = dotclear()->blog()->settings()->system->date_format;
        }

        if ($type == 'upddt') {
            return Dt::dt2str($format, $this->rs->post_upddt, $this->rs->post_tz);
        } elseif ($type == 'creadt') {
            return Dt::dt2str($format, $this->rs->post_creadt, $this->rs->post_tz);
        }

        return Dt::dt2str($format, $this->rs->post_dt);
    }

    /**
     * Returns post time with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>time_format</var> blog setting.
     *
     * @param   string  $format     The time format pattern
     * @param   string  $type       The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return  string              The time.
     */
    public function getTime(string $format, string $type = ''): string
    {
        if (!$format) {
            $format = dotclear()->blog()->settings()->system->time_format;
        }

        if ($type == 'upddt') {
            return Dt::dt2str($format, $this->rs->post_upddt, $this->rs->post_tz);
        } elseif ($type == 'creadt') {
            return Dt::dt2str($format, $this->rs->post_creadt, $this->rs->post_tz);
        }

        return Dt::dt2str($format, $this->rs->post_dt);
    }

    /**
     * Returns author common name using user_id, user_name, user_firstname and
     * user_displayname fields.
     *
     * @return  string  The author common name.
     */
    public function getAuthorCN(): string
    {
        return UserContainer::getUserCN(
            $this->rs->user_id,
            $this->rs->user_name,
            $this->rs->user_firstname,
            $this->rs->user_displayname
        );
    }

    /**
     * Returns author common name with a link if he specified one in its preferences.
     *
     * @return  string
     */
    public function getAuthorLink(): string
    {
        $res = '%1$s';
        $url = $this->rs->user_url;
        if ($url) {
            $res = '<a href="%2$s">%1$s</a>';
        }

        return sprintf($res, Html::escapeHTML($this->getAuthorCN()), Html::escapeHTML($url));
    }

    /**
     * Returns author e-mail address. If <var>$encoded</var> is true, "@" sign is
     * replaced by "%40" and "." by "%2e".
     *
     * @param   bool    $encoded    Encode address
     *
     * @return  string              The author email.
     */
    public function getAuthorEmail(bool $encoded = true): string
    {
        if ($encoded) {
            return strtr($this->rs->user_email, ['@' => '%40', '.' => '%2e']);
        }

        return $this->rs->user_email;
    }

    /**
     * Gets the post feed unique id.
     *
     * @return  string  The feed id.
     */
    public function getFeedID(): string
    {
        return 'urn:md5:' . md5(dotclear()->blog()->uid . $this->rs->post_id);
    }

    /**
     * Returns trackback RDF information block in HTML comment.
     *
     * @param   string  $format     The format (html|xml)
     *
     * @return  string
     */
    public function getTrackbackData(string $format = 'html'): string
    {
        return
        ($format == 'xml' ? "<![CDATA[>\n" : '') .
        "<!--\n" .
        '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . "\n" .
        '  xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n" .
        '  xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/">' . "\n" .
        "<rdf:Description\n" .
        '  rdf:about="' . $this->getURL() . '"' . "\n" .
        '  dc:identifier="' . $this->getURL() . '"' . "\n" .
        '  dc:title="' . htmlspecialchars($this->rs->post_title, ENT_COMPAT, 'UTF-8') . '"' . "\n" .
        '  trackback:ping="' . $this->getTrackbackLink() . '" />' . "\n" .
            "</rdf:RDF>\n" .
            ($format == 'xml' ? '<!]]><!--' : '') .
            "-->\n";
    }

    /**
     * Gets the post trackback full URL.
     *
     * @return  string  The trackback link.
     */
    public function getTrackbackLink(): string
    {
        return dotclear()->blog()->getURLFor('trackback', $this->rs->post_id);
    }

    /**
     * Returns post content. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param   bool    $absolute_urls  With absolute URLs
     *
     * @return  string                  The content.
     */
    public function getContent(bool $absolute_urls = false): string
    {
        if ($absolute_urls) {
            return Html::absoluteURLs($this->rs->post_content_xhtml, $this->getURL());
        }

        return $this->rs->post_content_xhtml;
    }

    /**
     * Returns post excerpt. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param   bool    $absolute_urls  With absolute URLs
     *
     * @return  string                  The excerpt.
     */
    public function getExcerpt(bool $absolute_urls = false): string
    {
        if ($absolute_urls) {
            return Html::absoluteURLs($this->rs->post_excerpt_xhtml, $this->getURL());
        }

        return $this->rs->post_excerpt_xhtml;
    }

    /**
     * Returns post media count using a subquery.
     *
     * @param   mixed   $link_type  The link type
     *
     * @return  int                 Number of media.
     */
    public function countMedia(mixed $link_type = null): int
    {
        if (isset($this->rs->_nb_media[$this->rs->index()])) {
            return $this->rs->_nb_media[$this->rs->index()];
        }
        $strReq = 'SELECT count(media_id) ' .
            'FROM ' . dotclear()->prefix . 'post_media ' .
            'WHERE post_id = ' . (int) $this->rs->post_id . ' ';
        if ($link_type != null) {
            $strReq .= "AND link_type = '" . dotclear()->con()->escape($link_type) . "'";
        }

        $res                               = dotclear()->con()->select($strReq)->fInt();
        $this->rs->_nb_media[$this->rs->index()] = $res;

        return $res;
    }

    /**
     * Returns true if current category if in given cat_url subtree
     *
     * @param   string      $cat_url    The cat url
     *
     * @return  bool                    true if current cat is in given cat subtree
     */
    public function underCat(string $cat_url): bool
    {
        return dotclear()->blog()->categories()->IsInCatSubtree($this->rs->cat_url, $cat_url);
    }
}
