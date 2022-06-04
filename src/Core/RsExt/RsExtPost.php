<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

// Dotclear\Core\RsExt\RsExtPost
use Dotclear\App;
use Dotclear\Core\User\UserContainer;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Clock;

/**
 * Posts record helpers.
 *
 * @ingroup  Core Post Record
 */
class RsExtPost extends RsExtend
{
    /**
     * @var array<int,int> $_nb_media
     *                     Number of attach media
     */
    public $_nb_media = [];

    /**
     * Determines whether the specified post is editable.
     *
     * @return bool true if the specified rs is editable, False otherwise
     */
    public function isEditable(): bool
    {
        // If user is admin or contentadmin, true
        if (App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            return true;
        }

        // No user id in result ? false
        if (!$this->rs->exists('user_id')) {
            return false;
        }

        // If user is usage and owner of the entrie
        if (App::core()->user()->check('usage', App::core()->blog()->id)
            && $this->rs->f('user_id') == App::core()->user()->userID()) {
            return true;
        }

        return false;
    }

    /**
     * Determines whether the specified post is deletable.
     *
     * @return bool true if the specified rs is deletable, False otherwise
     */
    public function isDeletable(): bool
    {
        // If user is admin, or contentadmin, true
        if (App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            return true;
        }

        // No user id in result ? false
        if (!$this->rs->exists('user_id')) {
            return false;
        }

        // If user has delete rights and is owner of the entrie
        if (App::core()->user()->check('delete', App::core()->blog()->id)
            && $this->rs->f('user_id') == App::core()->user()->userID()) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether post is the first one of its day.
     */
    public function firstPostOfDay(): bool
    {
        if ($this->rs->isStart()) {
            return true;
        }

        $cdate = Clock::format(format: 'Ymd', date: $this->rs->f('post_dt'), to: App::core()->timezone());
        $this->rs->movePrev();
        $ndate = Clock::format(format: 'Ymd', date: $this->rs->f('post_dt'), to: App::core()->timezone());
        $this->rs->moveNext();

        return $ndate != $cdate;
    }

    /**
     * Returns whether post is the last one of its day.
     */
    public function lastPostOfDay(): bool
    {
        if ($this->rs->isEnd()) {
            return true;
        }

        $cdate = Clock::format(format: 'Ymd', date: $this->rs->f('post_dt'), to: App::core()->timezone());
        $this->rs->moveNext();
        $ndate = Clock::format(format: 'Ymd', date: $this->rs->f('post_dt'), to: App::core()->timezone());
        $this->rs->movePrev();

        return $ndate != $cdate;
    }

    /**
     * Returns whether comments are enabled on post.
     */
    public function commentsActive(): bool
    {
        return
            App::core()->blog()->settings()->getGroup('system')->getSetting('allow_comments')
            && $this->rs->f('post_open_comment')
            && (
                0 == App::core()->blog()->settings()->getGroup('system')->getSetting('comments_ttl')
                || Clock::ts(to: App::core()->timezone()) - (App::core()->blog()->settings()->getGroup('system')->getSetting('comments_ttl') * 86400) < $this->getTS()
            );
    }

    /**
     * Returns whether trackbacks are enabled on post.
     */
    public function trackbacksActive(): bool
    {
        return
            App::core()->blog()->settings()->getGroup('system')->getSetting('allow_trackbacks')
            && $this->rs->f('post_open_tb')
            && (
                0 == App::core()->blog()->settings()->getGroup('system')->getSetting('trackbacks_ttl')
                || Clock::ts(to: App::core()->timezone()) - (App::core()->blog()->settings()->getGroup('system')->getSetting('trackbacks_ttl') * 86400) < $this->getTS()
            );
    }

    /**
     * Returns whether post has at least one comment.
     */
    public function hasComments(): bool
    {
        return 0 < $this->rs->f('nb_comment');
    }

    /**
     * Returns whether post has at least one trackbacks.
     */
    public function hasTrackbacks(): bool
    {
        return 0 < $this->rs->f('nb_trackback');
    }

    /**
     * Returns whether post has been updated since publication.
     */
    public function isRepublished(): bool
    {
        // Take care of post_dt does not store seconds
        return $this->getTS('upddt') > ($this->getTS() + 60);
    }

    /**
     * Gets the full post url.
     *
     * @return string the url
     */
    public function getURL(): string
    {
        return App::core()->blog()->url . App::core()->posttype()->getPostPublicURL(
            $this->rs->f('post_type'),
            Html::sanitizeURL($this->rs->f('post_url'))
        );
    }

    /**
     * Returns full post category URL.
     *
     * @return string the category url
     */
    public function getCategoryURL(): string
    {
        return App::core()->blog()->getURLFor('category', Html::sanitizeURL($this->rs->f('cat_url')));
    }

    /**
     * Returns whether post has an excerpt.
     */
    public function isExtended(): bool
    {
        return '' != $this->rs->f('post_excerpt_xhtml');
    }

    /**
     * Gets the post timestamp.
     *
     * @param string $type The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return int the ts
     */
    public function getTS(string $type = ''): int
    {
        $date = match ($type) {
            'upddt'  => $this->rs->f('post_upddt'),
            'creadt' => $this->rs->f('post_creadt'),
            default  => $this->rs->f('post_dt'),
        };

        return CLock::ts(
            date: $date,
            to: App::core()->timezone()
        );
    }

    /**
     * Returns post date formating according to the ISO 8601 standard.
     *
     * @param string $type The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return string the iso 8601 date
     */
    public function getISO8601Date(string $type = ''): string
    {
        return Clock::iso8601(
            date: $this->getTS($type),
            from: App::core()->timezone(),
            to: App::core()->timezone()
        );
    }

    /**
     * Returns post date formating according to RFC 822.
     *
     * @param string $type The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return string the rfc 822 date
     */
    public function getRFC822Date(string $type = ''): string
    {
        return Clock::rfc822(
            date: $this->getTS($type),
            from: App::core()->timezone(),
            to: App::core()->timezone()
        );
    }

    /**
     * Returns post date with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>date_format</var> blog setting.
     *
     * @param string $format The date format pattern
     * @param string $type   The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return string the date
     */
    public function getDate(string $format, string $type = ''): string
    {
        return Clock::str(
            format: ($format ?: App::core()->blog()->settings()->getGroup('system')->getSetting('date_format')),
            date: $this->getTS($type),
            from: App::core()->timezone(),
            to: App::core()->timezone()
        );
    }

    /**
     * Returns post time with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>time_format</var> blog setting.
     *
     * @param string $format The time format pattern
     * @param string $type   The type, (dt|upddt|creadt) defaults to post_dt
     *
     * @return string the time
     */
    public function getTime(string $format, string $type = ''): string
    {
        return Clock::str(
            format: ($format ?: App::core()->blog()->settings()->getGroup('system')->getSetting('time_format')),
            date: $this->getTS($type),
            from: App::core()->timezone(),
            to: App::core()->timezone()
        );
    }

    /**
     * Returns author common name using user_id, user_name, user_firstname and
     * user_displayname fields.
     *
     * @return string the author common name
     */
    public function getAuthorCN(): string
    {
        return UserContainer::getUserCN(
            $this->rs->f('user_id'),
            $this->rs->f('user_name'),
            $this->rs->f('user_firstname'),
            $this->rs->f('user_displayname')
        );
    }

    /**
     * Returns author common name with a link if he specified one in its preferences.
     */
    public function getAuthorLink(): string
    {
        $res = '%1$s';
        $url = $this->rs->f('user_url');
        if ($url) {
            $res = '<a href="%2$s">%1$s</a>';
        }

        return sprintf($res, Html::escapeHTML($this->getAuthorCN()), Html::escapeHTML($url));
    }

    /**
     * Returns author e-mail address. If <var>$encoded</var> is true, "@" sign is
     * replaced by "%40" and "." by "%2e".
     *
     * @param bool $encoded Encode address
     *
     * @return string the author email
     */
    public function getAuthorEmail(bool $encoded = true): string
    {
        return $encoded ? strtr($this->rs->f('user_email'), ['@' => '%40', '.' => '%2e']) : $this->rs->f('user_email');
    }

    /**
     * Gets the post feed unique id.
     *
     * @return string the feed id
     */
    public function getFeedID(): string
    {
        return 'urn:md5:' . md5(App::core()->blog()->uid . $this->rs->f('post_id'));
    }

    /**
     * Returns trackback RDF information block in HTML comment.
     *
     * @param string $format The format (html|xml)
     */
    public function getTrackbackData(string $format = 'html'): string
    {
        return
        ('xml' == $format ? "<![CDATA[>\n" : '') .
        "<!--\n" .
        '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . "\n" .
        '  xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n" .
        '  xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/">' . "\n" .
        "<rdf:Description\n" .
        '  rdf:about="' . $this->getURL() . '"' . "\n" .
        '  dc:identifier="' . $this->getURL() . '"' . "\n" .
        '  dc:title="' . htmlspecialchars($this->rs->f('post_title'), ENT_COMPAT, 'UTF-8') . '"' . "\n" .
        '  trackback:ping="' . $this->getTrackbackLink() . '" />' . "\n" .
            "</rdf:RDF>\n" .
            ('xml' == $format ? '<!]]><!--' : '') .
            "-->\n";
    }

    /**
     * Gets the post trackback full URL.
     *
     * @return string the trackback link
     */
    public function getTrackbackLink(): string
    {
        return App::core()->blog()->getURLFor('trackback', $this->rs->f('post_id'));
    }

    /**
     * Returns post content. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param bool $absolute_urls With absolute URLs
     *
     * @return string the content
     */
    public function getContent(bool $absolute_urls = false): string
    {
        return $absolute_urls ?
            Html::absoluteURLs($this->rs->f('post_content_xhtml'), $this->getURL()) :
            $this->rs->f('post_content_xhtml');
    }

    /**
     * Returns post excerpt. If <var>$absolute_urls</var> is true, appends full
     * blog URL to each relative post URLs.
     *
     * @param bool $absolute_urls With absolute URLs
     *
     * @return string the excerpt
     */
    public function getExcerpt(bool $absolute_urls = false): string
    {
        return $absolute_urls ?
            Html::absoluteURLs($this->rs->f('post_excerpt_xhtml'), $this->getURL()) :
            $this->rs->f('post_excerpt_xhtml');
    }

    /**
     * Returns post media count using a subquery.
     *
     * @param mixed $link_type The link type
     *
     * @return int number of media
     */
    public function countMedia(mixed $link_type = null): int
    {
        if (isset($this->_nb_media[$this->rs->index()])) {
            return $this->_nb_media[$this->rs->index()];
        }
        $strReq = 'SELECT count(media_id) ' .
            'FROM ' . App::core()->prefix() . 'post_media ' .
            'WHERE post_id = ' . (int) $this->rs->f('post_id') . ' ';
        if (null != $link_type) {
            $strReq .= "AND link_type = '" . App::core()->con()->escape($link_type) . "'";
        }

        $res                                 = App::core()->con()->select($strReq)->fInt();
        $this->_nb_media[$this->rs->index()] = $res;

        return $res;
    }

    /**
     * Returns true if current category if in given cat_url subtree.
     *
     * @param string $cat_url The cat url
     *
     * @return bool true if current cat is in given cat subtree
     */
    public function underCat(string $cat_url): bool
    {
        return App::core()->blog()->categories()->isInCatSubtree(url: $this->rs->f('cat_url'), parent: $cat_url);
    }
}
