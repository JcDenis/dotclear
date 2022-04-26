<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog;

// Dotclear\Core\Blog\Blog
use ArrayObject;
use Dotclear\Core\Blog\Categories\Categories;
use Dotclear\Core\Blog\Comments\Comments;
use Dotclear\Core\Blog\Posts\Posts;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Dt;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Network\Http;

/**
 * Blog access methods.
 *
 * This class provides access to informations
 * related to a given Blog.
 *
 * @ingroup  Core Blog
 */
class Blog
{
    /**
     * @var categories $categories
     *                 The blog categories instance
     */
    private $categories;

    /**
     * @var comments $comments
     *               The blog comments instance
     */
    private $comments;

    /**
     * @var posts $posts
     *            The blog posts instance
     */
    private $posts;

    /**
     * @var settings $settings
     *               The blog settings instance
     */
    private $settings;

    /**
     * @var array<int,string> $post_status
     *                        The post status list
     */
    private $post_status    = [];

    /**
     * @var array<int,string> $comment_status
     *                        The comment status list
     */
    private $comment_status = [];

    /**
     * @var string $id
     *             The blog id
     */
    public $id;

    /**
     * @var string $uid
     *             The blog unique id
     */
    public $uid;

    /**
     * @var string $name
     *             The blog name
     */
    public $name;

    /**
     * @var string $desc
     *             The blog description
     */
    public $desc;

    /**
     * @var string $url
     *             The blog URL
     */
    public $url;

    /**
     * @var string $host
     *             The blog host
     */
    public $host;

    /**
     * @var int $creadt
     *          The blog creation date
     */
    public $creadt;

    /**
     * @var int $upddt
     *          The blog last update date
     */
    public $upddt;

    /**
     * @var int $status
     *          The blog status
     */
    public $status;

    /**
     * @var false|string $public_path
     *                   The blog public path
     */
    public $public_path;

    /**
     * @var string $public_url
     *             The blog public url
     */
    public $public_url;

    /**
     * @var bool $without_password
     *           Disallow entries password protection
     */
    public $without_password = true;

    /**
     * Constructor.
     *
     * Blog methods are accesible from dotclear()->blog()
     *
     * @param string $id The blog identifier
     */
    public function __construct(string $id)
    {
        if (null !== ($rs = dotclear()->blogs()->getBlog($id))) {
            $this->id     = $id;
            $this->uid    = $rs->f('blog_uid');
            $this->name   = $rs->f('blog_name');
            $this->desc   = $rs->f('blog_desc');
            $this->url    = $rs->f('blog_url');
            $this->host   = Http::getHostFromURL($this->url);
            $this->creadt = (int) strtotime($rs->f('blog_creadt'));
            $this->upddt  = (int) strtotime($rs->f('blog_upddt'));
            $this->status = (int) $rs->f('blog_status');

            $this->public_path = Path::real(Path::fullFromRoot($this->settings()->get('system')->get('public_path'), dotclear()->config()->get('base_dir')));
            $this->public_url  = $this->getURLFor('resources'); // ! to enhance

            $this->post_status['-2'] = __('Pending');
            $this->post_status['-1'] = __('Scheduled');
            $this->post_status['0']  = __('Unpublished');
            $this->post_status['1']  = __('Published');

            $this->comment_status['-2'] = __('Junk');
            $this->comment_status['-1'] = __('Pending');
            $this->comment_status['0']  = __('Unpublished');
            $this->comment_status['1']  = __('Published');

            // --BEHAVIOR-- coreBlogConstruct, Dotclear\Core\Blog
            dotclear()->behavior()->call('coreBlogConstruct', $this);
        }
    }

    // / @name Blog sub instances methods
    // @{
    /**
     * Get categories instance.
     *
     * Categories methods are accesible from dotclear()->blog()->categories()
     *
     * @return Categories The categories instance
     */
    public function categories(): Categories
    {
        if (!($this->categories instanceof Categories)) {
            $this->categories = new Categories();
        }

        return $this->categories;
    }

    /**
     * Get comments instance.
     *
     * Comments methods are accesible from dotclear()->blog()->comments()
     *
     * @return Comments The comments instance
     */
    public function comments(): Comments
    {
        if (!($this->comments instanceof Comments)) {
            $this->comments = new Comments();
        }

        return $this->comments;
    }

    /**
     * Get posts instance.
     *
     * Posts methods are accesible from dotclear()->blog()->posts()
     *
     * @return Posts The posts instance
     */
    public function posts(): Posts
    {
        if (!($this->posts instanceof Posts)) {
            $this->posts = new Posts();
        }

        return $this->posts;
    }

    /**
     * Get settings instance.
     *
     * Settings methods are accesible from dotclear()->blog()->settings()
     *
     * @return Settings The settings instance
     */
    public function settings(): Settings
    {
        if (!($this->settings instanceof Settings)) {
            $this->settings = new Settings($this->id);
        }

        return $this->settings;
    }

    // @}

    // / @name Common public methods
    // @{
    /**
     * Returns blog URL ending with a question mark.
     *
     * @return string The qmark url
     */
    public function getQmarkURL(): string
    {
        return '?' != substr($this->url, -1) ? $this->url . '?' : $this->url;
    }

    /**
     * Returns URLs from URL handler with blog root URL.
     *
     * @param string     $type  The URL handler type
     * @param int|string $value The URL handler value
     *
     * @return string The URL
     */
    public function getURLFor(string $type, string|int $value = ''): string
    {
        $url = dotclear()->url()->getURLFor($type, $value);

        return str_contains($url, 'http') ? $url : $this->url . $url;
    }

    /**
     * Gets the jQuery version path.
     *
     * @return string The jQuery version path
     */
    public function getJsJQuery(): string
    {
        $version = $this->settings()->get('system')->get('jquery_version');
        if ('' == $version) {
            // Version not set, use default one
            $version = dotclear()->config()->get('jquery_default');
        } else {
            if (true !== $this->settings()->get('system')->get('jquery_allow_old_version')) {
                // Use the blog defined version only if more recent than default
                if (version_compare($version, dotclear()->config()->get('jquery_default'), '<')) {
                    $version = dotclear()->config()->get('jquery_default');
                }
            }
        }

        return 'jquery/' . $version;
    }

    /**
     * Returns an entry status name given to a code.
     *
     * Status are translated, never use it for tests.
     * If status code does not exist, returns <i>unpublished</i>.
     *
     * @param int $s The status code
     *
     * @return string The post status
     */
    public function getPostStatus(int $s): string
    {
        return $this->post_status[$s] ?? $this->post_status['0'];
    }

    /**
     * Returns an array of available entry status codes and names.
     *
     * @return array<int, string> Simple array with codes in keys and names in value
     */
    public function getAllPostStatus(): array
    {
        return $this->post_status;
    }

    /**
     * Returns an array of available comment status codes and names.
     *
     * @return array<int, string> Simple array with codes in keys and names in value
     */
    public function getAllCommentStatus(): array
    {
        return $this->comment_status;
    }

    /**
     * Disallows entries password protection.
     *
     * You need to set it to <var>false</var> while serving a public blog.
     * Set it to null to read current value.
     *
     * @param null|bool $v true to set password usage, null to read it
     */
    public function withoutPassword(?bool $v = null): bool
    {
        if (null !== $v) {
            $this->without_password = $v;
        }

        return $this->without_password;
    }

    /**
     * Get (non-)formatted blog update date.
     *
     * @param string $format The date format
     *
     * @return int|string The formatted update date
     */
    public function getUpdateDate(string $format = ''): int|string
    {
        return match ($format) {
            'rfc822'  => Dt::rfc822($this->upddt, $this->settings()->get('system')->get('blog_timezone')),
            'iso8601' => Dt::iso8601($this->upddt, $this->settings()->get('system')->get('blog_timezone')),
            ''        => Dt::str($format, $this->upddt),
            default   => $this->upddt,
        };
    }
    // @}

    // / @name Triggers methods
    // @{
    /**
     * Updates blog last update date.
     *
     * Should be called every time you change
     * an element related to the blog.
     */
    public function triggerBlog(): void
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'blog')
            ->setField('blog_upddt', date('Y-m-d H:i:s'))
        ;

        $sql = new UpdateStatement(__METHOD__);
        $sql->where('blog_id = ' . $sql->quote($this->id))
            ->update($cur)
        ;

        // --BEHAVIOR-- coreBlogAfterTriggerBlog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreBlogAfterTriggerBlog', $cur);
    }

    /**
     * Updates comment and trackback counters in post table.
     *
     * Should be called every time a comment or trackback
     * is added, removed or changed its status.
     *
     * @param int  $id  The comment identifier
     * @param bool $del If comment is deleted, set this to true
     */
    public function triggerComment(int $id, bool $del = false): void
    {
        $this->triggerComments([$id], $del);
    }

    /**
     * Updates comments and trackbacks counters in post table.
     *
     * Should be called every time comments or trackbacks are
     * added, removed or changed their status.
     *
     * @param array|ArrayObject   $ids            The identifiers
     * @param bool                $del            If comment is delete, set this to true
     * @param null|array<int,int> $affected_posts The affected posts IDs
     */
    public function triggerComments(array|ArrayObject $ids, bool $del = false, ?array $affected_posts = null): void
    {
        $comments_ids = $this->cleanIds($ids);

        // Get posts affected by comments edition
        if (empty($affected_posts)) {
            $sql = new SelectStatement(__METHOD__ . 'Id');
            $rs  = $sql
                ->from(dotclear()->prefix . 'comment ')
                ->where('comment_id' . $sql->in($comments_ids))
                ->group('post_id')
                ->select()
            ;

            $affected_posts = [];
            while ($rs->fetch()) {
                $affected_posts[] = $rs->fInt('post_id');
            }
        }

        if (empty($affected_posts)) {
            return;
        }

        // Count number of comments if exists for affected posts
        $sql = new SelectStatement(__METHOD__ . 'Count');
        $rs  = $sql
            ->columns([
                'post_id',
                $sql->count('post_id', 'nb_comment'),
                'comment_trackback',
            ])
            ->from(dotclear()->prefix . 'comment ')
            ->where('comment_status = 1')
            ->and('post_id' . $sql->in($affected_posts))
            ->group(['post_id', 'comment_trackback'])
            ->select()
        ;

        $posts = [];
        while ($rs->fetch()) {
            if ($rs->fInt('comment_trackback')) {
                $posts[$rs->fInt('post_id')]['trackback'] = $rs->f('nb_comment');
            } else {
                $posts[$rs->fInt('post_id')]['comment'] = $rs->f('nb_comment');
            }
        }

        // Update number of comments on affected posts
        foreach ($affected_posts as $post_id) {
            $sql = UpdateStatement::init(__METHOD__ . $post_id)
                ->from(dotclear()->prefix . 'post')
                ->where('post_id = ' . $post_id)
            ;

            if (array_key_exists($post_id, $posts)) {
                $sql->set('nb_trackback = ' . (array_key_exists('trackback', $posts[$post_id]) ? $posts[$post_id]['trackback'] : '0'));
                $sql->set('nb_comment = ' . (array_key_exists('comment', $posts[$post_id]) ? $posts[$post_id]['comment'] : '0'));
            } else {
                $sql->set('nb_trackback = 0');
                $sql->set('nb_comment = 0');
            }

            $sql->update();
        }
    }
    // @}

    // / @name Helper methods
    // @{
    /**
     * Cleanup a list of IDs.
     *
     * @param mixed $ids The identifiers
     *
     * @return array<int, int> The clean ids
     */
    public function cleanIds(mixed $ids): array
    {
        $clean_ids = [];

        if (!is_array($ids) && !($ids instanceof ArrayObject)) {
            $ids = [$ids];
        }

        foreach ($ids as $id) {
            if (is_array($id) || ($id instanceof ArrayObject)) {
                $clean_ids = array_merge($clean_ids, $this->cleanIds($id));
            } else {
                $id = abs((int) $id);

                if (!empty($id)) {
                    $clean_ids[] = $id;
                }
            }
        }

        return $clean_ids;
    }
    // @}
}
