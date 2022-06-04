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
use Dotclear\App;
use Dotclear\Core\Blog\Categories\Categories;
use Dotclear\Core\Blog\Comments\Comments;
use Dotclear\Core\Blog\Posts\Posts;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Database\Param;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Clock;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Mapper\Integers;

/**
 * Blog access methods.
 *
 * This class provides access to informations
 * related to a given Blog.
 *
 * @ingroup  Core Blog
 */
final class Blog
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
     * @var null|string $id
     *                  The blog id
     */
    public readonly ?string $id;

    /**
     * @var null|string $uid
     *                  The blog unique id
     */
    public readonly ?string $uid;

    /**
     * @var string $name
     *             The blog name
     */
    public readonly ?string $name;

    /**
     * @var string $desc
     *             The blog description
     */
    public readonly ?string $desc;

    /**
     * @var string $url
     *             The blog URL
     */
    public readonly ?string $url;

    /**
     * @var string $host
     *             The blog host
     */
    public readonly ?string $host;

    /**
     * @var int $creadt
     *          The blog creation date
     */
    public readonly ?int $creadt;

    /**
     * @var int $upddt
     *          The blog last update date
     */
    public readonly ?int $upddt;

    /**
     * @var int $status
     *          The blog status
     */
    public readonly ?int $status;

    /**
     * @var false|string $public_path
     *                   The blog public path
     */
    public readonly false|string $public_path;

    /**
     * @var string $public_url
     *             The blog public url
     */
    public readonly ?string $public_url;

    /**
     * @var bool $without_password
     *           Disallow entries password protection
     */
    public $without_password = true;

    /**
     * Constructor.
     *
     * Blog methods are accesible from App::core()->blog()
     *
     * @param string $id The blog identifier
     */
    public function __construct(string $id)
    {
        $param = new Param();
        $param->set('blog_id', $id);

        $record = App::core()->blogs()->getBlogs(param: $param);

        $this->id     = $record->isEmpty() ? null : $id;
        $this->uid    = $record->isEmpty() ? null : $record->f('blog_uid');
        $this->name   = $record->isEmpty() ? null : $record->f('blog_name');
        $this->desc   = $record->isEmpty() ? null : $record->f('blog_desc');
        $this->url    = $record->isEmpty() ? null : $record->f('blog_url');
        $this->host   = $record->isEmpty() ? null : Http::getHostFromURL($this->url);
        $this->creadt = $record->isEmpty() ? null : Clock::ts(date: $record->f('blog_creadt'));
        $this->upddt  = $record->isEmpty() ? null : Clock::ts(date: $record->f('blog_upddt'));
        $this->status = $record->isEmpty() ? null : (int) $record->f('blog_status');

        $this->public_url  = $record->isEmpty() ? null : $this->getURLFor('resources'); // ! to enhance;
        $this->public_path = $record->isEmpty() ? false : Path::real(Path::fullFromRoot($this->settings()->get('system')->get('public_path'), App::core()->config()->get('base_dir')));

        // --BEHAVIOR-- coreAfterConstructBlog, Blog
        App::core()->behavior()->call('coreAfterConstructBlog', blog: $this);
    }

    /**
     * // / @name Blog sub instances methods
     * // @{.
     * /**
     * Get categories instance.
     *
     * Categories methods are accesible from App::core()->blog()->categories()
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
     * Comments methods are accesible from App::core()->blog()->comments()
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
     * Posts methods are accesible from App::core()->blog()->posts()
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
     * Settings methods are accesible from App::core()->blog()->settings()
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
        $url = App::core()->url()->getURLFor($type, $value);

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
            $version = App::core()->config()->get('jquery_default');
        } else {
            if (true !== $this->settings()->get('system')->get('jquery_allow_old_version')) {
                // Use the blog defined version only if more recent than default
                if (version_compare($version, App::core()->config()->get('jquery_default'), '<')) {
                    $version = App::core()->config()->get('jquery_default');
                }
            }
        }

        return 'jquery/' . $version;
    }

    /**
     * Check entries password protection state.
     *
     * @return bool True if without password protection
     */
    public function isWithoutPassword(): bool
    {
        return $this->without_password;
    }

    /**
     * Disallow entries password protection.
     */
    public function setWithoutPassword()
    {
        $this->without_password = true;
    }

    /**
     * Allow entries password protection.
     */
    public function setWithPassword()
    {
        $this->without_password = false;
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
            'rfc822'  => Clock::rfc822(date: $this->upddt, to: App::core()->timezone()),
            'iso8601' => Clock::iso8601(date: $this->upddt, to: App::core()->timezone()),
            ''        => Clock::str(format: $format, date: $this->upddt, to: App::core()->timezone()),
            default   => Clock::database(date: $this->upddt, to: App::core()->timezone()),
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
        $sql = new UpdateStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'blog');
        $sql->set('blog_upddt = ' . $sql->quote(Clock::database()));
        $sql->where('blog_id = ' . $sql->quote($this->id));
        $sql->update();

        // --BEHAVIOR-- coreAfterTriggerBlog
        App::core()->behavior()->call('coreAfterTriggerBlog');
    }

    /**
     * Updates comments and trackbacks counters in post table.
     *
     * Should be called every time comments or trackbacks are
     * added, removed or changed their status.
     *
     * @param Integers $ids   The comments IDs
     * @param Integers $posts The affected posts IDs
     */
    public function triggerComments(Integers $ids, Integers $posts = null): void
    {
        // Get posts affected by comments edition
        if (null === $posts || !$posts->count()) {
            $sql = new SelectStatement(__METHOD__);
            $sql->from(App::core()->prefix() . 'comment ');
            $sql->where('comment_id' . $sql->in($ids->dump()));
            $sql->group('post_id');

            $posts  = new Integers();
            $record = $sql->select();
            while ($record->fetch()) {
                $posts->add($record->fInt('post_id'));
            }
        }

        if (!$posts->count()) {
            return;
        }

        // Count number of comments if exists for affected posts
        $sql = new SelectStatement(__METHOD__);
        $sql->columns([
            'post_id',
            $sql->count('post_id', 'nb_comment'),
            'comment_trackback',
        ]);
        $sql->from(App::core()->prefix() . 'comment ');
        $sql->where('comment_status = 1');
        $sql->and('post_id' . $sql->in($posts->dump()));
        $sql->group(['post_id', 'comment_trackback']);

        $nb     = [];
        $record = $sql->select();
        while ($record->fetch()) {
            $nb[$record->fInt('post_id')][$record->fInt('comment_trackback') ? 'trackback' : 'comment'] = $record->f('nb_comment');
        }

        // Update number of comments on affected posts
        foreach ($posts->dump() as $post_id) {
            $sql = new UpdateStatement(__METHOD__);
            $sql->from(App::core()->prefix() . 'post');
            $sql->where('post_id = ' . $post_id);

            if (array_key_exists($post_id, $nb)) {
                $sql->set('nb_trackback = ' . (array_key_exists('trackback', $nb[$post_id]) ? $nb[$post_id]['trackback'] : '0'));
                $sql->set('nb_comment = ' . (array_key_exists('comment', $nb[$post_id]) ? $nb[$post_id]['comment'] : '0'));
            } else {
                $sql->set('nb_trackback = 0');
                $sql->set('nb_comment = 0');
            }

            $sql->update();
        }
    }
    // @}
}
