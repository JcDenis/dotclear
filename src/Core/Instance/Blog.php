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

use Dotclear\Core\Utils;
use Dotclear\File\Path;
use Dotclear\Network\Http;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Blog
{
    use \Dotclear\Core\Instance\TraitComments;
    use \Dotclear\Core\Instance\TraitCategories;
    use \Dotclear\Core\Instance\TraitPosts;
    use \Dotclear\Core\Blog\Settings\TraitSettings;

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

            $this->settings($this->id);

            $this->public_path = Path::fullFromRoot($this->settings()->system->public_path, dotclear()->config()->base_dir);

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
        $version = $this->settings()->system->jquery_version;
        if ($version == '') {
            // Version not set, use default one
            $version = dotclear()->config()->jquery_default; // defined in inc/prepend.php
        } else {
            if (!$this->settings()->system->jquery_allow_old_version) {
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
    public function withoutPassword(?bool $v = null): bool
    {
        if (null !== $v) {
            $this->without_password = $v;
        }

        return $this->without_password;
    }

    public function getUpdateDate(string $format = ''): string
    {
        if ($format == 'rfc822') {
            return Dt::rfc822($this->upddt, $this->settings()->system->blog_timezone);
        } elseif ($format == 'iso8601') {
            return Dt::iso8601($this->upddt, $this->settings()->system->blog_timezone);
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
}
