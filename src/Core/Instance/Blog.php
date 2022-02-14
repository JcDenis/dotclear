<?php
/**
 * @class Dotclear\Core\Instance\Blog
 * @brief Dotclear core blog class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use ArrayObject;

use Dotclear\Exception\CoreException;
use Dotclear\Exception\DeprecatedException;

use Dotclear\Core\Settings;
use Dotclear\Core\Utils;

use Dotclear\Database\Record;
use Dotclear\Database\Cursor;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\File\Path;
use Dotclear\Utils\Dt;
use Dotclear\Utils\Text;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Blog
{
    use \Dotclear\Core\Instance\TraitCategories;
    use \Dotclear\Core\Instance\TraitPosts;

    /** @var Settings   Settings instance */
    public $settings;

    /** @var string     Blog ID */
    public $id;

    /** @var string     Blog unique ID */
    public $uid;

    /** @var string     Blog name */
    public $name;

    /** @var string     Blog description */
    public $desc;

    /** @var string     Blog URL */
    public $url;

    /** @var string     Blog host */
    public $host;

    /** @var int        Blog creation date */
    public $creadt;

    /** @var int        Blog last update date */
    public $upddt;

    /** @var string     Blog status */
    public $status;

    /** @var string     Blog public path */
    public $public_path;

    /** @var array      post status list */
    private $post_status    = [];

    /** @var array      comment status list */
    private $comment_status = [];

    /** @var bool       Disallow entries password protection */
    public $without_password = true;

    /**
     * Constructs a new instance.
     *
     * @param      string   $id     The blog identifier
     */
    public function __construct(string $id)
    {
        if (($b = dotclear()->blogs()->getBlog($id)) !== null) {
            $this->id     = $id;
            $this->uid    = $b->blog_uid;
            $this->name   = $b->blog_name;
            $this->desc   = $b->blog_desc;
            $this->url    = $b->blog_url;
            $this->host   = Http::getHostFromURL($this->url);
            $this->creadt = (int) strtotime($b->blog_creadt);
            $this->upddt  = (int) strtotime($b->blog_upddt);
            $this->status = (int) $b->blog_status;

            $this->settings = new Settings($this->id);

            $this->public_path = Path::fullFromRoot($this->settings->system->public_path, dotclear()->config()->base_dir);

            $this->post_status['-2'] = __('Pending');
            $this->post_status['-1'] = __('Scheduled');
            $this->post_status['0']  = __('Unpublished');
            $this->post_status['1']  = __('Published');

            $this->comment_status['-2'] = __('Junk');
            $this->comment_status['-1'] = __('Pending');
            $this->comment_status['0']  = __('Unpublished');
            $this->comment_status['1']  = __('Published');

            # --BEHAVIOR-- coreBlogConstruct, Dotclear\Core\Blog
            dotclear()->behavior()->call('coreBlogConstruct', $this);
        }
    }

    /// @name Common public methods
    //@{
    /**
     * Returns blog URL ending with a question mark.
     *
     * @return     string  The qmark url.
     */
    public function getQmarkURL(): string
    {
        if (substr($this->url, -1) != '?') {
            return $this->url . '?';
        }

        return $this->url;
    }

    /**
     * Gets the jQuery version.
     *
     * @return     string
     */
    public function getJsJQuery(): string
    {
        $version = $this->settings->system->jquery_version;
        if ($version == '') {
            // Version not set, use default one
            $version = dotclear()->config()->jquery_default; // defined in inc/prepend.php
        } else {
            if (!$this->settings->system->jquery_allow_old_version) {
                // Use the blog defined version only if more recent than default
                if (version_compare($version, dotclear()->config()->jquery_default, '<')) {
                    $version = dotclear()->config()->jquery_default; // defined in inc/prepend.php
                }
            }
        }

        return 'jquery/' . $version;
    }

    /**
     * Returns an entry status name given to a code. Status are translated, never
     * use it for tests. If status code does not exist, returns <i>unpublished</i>.
     *
     * @param      int  $s      The status code
     *
     * @return     string  The post status.
     */
    public function getPostStatus(int $s): string
    {
        if (isset($this->post_status[$s])) {
            return $this->post_status[$s];
        }

        return $this->post_status['0'];
    }

    /**
     * Returns an array of available entry status codes and names.
     *
     * @return     array  Simple array with codes in keys and names in value.
     */
    public function getAllPostStatus(): array
    {
        return $this->post_status;
    }

    /**
     * Returns an array of available comment status codes and names.
     *
     * @return    array Simple array with codes in keys and names in value
     */
    public function getAllCommentStatus(): array
    {
        return $this->comment_status;
    }

    /**
     * Disallows entries password protection. You need to set it to
     * <var>false</var> while serving a public blog.
     *
     * @param   bool|null   $v
     */
    public function withoutPassword(?bool $v): bool
    {
        if (null !== $v) {
            $this->without_password = $v;
        }

        return $this->without_password;
    }

    public function getUpdateDate(string $format = ''): string
    {
        if ($format == 'rfc822') {
            return Dt::rfc822($this->upddt, $this->settings->system->blog_timezone);
        } elseif ($format == 'iso8601') {
            return Dt::iso8601($this->upddt, $this->settings->system->blog_timezone);
        } elseif (!$format) {
            return Dt::str($format, $this->upddt);
        }

        return $this->upddt;
    }
    //@}

    /// @name Triggers methods
    //@{
    /**
     * Updates blog last update date. Should be called every time you change
     * an element related to the blog.
     */
    public function triggerBlog(): void
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'blog');

        $cur->blog_upddt = date('Y-m-d H:i:s');

        $cur->update("WHERE blog_id = '" . dotclear()->con()->escape($this->id) . "' ");

        # --BEHAVIOR-- coreBlogAfterTriggerBlog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreBlogAfterTriggerBlog', $cur);
    }

    /**
     * Updates comment and trackback counters in post table. Should be called
     * every time a comment or trackback is added, removed or changed its status.
     *
     * @param      int  $id     The comment identifier
     * @param      bool     $del    If comment is deleted, set this to true
     */
    public function triggerComment(int $id, bool $del = false): void
    {
        $this->triggerComments($id, $del);
    }

    /**
     * Updates comments and trackbacks counters in post table. Should be called
     * every time comments or trackbacks are added, removed or changed their status.
     *
     * @param      int|array|ArrayObject    $ids             The identifiers
     * @param      bool                     $del             If comment is delete, set this to true
     * @param      null|array               $affected_posts  The affected posts IDs
     */
    public function triggerComments($ids, bool $del = false, ?array $affected_posts = null): void
    {
        $comments_ids = Utils::cleanIds($ids);

        # Get posts affected by comments edition
        if (empty($affected_posts)) {
            $strReq = 'SELECT post_id ' .
            'FROM ' . dotclear()->prefix . 'comment ' .
            'WHERE comment_id' . dotclear()->con()->in($comments_ids) .
                'GROUP BY post_id';

            $rs = dotclear()->con()->select($strReq);

            $affected_posts = [];
            while ($rs->fetch()) {
                $affected_posts[] = (int) $rs->post_id;
            }
        }

        if (!is_array($affected_posts) || empty($affected_posts)) {
            return;
        }

        # Count number of comments if exists for affected posts
        $strReq = 'SELECT post_id, COUNT(post_id) AS nb_comment, comment_trackback ' .
        'FROM ' . dotclear()->prefix . 'comment ' .
        'WHERE comment_status = 1 ' .
        'AND post_id' . dotclear()->con()->in($affected_posts) .
            'GROUP BY post_id,comment_trackback';

        $rs = dotclear()->con()->select($strReq);

        $posts = [];
        while ($rs->fetch()) {
            if ($rs->comment_trackback) {
                $posts[$rs->post_id]['trackback'] = $rs->nb_comment;
            } else {
                $posts[$rs->post_id]['comment'] = $rs->nb_comment;
            }
        }

        # Update number of comments on affected posts
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
        foreach ($affected_posts as $post_id) {
            $cur->clean();

            if (!array_key_exists($post_id, $posts)) {
                $cur->nb_trackback = 0;
                $cur->nb_comment   = 0;
            } else {
                $cur->nb_trackback = empty($posts[$post_id]['trackback']) ? 0 : $posts[$post_id]['trackback'];
                $cur->nb_comment   = empty($posts[$post_id]['comment']) ? 0 : $posts[$post_id]['comment'];
            }

            $cur->update('WHERE post_id = ' . $post_id);
        }
    }
    //@}

    /// @name Comments management methods
    //@{
    /**
     * Retrieves comments. <b>$params</b> is an array taking the following
     * optionnal parameters:
     *
     * - no_content: Don't retrieve comment content
     * - post_type: Get only entries with given type (default no type, array for many types)
     * - post_id: (integer) Get comments belonging to given post_id
     * - cat_id: (integer or array) Get comments belonging to entries of given category ID
     * - comment_id: (integer or array) Get comment with given ID (or IDs)
     * - comment_site: (string) Get comments with given comment_site
     * - comment_status: (integer) Get comments with given comment_status
     * - comment_trackback: (integer) Get only comments (0) or trackbacks (1)
     * - comment_ip: (string) Get comments with given IP address
     * - post_url: Get entry with given post_url field
     * - user_id: (integer) Get entries belonging to given user ID
     * - q_author: Search comments by author
     * - sql: Append SQL string at the end of the query
     * - from: Append SQL string after "FROM" statement in query
     * - order: Order of results (default "ORDER BY comment_dt DES")
     * - limit: Limit parameter
     * - sql_only : return the sql request instead of results. Only ids are selected
     *
     * @since 3.0 remove sql_only param: reimplement something later
     *
     * @param    array      $params        Parameters
     * @param    bool       $count_only    Only counts results
     *
     * @return   Record      A record with some more capabilities
     */
    public function getComments(array $params = [], bool $count_only = false): Record
    {
        if ($count_only) {
            $strReq = 'SELECT count(comment_id) ';
        } elseif (!empty($params['sql_only'])) {
            DeprecatedException::throw();
            $strReq = 'SELECT P.post_id ';
        } else {
            if (!empty($params['no_content'])) {
                $content_req = '';
            } else {
                $content_req = 'comment_content, ';
            }

            if (!empty($params['columns']) && is_array($params['columns'])) {
                $content_req .= implode(', ', $params['columns']) . ', ';
            }

            $strReq = 'SELECT C.comment_id, comment_dt, comment_tz, comment_upddt, ' .
                'comment_author, comment_email, comment_site, ' .
                $content_req . ' comment_trackback, comment_status, ' .
                'comment_ip, ' .
                'P.post_title, P.post_url, P.post_id, P.post_password, P.post_type, ' .
                'P.post_dt, P.user_id, U.user_email, U.user_url ';
        }

        $strReq .= 'FROM ' . dotclear()->prefix . 'comment C ' .
        'INNER JOIN ' . dotclear()->prefix . 'post P ON C.post_id = P.post_id ' .
        'INNER JOIN ' . dotclear()->prefix . 'user U ON P.user_id = U.user_id ';

        if (!empty($params['from'])) {
            $strReq .= $params['from'] . ' ';
        }

        $strReq .= "WHERE P.blog_id = '" . dotclear()->con()->escape($this->id) . "' ";

        if (!dotclear()->auth()->check('contentadmin', $this->id)) {
            $strReq .= 'AND ((comment_status = 1 AND P.post_status = 1 ';

            if ($this->without_password) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (dotclear()->auth()->userID()) {
                $strReq .= "OR P.user_id = '" . dotclear()->con()->escape(dotclear()->auth()->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        if (!empty($params['post_type'])) {
            $strReq .= 'AND post_type ' . dotclear()->con()->in($params['post_type']);
        }

        if (isset($params['post_id']) && $params['post_id'] !== '') {
            $strReq .= 'AND P.post_id = ' . (int) $params['post_id'] . ' ';
        }

        if (isset($params['cat_id']) && $params['cat_id'] !== '') {
            $strReq .= 'AND P.cat_id = ' . (int) $params['cat_id'] . ' ';
        }

        if (isset($params['comment_id']) && $params['comment_id'] !== '') {
            if (is_array($params['comment_id'])) {
                array_walk($params['comment_id'], function (&$v, $k) { if ($v !== null) {$v = (int) $v;}});
            } else {
                $params['comment_id'] = [(int) $params['comment_id']];
            }
            $strReq .= 'AND comment_id ' . dotclear()->con()->in($params['comment_id']);
        }

        if (isset($params['comment_email'])) {
            $comment_email = dotclear()->con()->escape(str_replace('*', '%', $params['comment_email']));
            $strReq .= "AND comment_email LIKE '" . $comment_email . "' ";
        }

        if (isset($params['comment_site'])) {
            $comment_site = dotclear()->con()->escape(str_replace('*', '%', $params['comment_site']));
            $strReq .= "AND comment_site LIKE '" . $comment_site . "' ";
        }

        if (isset($params['comment_status'])) {
            $strReq .= 'AND comment_status = ' . (int) $params['comment_status'] . ' ';
        }

        if (!empty($params['comment_status_not'])) {
            $strReq .= 'AND comment_status <> ' . (int) $params['comment_status_not'] . ' ';
        }

        if (isset($params['comment_trackback'])) {
            $strReq .= 'AND comment_trackback = ' . (int) (bool) $params['comment_trackback'] . ' ';
        }

        if (isset($params['comment_ip'])) {
            $comment_ip = dotclear()->con()->escape(str_replace('*', '%', $params['comment_ip']));
            $strReq .= "AND comment_ip LIKE '" . $comment_ip . "' ";
        }

        if (isset($params['q_author'])) {
            $q_author = dotclear()->con()->escape(str_replace('*', '%', strtolower($params['q_author'])));
            $strReq .= "AND LOWER(comment_author) LIKE '" . $q_author . "' ";
        }

        if (!empty($params['search'])) {
            $words = Text::splitWords($params['search']);

            if (!empty($words)) {
                if (dotclear()->behavior()->has('coreCommentSearch')) {

                    # --BEHAVIOR coreCommentSearch, array
                    dotclear()->behavior()->call('coreCommentSearchs', [&$words, &$strReq, &$params]);
                }

                foreach ($words as $i => $w) {
                    $words[$i] = "comment_words LIKE '%" . dotclear()->con()->escape($w) . "%'";
                }
                $strReq .= 'AND ' . implode(' AND ', $words) . ' ';
            }
        }

        if (!empty($params['sql'])) {
            $strReq .= $params['sql'] . ' ';
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . dotclear()->con()->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY comment_dt DESC ';
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $strReq .= dotclear()->con()->limit($params['limit']);
        }

        $rs = dotclear()->con()->select($strReq);
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtComment');

        # --BEHAVIOR-- coreBlogGetComments, Dotclear\Database\Record
        dotclear()->behavior()->call('coreBlogGetComments', $rs);

        return $rs;
    }

    /**
     * Creates a new comment. Takes a cursor as input and returns the new comment ID.
     *
     * @param      Cursor  $cur    The comment cursor
     *
     * @return     int
     */
    public function addComment(Cursor $cur): int
    {
        dotclear()->con()->writeLock(dotclear()->prefix . 'comment');

        try {
            # Get ID
            $rs = dotclear()->con()->select(
                'SELECT MAX(comment_id) ' .
                'FROM ' . dotclear()->prefix . 'comment '
            );

            $cur->comment_id    = (int) $rs->f(0) + 1;
            $cur->comment_upddt = date('Y-m-d H:i:s');

            $offset          = Dt::getTimeOffset($this->settings->system->blog_timezone);
            $cur->comment_dt = date('Y-m-d H:i:s', time() + $offset);
            $cur->comment_tz = $this->settings->system->blog_timezone;

            $this->getCommentCursor($cur);

            if ($cur->comment_ip === null) {
                $cur->comment_ip = Http::realIP();
            }

            # --BEHAVIOR-- coreBeforeCommentCreate, Dotclear\Core\Blog, Dotclear\Database\Record
            dotclear()->behavior()->call('coreBeforeCommentCreate', $this, $cur);

            $cur->insert();
            dotclear()->con()->unlock();
        } catch (\Exception $e) {
            dotclear()->con()->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterCommentCreate, Dotclear\Core\Blog, Dotclear\Database\Record
        dotclear()->behavior()->call('coreAfterCommentCreate', $this, $cur);

        $this->triggerComment($cur->comment_id);
        if ($cur->comment_status != -2) {
            $this->triggerBlog();
        }

        return (int) $cur->comment_id;
    }

    /**
     * Updates an existing comment.
     *
     * @param      int      $id     The comment identifier
     * @param      Cursor   $cur    The comment cursor
     *
     * @throws     CoreException
     */
    public function updComment(int $id, Cursor $cur): void
    {
        if (!dotclear()->auth()->check('usage,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to update comments'));
        }

        $id = (int) $id;

        if (empty($id)) {
            throw new CoreException(__('No such comment ID'));
        }

        $rs = $this->getComments(['comment_id' => $id]);

        if ($rs->isEmpty()) {
            throw new CoreException(__('No such comment ID'));
        }

        #If user is only usage, we need to check the post's owner
        if (!dotclear()->auth()->check('contentadmin', $this->id)) {
            if ($rs->user_id != dotclear()->auth()->userID()) {
                throw new CoreException(__('You are not allowed to update this comment'));
            }
        }

        $this->getCommentCursor($cur);

        $cur->comment_upddt = date('Y-m-d H:i:s');

        if (!dotclear()->auth()->check('publish,contentadmin', $this->id)) {
            $cur->unsetField('comment_status');
        }

        # --BEHAVIOR-- coreBeforeCommentUpdate, Dotclear\Core\Blog, Dotclear\Database\Record
        dotclear()->behavior()->call('coreBeforeCommentUpdate', $this, $cur, $rs);

        $cur->update('WHERE comment_id = ' . $id . ' ');

        # --BEHAVIOR-- coreAfterCommentUpdate, Dotclear\Core\Blog, Dotclear\Database\Record
        dotclear()->behavior()->call('coreAfterCommentUpdate', $this, $cur, $rs);

        $this->triggerComment($id);
        $this->triggerBlog();
    }

    /**
     * Updates comment status.
     *
     * @param      int      $id      The comment identifier
     * @param      int      $status  The comment status
     */
    public function updCommentStatus(int $id, int $status): void
    {
        $this->updCommentsStatus($id, $status);
    }

    /**
     * Updates comments status.
     *
     * @param      int|array|ArrayObject    $ids     The identifiers
     * @param      int                      $status  The status
     *
     * @throws     CoreException
     */
    public function updCommentsStatus($ids, int $status): void
    {
        if (!dotclear()->auth()->check('publish,contentadmin', $this->id)) {
            throw new CoreException(__("You are not allowed to change this comment's status"));
        }

        $co_ids = Utils::cleanIds($ids);
        $status = (int) $status;

        $strReq = 'UPDATE ' . dotclear()->prefix . 'comment ' .
            'SET comment_status = ' . $status . ' ';
        $strReq .= 'WHERE comment_id' . dotclear()->con()->in($co_ids) .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . dotclear()->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . dotclear()->con()->escape($this->id) . "' ";
        if (!dotclear()->auth()->check('contentadmin', $this->id)) {
            $strReq .= "AND user_id = '" . dotclear()->con()->escape(dotclear()->auth()->userID()) . "' ";
        }
        $strReq .= ')';
        dotclear()->con()->execute($strReq);
        $this->triggerComments($co_ids);
        $this->triggerBlog();
    }

    /**
     * Delete a comment.
     *
     * @param      int  $id     The comment identifier
     */
    public function delComment(int $id): void
    {
        $this->delComments($id);
    }

    /**
     * Delete comments.
     *
     * @param      int|array|ArrayObject    $ids    The comments identifiers
     *
     * @throws     CoreException
     */
    public function delComments($ids): void
    {
        if (!dotclear()->auth()->check('delete,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $co_ids = Utils::cleanIds($ids);

        if (empty($co_ids)) {
            throw new CoreException(__('No such comment ID'));
        }

        # Retrieve posts affected by comments edition
        $affected_posts = [];
        $strReq         = 'SELECT post_id ' .
        'FROM ' . dotclear()->prefix . 'comment ' .
        'WHERE comment_id' . dotclear()->con()->in($co_ids) .
            'GROUP BY post_id';

        $rs = dotclear()->con()->select($strReq);

        while ($rs->fetch()) {
            $affected_posts[] = (int) $rs->post_id;
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'comment ' .
        'WHERE comment_id' . dotclear()->con()->in($co_ids) . ' ' .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . dotclear()->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . dotclear()->con()->escape($this->id) . "' ";
        #If user can only delete, we need to check the post's owner
        if (!dotclear()->auth()->check('contentadmin', $this->id)) {
            $strReq .= "AND tp.user_id = '" . dotclear()->con()->escape(dotclear()->auth()->userID()) . "' ";
        }
        $strReq .= ')';
        dotclear()->con()->execute($strReq);
        $this->triggerComments($co_ids, true, $affected_posts);
        $this->triggerBlog();
    }

    /**
     * Delete Junk comments
     *
     * @throws     CoreException  (description)
     */
    public function delJunkComments():void
    {
        if (!dotclear()->auth()->check('delete,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $strReq = 'DELETE FROM ' . dotclear()->prefix . 'comment ' .
        'WHERE comment_status = -2 ' .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . dotclear()->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . dotclear()->con()->escape($this->id) . "' ";
        #If user can only delete, we need to check the post's owner
        if (!dotclear()->auth()->check('contentadmin', $this->id)) {
            $strReq .= "AND tp.user_id = '" . dotclear()->con()->escape(dotclear()->auth()->userID()) . "' ";
        }
        $strReq .= ')';
        dotclear()->con()->execute($strReq);
        $this->triggerBlog();
    }

    /**
     * Gets the comment cursor.
     *
     * @param      Cursor     $cur    The comment cursor
     *
     * @throws     CoreException
     */
    private function getCommentCursor(Cursor $cur): void
    {
        if ($cur->comment_content !== null && $cur->comment_content == '') {
            throw new CoreException(__('You must provide a comment'));
        }

        if ($cur->comment_author !== null && $cur->comment_author == '') {
            throw new CoreException(__('You must provide an author name'));
        }

        if ($cur->comment_email != '' && !Text::isEmail($cur->comment_email)) {
            throw new CoreException(__('Email address is not valid.'));
        }

        if ($cur->comment_site !== null && $cur->comment_site != '') {
            if (!preg_match('|^http(s?)://|i', $cur->comment_site, $matches)) {
                $cur->comment_site = 'http://' . $cur->comment_site;
            } else {
                $cur->comment_site = strtolower($matches[0]) . substr($cur->comment_site, strlen($matches[0]));
            }
        }

        if ($cur->comment_status === null) {
            $cur->comment_status = (int) $this->settings->system->comments_pub;
        }

        # Words list
        if ($cur->comment_content !== null) {
            $cur->comment_words = implode(' ', Text::splitWords($cur->comment_content));
        }
    }
    //@}
}
