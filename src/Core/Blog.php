<?php
/**
 * @class Dotclear\Core\Blog
 * @brief Dotclear core blog class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use ArrayObject;

use Dotclear\Exception;
use Dotclear\Exception\CoreException;
use Dotclear\Exception\DeprecatedException;

use Dotclear\Core\Categories;
use Dotclear\Core\Settings;

use Dotclear\Database\Connection;
use Dotclear\Database\StaticRecord;
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
    /** @var Connection Connection instance */
    public $con;

    /** @var Settings   Settings instance */
    public $settings;

    /** @var string     Database table prefix */
    public $prefix;

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

    /** @var Categories Categories instance */
    private $categories;

    /** @var bool       Disallow entries password protection */
    public $without_password = true;

    /**
     * Constructs a new instance.
     *
     * @param      string   $id     The blog identifier
     */
    public function __construct(string $id)
    {
        $this->con    = dcCore()->con;
        $this->prefix = dcCore()->prefix;

        if (($b = dcCore()->getBlog($id)) !== null) {
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

            $this->public_path = Path::fullFromRoot($this->settings->system->public_path, DOTCLEAR_OTHER_DIR);

            $this->post_status['-2'] = __('Pending');
            $this->post_status['-1'] = __('Scheduled');
            $this->post_status['0']  = __('Unpublished');
            $this->post_status['1']  = __('Published');

            $this->comment_status['-2'] = __('Junk');
            $this->comment_status['-1'] = __('Pending');
            $this->comment_status['0']  = __('Unpublished');
            $this->comment_status['1']  = __('Published');

            # --BEHAVIOR-- coreBlogConstruct, Dotclear\Core\Blog
            dcCore()->behaviors->call('coreBlogConstruct', $this);
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
            $version = DOTCLEAR_JQUERY_DEFAULT; // defined in inc/prepend.php
        } else {
            if (!$this->settings->system->jquery_allow_old_version) {
                // Use the blog defined version only if more recent than default
                if (version_compare($version, DOTCLEAR_JQUERY_DEFAULT, '<')) {
                    $version = DOTCLEAR_JQUERY_DEFAULT; // defined in inc/prepend.php
                }
            }
        }

        return 'jquery/' . $version;
    }

    /**
     * Returns public URL of specified plugin file.
     *
     * @todo remove this
     *
     * @param      string  $pf          plugin file
     * @param      bool    $strip_host  Strip host in URL
     *
     * @return     string
     */
    public function getPF(string $pf, bool $strip_host = true): string
    {
        $ret = $this->getQmarkURL() . 'mf=Plugin/' . $pf;
        if ($strip_host) {
            $ret = Html::stripHostURL($ret);
        }

        return $ret;
    }

    /**
     * Returns public URL of specified var file.
     *
     * @param      string  $vf          var file
     * @param      bool    $strip_host  Strip host in URL
     *
     * @return     string
     */
    public function getVF(string $vf, bool $strip_host = true): string
    {
        $ret = $this->getQmarkURL() . 'vf=' . $vf;
        if ($strip_host) {
            $ret = Html::stripHostURL($ret);
        }

        return $ret;
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
     * @param      bool  $v
     */
    public function withoutPassword(bool $v): void
    {
        $this->without_password = $v;
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
        $cur = $this->con->openCursor($this->prefix . 'blog');

        $cur->blog_upddt = date('Y-m-d H:i:s');

        $cur->update("WHERE blog_id = '" . $this->con->escape($this->id) . "' ");

        # --BEHAVIOR-- coreBlogAfterTriggerBlog, Dotclear\Database\Cursor
        dcCore()->behaviors->call('coreBlogAfterTriggerBlog', $cur);
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
            'FROM ' . $this->prefix . 'comment ' .
            'WHERE comment_id' . $this->con->in($comments_ids) .
                'GROUP BY post_id';

            $rs = $this->con->select($strReq);

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
        'FROM ' . $this->prefix . 'comment ' .
        'WHERE comment_status = 1 ' .
        'AND post_id' . $this->con->in($affected_posts) .
            'GROUP BY post_id,comment_trackback';

        $rs = $this->con->select($strReq);

        $posts = [];
        while ($rs->fetch()) {
            if ($rs->comment_trackback) {
                $posts[$rs->post_id]['trackback'] = $rs->nb_comment;
            } else {
                $posts[$rs->post_id]['comment'] = $rs->nb_comment;
            }
        }

        # Update number of comments on affected posts
        $cur = $this->con->openCursor($this->prefix . 'post');
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

    /// @name Categories management methods
    //@{
    /**
     * Get Categories instance
     *
     * @return     Categories
     */
    public function categories(): Categories
    {
        if (!($this->categories instanceof Categories)) {
            $this->categories = new Categories();
        }

        return $this->categories;
    }

    /**
     * Retrieves categories. <var>$params</var> is an associative array which can
     * take the following parameters:
     *
     * - post_type: Get only entries with given type (default "post")
     * - cat_url: filter on cat_url field
     * - cat_id: filter on cat_id field
     * - start: start with a given category
     * - level: categories level to retrieve
     *
     * @param      ArrayObject|array   $params  The parameters
     *
     * @return     Record  The categories. (StaticRecord)
     */
    public function getCategories(ArrayObject|array $params = []): Record
    {
        $c_params = [];
        if (isset($params['post_type'])) {
            $c_params['post_type'] = $params['post_type'];
            unset($params['post_type']);
        }
        $counter = $this->getCategoriesCounter($c_params);

        if (isset($params['without_empty']) && ($params['without_empty'] == false)) {
            $without_empty = false;
        } else {
            $without_empty = dcCore()->auth->userID() == false; # Get all categories if in admin display
        }

        $start = isset($params['start']) ? (int) $params['start'] : 0;
        $l     = isset($params['level']) ? (int) $params['level'] : 0;

        $rs = $this->categories()->getChildren($start, null, 'desc');

        # Get each categories total posts count
        $data  = [];
        $stack = [];
        $level = 0;
        $cols  = $rs->columns();
        while ($rs->fetch()) {
            $nb_post = isset($counter[$rs->cat_id]) ? (int) $counter[$rs->cat_id] : 0;

            if ($rs->level > $level) {
                $nb_total          = $nb_post;
                $stack[$rs->level] = (int) $nb_post;
            } elseif ($rs->level == $level) {
                $nb_total = $nb_post;
                $stack[$rs->level] += $nb_post;
            } else {
                $nb_total = $stack[$rs->level + 1] + $nb_post;
                if (isset($stack[$rs->level])) {
                    $stack[$rs->level] += $nb_total;
                } else {
                    $stack[$rs->level] = $nb_total;
                }
                unset($stack[$rs->level + 1]);
            }

            if ($nb_total == 0 && $without_empty) {
                continue;
            }

            $level = $rs->level;

            $t = [];
            foreach ($cols as $c) {
                $t[$c] = $rs->f($c);
            }
            $t['nb_post']  = $nb_post;
            $t['nb_total'] = $nb_total;

            if ($l == 0 || ($l > 0 && $l == $rs->level)) {
                array_unshift($data, $t);
            }
        }

        # We need to apply filter after counting
        if (isset($params['cat_id']) && $params['cat_id'] !== '') {
            $found = false;
            foreach ($data as $v) {
                if ($v['cat_id'] == $params['cat_id']) {
                    $found = true;
                    $data  = [$v];

                    break;
                }
            }
            if (!$found) {
                $data = [];
            }
        }

        if (isset($params['cat_url']) && ($params['cat_url'] !== '')
            && !isset($params['cat_id'])) {
            $found = false;
            foreach ($data as $v) {
                if ($v['cat_url'] == $params['cat_url']) {
                    $found = true;
                    $data  = [$v];

                    break;
                }
            }
            if (!$found) {
                $data = [];
            }
        }

        return StaticRecord::newFromArray($data);
    }

    /**
     * Gets the category by its ID.
     *
     * @param      int  $id     The category identifier
     *
     * @return     Record  The category. (StaticRecord)
     */
    public function getCategory(int $id): Record
    {
        return $this->getCategories(['cat_id' => $id]);
    }

    /**
     * Gets the category parents.
     *
     * @param      int  $id     The category identifier
     *
     * @return     Record  The category parents. (StaticRecord)
     */
    public function getCategoryParents(int $id): Record
    {
        return $this->categories()->getParents($id);
    }

    /**
     * Gets the category first parent.
     *
     * @param      int  $id     The category identifier
     *
     * @return     Record  The category parent. (StaticRecord)
     */
    public function getCategoryParent(int $id): Record
    {
        return $this->categories()->getParent($id);
    }

    /**
     * Gets all category's first children.
     *
     * @param      int     $id     The category identifier
     *
     * @return     Record  The category first children. (StaticRecord)
     */
    public function getCategoryFirstChildren(int $id): Record
    {
        return $this->getCategories(['start' => $id, 'level' => $id == 0 ? 1 : 2]);
    }

    /**
     * Returns true if a given category if in a given category's subtree
     *
     * @param      string   $cat_url    The cat url
     * @param      string   $start_url  The top cat url
     *
     * @return     bool     true if cat_url is in given start_url cat subtree
     */
    public function IsInCatSubtree(string $cat_url, string $start_url): bool
    {
        // Get cat_id from start_url
        $cat = $this->getCategories(['cat_url' => $start_url]);
        if ($cat->fetch()) {
            // cat_id found, get cat tree list
            $cats = $this->getCategories(['start' => $cat->cat_id]);
            while ($cats->fetch()) {
                // check if post category is one of the cat or sub-cats
                if ($cats->cat_url === $cat_url) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gets the categories posts counter.
     *
     * @param      array  $params  The parameters
     *
     * @return     array  The categories counter.
     */
    private function getCategoriesCounter(array $params = []): array
    {
        $strReq = 'SELECT  C.cat_id, COUNT(P.post_id) AS nb_post ' .
        'FROM ' . $this->prefix . 'category AS C ' .
        'JOIN ' . $this->prefix . "post P ON (C.cat_id = P.cat_id AND P.blog_id = '" . $this->con->escape($this->id) . "' ) " .
        "WHERE C.blog_id = '" . $this->con->escape($this->id) . "' ";

        if (!dcCore()->auth->userID()) {
            $strReq .= 'AND P.post_status = 1 ';
        }

        if (!empty($params['post_type'])) {
            $strReq .= 'AND P.post_type ' . $this->con->in($params['post_type']);
        }

        $strReq .= 'GROUP BY C.cat_id ';

        $rs       = $this->con->select($strReq);
        $counters = [];
        while ($rs->fetch()) {
            $counters[$rs->cat_id] = $rs->nb_post;
        }

        return $counters;
    }

    /**
     * Adds a new category. Takes a cursor as input and returns the new category ID.
     *
     * @param      Cursor        $cur     The category cursor
     * @param      int           $parent  The parent category ID
     *
     * @throws     CoreException
     *
     * @return     int  New category ID
     */
    public function addCategory(Cursor $cur, int $parent = 0): int
    {
        if (!dcCore()->auth->check('categories', $this->id)) {
            throw new CoreException(__('You are not allowed to add categories'));
        }

        $url = [];
        if ($parent != 0) {
            $rs = $this->getCategory($parent);
            if ($rs->isEmpty()) {
                $url = [];
            } else {
                $url[] = $rs->cat_url;
            }
        }

        if ($cur->cat_url == '') {
            $url[] = Text::tidyURL($cur->cat_title, false);
        } else {
            $url[] = $cur->cat_url;
        }

        $cur->cat_url = implode('/', $url);

        $this->getCategoryCursor($cur);
        $cur->blog_id = (string) $this->id;

        # --BEHAVIOR-- coreBeforeCategoryCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dcCore()->behaviors->call('coreBeforeCategoryCreate', $this, $cur);

        $id = $this->categories()->addNode($cur, $parent);
        if ($id !== null) {
            # Update category's cursor
            $rs = $this->getCategory($id);
            if (!$rs->isEmpty()) {
                $cur->cat_lft = $rs->cat_lft;
                $cur->cat_rgt = $rs->cat_rgt;
            }
        }

        # --BEHAVIOR-- coreAfterCategoryCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dcCore()->behaviors->call('coreAfterCategoryCreate', $this, $cur);

        $this->triggerBlog();

        return (int) $cur->cat_id;
    }

    /**
     * Updates an existing category.
     *
     * @param      int     $id     The category ID
     * @param      Cursor      $cur    The category cursor
     *
     * @throws     CoreException
     */
    public function updCategory(int $id, Cursor $cur): void
    {
        if (!dcCore()->auth->check('categories', $this->id)) {
            throw new CoreException(__('You are not allowed to update categories'));
        }

        if ($cur->cat_url == '') {
            $url = [];
            $rs  = $this->categories()->getParents($id);
            while ($rs->fetch()) {
                if ($rs->index() == $rs->count() - 1) {
                    $url[] = $rs->cat_url;
                }
            }

            $url[]        = Text::tidyURL($cur->cat_title, false);
            $cur->cat_url = implode('/', $url);
        }

        $this->getCategoryCursor($cur, $id);

        # --BEHAVIOR-- coreBeforeCategoryUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dcCore()->behaviors->call('coreBeforeCategoryUpdate', $this, $cur);

        $cur->update(
            'WHERE cat_id = ' . (int) $id . ' ' .
            "AND blog_id = '" . $this->con->escape($this->id) . "' "
        );

        # --BEHAVIOR-- coreAfterCategoryUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dcCore()->behaviors->call('coreAfterCategoryUpdate', $this, $cur);

        $this->triggerBlog();
    }

    /**
     * Set category position.
     *
     * @param      int  $id     The category ID
     * @param      int  $left   The category ID before
     * @param      int  $right  The category ID after
     */
    public function updCategoryPosition(int $id, int $left, int $right): void
    {
        $this->categories()->updatePosition($id, $left, $right);
        $this->triggerBlog();
    }

    /**
     * Sets the category parent.
     *
     * @param      int  $id      The category ID
     * @param      int  $parent  The parent category ID
     */
    public function setCategoryParent(int $id, int $parent): void
    {
        $this->categories()->setNodeParent($id, $parent);
        $this->triggerBlog();
    }

    /**
     * Sets the category position.
     *
     * @param      int      $id       The category ID
     * @param      int      $sibling  The sibling category ID
     * @param      string   $move     The move (before|after)
     */
    public function setCategoryPosition(int $id, int $sibling, string $move): void
    {
        $this->categories()->setNodePosition($id, $sibling, $move);
        $this->triggerBlog();
    }

    /**
     * Delete a category.
     *
     * @param      int     $id     The category ID
     *
     * @throws     CoreException
     */
    public function delCategory(int $id): void
    {
        if (!dcCore()->auth->check('categories', $this->id)) {
            throw new CoreException(__('You are not allowed to delete categories'));
        }

        $strReq = 'SELECT COUNT(post_id) AS nb_post ' .
        'FROM ' . $this->prefix . 'post ' .
        'WHERE cat_id = ' . (int) $id . ' ' .
        "AND blog_id = '" . $this->con->escape($this->id) . "' ";

        $rs = $this->con->select($strReq);

        if ($rs->nb_post > 0) {
            throw new CoreException(__('This category is not empty.'));
        }

        $this->categories()->deleteNode($id, true);
        $this->triggerBlog();
    }

    /**
     * Reset categories order and relocate them to first level
     */
    public function resetCategoriesOrder(): void
    {
        if (!dcCore()->auth->check('categories', $this->id)) {
            throw new CoreException(__('You are not allowed to reset categories order'));
        }

        $this->categories()->resetOrder();
        $this->triggerBlog();
    }

    /**
     * Check if the category title and url are unique.
     *
     * @param      string       $title  The title
     * @param      string       $url    The url
     * @param      null|int     $id     The identifier
     *
     * @return     string
     */
    private function checkCategory(string $title, string $url, ?int $id = null): string
    {
        # Let's check if URL is taken...
        $strReq = 'SELECT cat_url FROM ' . $this->prefix . 'category ' .
        "WHERE cat_url = '" . $this->con->escape($url) . "' " .
        ($id ? 'AND cat_id <> ' . (int) $id . ' ' : '') .
        "AND blog_id = '" . $this->con->escape($this->id) . "' " .
            'ORDER BY cat_url DESC';

        $rs = $this->con->select($strReq);

        if (!$rs->isEmpty()) {
            if ($this->con->syntax() == 'mysql') {
                $clause = "REGEXP '^" . $this->con->escape($url) . "[0-9]+$'";
            } elseif ($this->con->driver() == 'pgsql') {
                $clause = "~ '^" . $this->con->escape($url) . "[0-9]+$'";
            } else {
                $clause = "LIKE '" . $this->con->escape($url) . "%'";
            }
            $strReq = 'SELECT cat_url FROM ' . $this->prefix . 'category ' .
            'WHERE cat_url ' . $clause . ' ' .
            ($id ? 'AND cat_id <> ' . (int) $id . ' ' : '') .
            "AND blog_id = '" . $this->con->escape($this->id) . "' " .
                'ORDER BY cat_url DESC ';

            $rs = $this->con->select($strReq);

            if ($rs->isEmpty()) {
                return $url;
            }

            $a  = [];
            while ($rs->fetch()) {
                $a[] = $rs->cat_url;
            }

            natsort($a);
            $t_url = end($a);

            if (preg_match('/(.*?)([0-9]+)$/', $t_url, $m)) {
                $i   = (int) $m[2];
                $url = $m[1];
            } else {
                $i = 1;
            }

            return $url . ($i + 1);
        }

        # URL is empty?
        if ($url == '') {
            throw new CoreException(__('Empty category URL'));
        }

        return $url;
    }

    /**
     * Gets the category cursor.
     *
     * @param      Cursor       $cur    The category cursor
     * @param      null|int     $id     The category ID
     *
     * @throws     CoreException
     */
    private function getCategoryCursor(Cursor $cur, ?int $id = null): void
    {
        if ($cur->cat_title == '') {
            throw new CoreException(__('You must provide a category title'));
        }

        # If we don't have any cat_url, let's do one
        if ($cur->cat_url == '') {
            $cur->cat_url = Text::tidyURL($cur->cat_title, false);
        }

        # Still empty ?
        if ($cur->cat_url == '') {
            throw new CoreException(__('You must provide a category URL'));
        }
        $cur->cat_url = Text::tidyURL($cur->cat_url, true);

        # Check if title or url are unique
        $cur->cat_url = $this->checkCategory($cur->cat_title, $cur->cat_url, $id);

        if ($cur->cat_desc !== null) {
            $cur->cat_desc = dcCore()->HTMLfilter($cur->cat_desc);
        }
    }
    //@}

    /// @name Entries management methods
    //@{
    /**
     * Retrieves entries. <b>$params</b> is an array taking the following
     * optionnal parameters:
     *
     * - no_content: Don't retrieve entry content (excerpt and content)
     * - post_type: Get only entries with given type (default "post", array for many types and '' for no type)
     * - post_id: (integer or array) Get entry with given post_id
     * - post_url: Get entry with given post_url field
     * - user_id: (integer) Get entries belonging to given user ID
     * - cat_id: (string or array) Get entries belonging to given category ID
     * - cat_id_not: deprecated (use cat_id with "id ?not" instead)
     * - cat_url: (string or array) Get entries belonging to given category URL
     * - cat_url_not: deprecated (use cat_url with "url ?not" instead)
     * - post_status: (integer) Get entries with given post_status
     * - post_selected: (boolean) Get select flaged entries
     * - post_year: (integer) Get entries with given year
     * - post_month: (integer) Get entries with given month
     * - post_day: (integer) Get entries with given day
     * - post_lang: Get entries with given language code
     * - search: Get entries corresponding of the following search string
     * - columns: (array) More columns to retrieve
     * - sql: Append SQL string at the end of the query
     * - from: Append SQL string after "FROM" statement in query
     * - order: Order of results (default "ORDER BY post_dt DES")
     * - limit: Limit parameter
     * - sql_only : return the sql request instead of results. Only ids are selected
     * - exclude_post_id : (integer or array) Exclude entries with given post_id
     *
     * Please note that on every cat_id or cat_url, you can add ?not to exclude
     * the category and ?sub to get subcategories.
     *
     * @since 3.0 : remove sql_only params (reimplement someting later)
     *
     * @param    ArrayObject|array  $params        Parameters
     * @param    bool   $count_only    Only counts results
     *
     * @return   Record    A record with some more capabilities or the SQL request
     */
    public function getPosts(ArrayObject|array $params = [], bool $count_only = false): Record
    {
        $params = new ArrayObject($params);

        # --BEHAVIOR-- coreBlogBeforeGetPosts ArrayObject
        dcCore()->behaviors->call('coreBlogBeforeGetPosts', $params);

        if ($count_only) {
            $strReq = 'SELECT count(DISTINCT P.post_id) ';
        } elseif (!empty($params['sql_only'])) {
            DeprecatedException::throw();
            $strReq = 'SELECT P.post_id ';
        } else {
            if (!empty($params['no_content'])) {
                $content_req = '';
            } else {
                $content_req = 'post_excerpt, post_excerpt_xhtml, ' .
                    'post_content, post_content_xhtml, post_notes, ';
            }

            if (!empty($params['columns']) && is_array($params['columns'])) {
                $content_req .= implode(', ', $params['columns']) . ', ';
            }

            $strReq = 'SELECT P.post_id, P.blog_id, P.user_id, P.cat_id, post_dt, ' .
                'post_tz, post_creadt, post_upddt, post_format, post_password, ' .
                'post_url, post_lang, post_title, ' . $content_req .
                'post_type, post_meta, ' .
                'post_status, post_firstpub, post_selected, post_position, ' .
                'post_open_comment, post_open_tb, nb_comment, nb_trackback, ' .
                'U.user_name, U.user_firstname, U.user_displayname, U.user_email, ' .
                'U.user_url, ' .
                'C.cat_title, C.cat_url, C.cat_desc ';
        }

        $strReq .= 'FROM ' . $this->prefix . 'post P ' .
        'INNER JOIN ' . $this->prefix . 'user U ON U.user_id = P.user_id ' .
        'LEFT OUTER JOIN ' . $this->prefix . 'category C ON P.cat_id = C.cat_id ';

        if (!empty($params['from'])) {
            $strReq .= $params['from'] . ' ';
        }

        $strReq .= "WHERE P.blog_id = '" . $this->con->escape($this->id) . "' ";

        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= 'AND ((post_status = 1 ';

            if ($this->without_password) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (dcCore()->auth->userID()) {
                $strReq .= "OR P.user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        #Adding parameters
        if (isset($params['post_type'])) {
            if (is_array($params['post_type']) || $params['post_type'] != '') {
                $strReq .= 'AND post_type ' . $this->con->in($params['post_type']);
            }
        } else {
            $strReq .= "AND post_type = 'post' ";
        }

        if (isset($params['post_id']) && $params['post_id'] !== '') {
            if (is_array($params['post_id'])) {
                array_walk($params['post_id'], function (&$v, $k) { if ($v !== null) {$v = (int) $v;}});
            } else {
                $params['post_id'] = [(int) $params['post_id']];
            }
            $strReq .= 'AND P.post_id ' . $this->con->in($params['post_id']);
        }

        if (isset($params['exclude_post_id']) && $params['exclude_post_id'] !== '') {
            if (is_array($params['exclude_post_id'])) {
                array_walk($params['exclude_post_id'], function (&$v, $k) { if ($v !== null) {$v = (int) $v;}});
            } else {
                $params['exclude_post_id'] = [(int) $params['exclude_post_id']];
            }
            $strReq .= 'AND P.post_id NOT ' . $this->con->in($params['exclude_post_id']);
        }

        if (isset($params['post_url']) && $params['post_url'] !== '') {
            $strReq .= "AND post_url = '" . $this->con->escape($params['post_url']) . "' ";
        }

        if (!empty($params['user_id'])) {
            $strReq .= "AND U.user_id = '" . $this->con->escape($params['user_id']) . "' ";
        }

        if (isset($params['cat_id']) && $params['cat_id'] !== '') {
            if (!is_array($params['cat_id'])) {
                $params['cat_id'] = [$params['cat_id']];
            }
            if (!empty($params['cat_id_not'])) {
                array_walk($params['cat_id'], function (&$v, $k) {$v = $v . ' ?not';});
            }
            $strReq .= 'AND ' . $this->getPostsCategoryFilter($params['cat_id'], 'cat_id') . ' ';
        } elseif (isset($params['cat_url']) && $params['cat_url'] !== '') {
            if (!is_array($params['cat_url'])) {
                $params['cat_url'] = [$params['cat_url']];
            }
            if (!empty($params['cat_url_not'])) {
                array_walk($params['cat_url'], function (&$v, $k) {$v = $v . ' ?not';});
            }
            $strReq .= 'AND ' . $this->getPostsCategoryFilter($params['cat_url'], 'cat_url') . ' ';
        }

        /* Other filters */
        if (isset($params['post_status'])) {
            $strReq .= 'AND post_status = ' . (int) $params['post_status'] . ' ';
        }

        if (isset($params['post_firstpub'])) {
            $strReq .= 'AND post_firstpub = ' . (int) $params['post_firstpub'] . ' ';
        }

        if (isset($params['post_selected'])) {
            $strReq .= 'AND post_selected = ' . (int) $params['post_selected'] . ' ';
        }

        if (!empty($params['post_year'])) {
            $strReq .= 'AND ' . $this->con->dateFormat('post_dt', '%Y') . ' = ' .
            "'" . sprintf('%04d', $params['post_year']) . "' ";
        }

        if (!empty($params['post_month'])) {
            $strReq .= 'AND ' . $this->con->dateFormat('post_dt', '%m') . ' = ' .
            "'" . sprintf('%02d', $params['post_month']) . "' ";
        }

        if (!empty($params['post_day'])) {
            $strReq .= 'AND ' . $this->con->dateFormat('post_dt', '%d') . ' = ' .
            "'" . sprintf('%02d', $params['post_day']) . "' ";
        }

        if (!empty($params['post_lang'])) {
            $strReq .= "AND P.post_lang = '" . $this->con->escape($params['post_lang']) . "' ";
        }

        if (!empty($params['search'])) {
            $words = Text::splitWords($params['search']);

            if (!empty($words)) {
                if (dcCore()->behaviors->has('corePostSearch')) {

                    # --BEHAVIOR-- corePostSearch, array
                    dcCore()->behaviors->call('corePostSearch', [&$words, &$strReq, &$params]);
                }

                foreach ($words as $i => $w) {
                    $words[$i] = "post_words LIKE '%" . $this->con->escape($w) . "%'";
                }
                $strReq .= 'AND ' . implode(' AND ', $words) . ' ';
            }
        }

        if (isset($params['media'])) {
            if ($params['media'] == '0') {
                $strReq .= 'AND NOT ';
            } else {
                $strReq .= 'AND ';
            }
            $strReq .= 'EXISTS (SELECT M.post_id FROM ' . $this->prefix . 'post_media M ' .
                'WHERE M.post_id = P.post_id ';
            if (isset($params['link_type'])) {
                $strReq .= ' AND M.link_type ' . $this->con->in($params['link_type']) . ' ';
            }
            $strReq .= ')';
        }

        if (!empty($params['where'])) {
            $strReq .= $params['where'] . ' ';
        }

        if (!empty($params['sql'])) {
            $strReq .= $params['sql'] . ' ';
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . $this->con->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY post_dt DESC ';
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $strReq .= $this->con->limit($params['limit']);
        }

        $rs            = $this->con->select($strReq);
        $rs->_nb_media = [];
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtPost');

        # --BEHAVIOR-- coreBlogGetPosts
        dcCore()->behaviors->call('coreBlogGetPosts', $rs);

        $alt = new ArrayObject(['rs' => null, 'params' => $params, 'count_only' => $count_only]);

        # --BEHAVIOR-- coreBlogAfterGetPosts, ArrayObject, array
        dcCore()->behaviors->call('coreBlogAfterGetPosts', $rs, $alt);

        if ($alt['rs'] instanceof Record) { // @phpstan-ignore-line
            $rs = $alt['rs'];
        }

        return $rs;
    }

    /**
     * Returns a record with post id, title and date for next or previous post
     * according to the post ID.
     * $dir could be 1 (next post) or -1 (previous post).
     *
     * @param      Record  $post                  The post ID
     * @param      int     $dir                   The search direction
     * @param      bool    $restrict_to_category  Restrict to same category
     * @param      bool    $restrict_to_lang      Restrict to same language
     *
     * @return     null|Record   The next post.
     */
    public function getNextPost(Record $post, int $dir, bool $restrict_to_category = false, bool $restrict_to_lang = false): ?Record
    {
        $dt      = $post->post_dt;
        $post_id = (int) $post->post_id;

        if ($dir > 0) {
            $sign  = '>';
            $order = 'ASC';
        } else {
            $sign  = '<';
            $order = 'DESC';
        }

        $params['post_type'] = $post->post_type;
        $params['limit']     = 1;
        $params['order']     = 'post_dt ' . $order . ', P.post_id ' . $order;
        $params['sql']       = 'AND ( ' .
        "   (post_dt = '" . $this->con->escape($dt) . "' AND P.post_id " . $sign . ' ' . $post_id . ') ' .
        '   OR post_dt ' . $sign . " '" . $this->con->escape($dt) . "' " .
            ') ';

        if ($restrict_to_category) {
            $params['sql'] .= $post->cat_id ? 'AND P.cat_id = ' . (int) $post->cat_id . ' ' : 'AND P.cat_id IS NULL ';
        }

        if ($restrict_to_lang) {
            $params['sql'] .= $post->post_lang ? 'AND P.post_lang = \'' . $this->con->escape($post->post_lang) . '\' ' : 'AND P.post_lang IS NULL ';
        }

        $rs = $this->getPosts($params);

        if ($rs->isEmpty()) {
            return null;
        }

        return $rs;
    }

    /**
     * Retrieves different languages and post count on blog, based on post_lang
     * field. <var>$params</var> is an array taking the following optionnal
     * parameters:
     *
     * - post_type: Get only entries with given type (default "post", '' for no type)
     * - lang: retrieve post count for selected lang
     * - order: order statement (default post_lang DESC)
     *
     * @param      array   $params  The parameters
     *
     * @return     Record  The langs.
     */
    public function getLangs(array $params = []): Record
    {
        $strReq = 'SELECT COUNT(post_id) as nb_post, post_lang ' .
        'FROM ' . $this->prefix . 'post ' .
        "WHERE blog_id = '" . $this->con->escape($this->id) . "' " .
            "AND post_lang <> '' " .
            'AND post_lang IS NOT NULL ';

        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= 'AND ((post_status = 1 ';

            if ($this->without_password) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (dcCore()->auth->userID()) {
                $strReq .= "OR user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        if (isset($params['post_type'])) {
            if ($params['post_type'] != '') {
                $strReq .= "AND post_type = '" . $this->con->escape($params['post_type']) . "' ";
            }
        } else {
            $strReq .= "AND post_type = 'post' ";
        }

        if (isset($params['lang'])) {
            $strReq .= "AND post_lang = '" . $this->con->escape($params['lang']) . "' ";
        }

        $strReq .= 'GROUP BY post_lang ';

        $order = 'desc';
        if (!empty($params['order']) && preg_match('/^(desc|asc)$/i', $params['order'])) {
            $order = $params['order'];
        }
        $strReq .= 'ORDER BY post_lang ' . $order . ' ';

        return $this->con->select($strReq);
    }

    /**
     * Returns a record with all distinct blog dates and post count.
     * <var>$params</var> is an array taking the following optionnal parameters:
     *
     * - type: (day|month|year) Get days, months or years
     * - year: (integer) Get dates for given year
     * - month: (integer) Get dates for given month
     * - day: (integer) Get dates for given day
     * - cat_id: (integer) Category ID filter
     * - cat_url: Category URL filter
     * - post_lang: lang of the posts
     * - next: Get date following match
     * - previous: Get date before match
     * - order: Sort by date "ASC" or "DESC"
     *
     * @param      array   $params  The parameters
     *
     * @return     record  The dates.
     */
    public function getDates(array $params = []): Record
    {
        $dt_f  = '%Y-%m-%d';
        $dt_fc = '%Y%m%d';
        if (isset($params['type'])) {
            if ($params['type'] == 'year') {
                $dt_f  = '%Y-01-01';
                $dt_fc = '%Y0101';
            } elseif ($params['type'] == 'month') {
                $dt_f  = '%Y-%m-01';
                $dt_fc = '%Y%m01';
            }
        }
        $dt_f  .= ' 00:00:00';
        $dt_fc .= '000000';

        $cat_field = $catReq = $limit = '';

        if (isset($params['cat_id']) && $params['cat_id'] !== '') {
            $catReq    = 'AND P.cat_id = ' . (int) $params['cat_id'] . ' ';
            $cat_field = ', C.cat_url ';
        } elseif (isset($params['cat_url']) && $params['cat_url'] !== '') {
            $catReq    = "AND C.cat_url = '" . $this->con->escape($params['cat_url']) . "' ";
            $cat_field = ', C.cat_url ';
        }
        if (!empty($params['post_lang'])) {
            $catReq = 'AND P.post_lang = \'' . $params['post_lang'] . '\' ';
        }

        $strReq = 'SELECT DISTINCT(' . $this->con->dateFormat('post_dt', $dt_f) . ') AS dt ' .
        $cat_field .
        ',COUNT(P.post_id) AS nb_post ' .
        'FROM ' . $this->prefix . 'post P LEFT JOIN ' . $this->prefix . 'category C ' .
        'ON P.cat_id = C.cat_id ' .
        "WHERE P.blog_id = '" . $this->con->escape($this->id) . "' " .
            $catReq;

        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= 'AND ((post_status = 1 ';

            if ($this->without_password) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (dcCore()->auth->userID()) {
                $strReq .= "OR P.user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        if (!empty($params['post_type'])) {
            $strReq .= 'AND post_type ' . $this->con->in($params['post_type']) . ' ';
        } else {
            $strReq .= "AND post_type = 'post' ";
        }

        if (!empty($params['year'])) {
            $strReq .= 'AND ' . $this->con->dateFormat('post_dt', '%Y') . " = '" . sprintf('%04d', $params['year']) . "' ";
        }

        if (!empty($params['month'])) {
            $strReq .= 'AND ' . $this->con->dateFormat('post_dt', '%m') . " = '" . sprintf('%02d', $params['month']) . "' ";
        }

        if (!empty($params['day'])) {
            $strReq .= 'AND ' . $this->con->dateFormat('post_dt', '%d') . " = '" . sprintf('%02d', $params['day']) . "' ";
        }

        # Get next or previous date
        if (!empty($params['next']) || !empty($params['previous'])) {
            if (!empty($params['next'])) {
                $pdir            = ' > ';
                $params['order'] = 'asc';
                $dt              = $params['next'];
            } else {
                $pdir            = ' < ';
                $params['order'] = 'desc';
                $dt              = $params['previous'];
            }

            $dt = date('YmdHis', strtotime($dt));

            $strReq .= 'AND ' . $this->con->dateFormat('post_dt', $dt_fc) . $pdir . "'" . $dt . "' ";
            $limit = $this->con->limit(1);
        }

        $strReq .= 'GROUP BY dt ' . $cat_field;

        $order = 'desc';
        if (!empty($params['order']) && preg_match('/^(desc|asc)$/i', $params['order'])) {
            $order = $params['order'];
        }

        $strReq .= 'ORDER BY dt ' . $order . ' ' .
            $limit;

        $rs = $this->con->select($strReq);
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtDates');

        return $rs;
    }

    /**
     * Creates a new entry. Takes a cursor as input and returns the new entry ID.
     *
     * @param      Cursor     $cur    The post cursor
     *
     * @throws     CoreException
     *
     * @return     int
     */
    public function addPost(Cursor $cur): int
    {
        if (!dcCore()->auth->check('usage,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to create an entry'));
        }

        $this->con->writeLock($this->prefix . 'post');

        try {
            # Get ID
            $rs = $this->con->select(
                'SELECT MAX(post_id) ' .
                'FROM ' . $this->prefix . 'post '
            );

            $cur->post_id     = (int) $rs->f(0) + 1;
            $cur->blog_id     = (string) $this->id;
            $cur->post_creadt = date('Y-m-d H:i:s');
            $cur->post_upddt  = date('Y-m-d H:i:s');
            $cur->post_tz     = dcCore()->auth->getInfo('user_tz');

            # Post excerpt and content
            $this->getPostContent($cur, $cur->post_id);

            $this->getPostCursor($cur);

            $cur->post_url = $this->getPostURL($cur->post_url, $cur->post_dt, $cur->post_title, $cur->post_id);

            if (!dcCore()->auth->check('publish,contentadmin', $this->id)) {
                $cur->post_status = -2;
            }

            # --BEHAVIOR-- coreBeforePostCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
            dcCore()->behaviors->call('coreBeforePostCreate', $this, $cur);

            $cur->insert();
            $this->con->unlock();
        } catch (Exception $e) {
            $this->con->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterPostCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dcCore()->behaviors->call('coreAfterPostCreate', $this, $cur);

        $this->triggerBlog();

        $this->firstPublicationEntries($cur->post_id);

        return (int) $cur->post_id;
    }

    /**
     * Updates an existing post.
     *
     * @param      int     $id     The post identifier
     * @param      Cursor      $cur    The post cursor
     *
     * @throws     CoreException
     */
    public function updPost(int $id, Cursor $cur): void
    {
        if (!dcCore()->auth->check('usage,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to update entries'));
        }

        $id = (int) $id;

        if (empty($id)) {
            throw new CoreException(__('No such entry ID'));
        }

        # Post excerpt and content
        $this->getPostContent($cur, $id);

        $this->getPostCursor($cur);

        if ($cur->post_url !== null) {
            $cur->post_url = $this->getPostURL($cur->post_url, $cur->post_dt, $cur->post_title, $id);
        }

        if (!dcCore()->auth->check('publish,contentadmin', $this->id)) {
            $cur->unsetField('post_status');
        }

        $cur->post_upddt = date('Y-m-d H:i:s');

        #If user is only "usage", we need to check the post's owner
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq = 'SELECT post_id ' .
            'FROM ' . $this->prefix . 'post ' .
            'WHERE post_id = ' . $id . ' ' .
            "AND user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "' ";

            $rs = $this->con->select($strReq);

            if ($rs->isEmpty()) {
                throw new CoreException(__('You are not allowed to edit this entry'));
            }
        }

        # --BEHAVIOR-- coreBeforePostUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dcCore()->behaviors->call('coreBeforePostUpdate', $this, $cur);

        $cur->update('WHERE post_id = ' . $id . ' ');

        # --BEHAVIOR-- coreBeforePostUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dcCore()->behaviors->call('coreBeforePostUpdate', $this, $cur);

        $this->triggerBlog();

        $this->firstPublicationEntries($id);
    }

    /**
     * Update post status.
     *
     * @param      int  $id      The identifier
     * @param      int  $status  The status
     */
    public function updPostStatus(int $id, int $status): void
    {
        $this->updPostsStatus($id, $status);
    }

    /**
     * Updates posts status.
     *
     * @param      int|array|ArrayObject    $ids     The identifiers
     * @param      int                      $status  The status
     *
     * @throws     CoreException
     */
    public function updPostsStatus($ids, int $status): void
    {
        if (!dcCore()->auth->check('publish,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to change this entry status'));
        }

        $posts_ids = Utils::cleanIds($ids);
        $status    = (int) $status;

        $strReq = "WHERE blog_id = '" . $this->con->escape($this->id) . "' " .
        'AND post_id ' . $this->con->in($posts_ids);

        #If user can only publish, we need to check the post's owner
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= "AND user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "' ";
        }

        $cur = $this->con->openCursor($this->prefix . 'post');

        $cur->post_status = $status;
        $cur->post_upddt  = date('Y-m-d H:i:s');

        $cur->update($strReq);
        $this->triggerBlog();

        $this->firstPublicationEntries($posts_ids);
    }

    /**
     * Updates post selection.
     *
     * @param      int              $id        The identifier
     * @param      bool|int|null    $selected  The selected flag
     */
    public function updPostSelected(int $id, $selected): void
    {
        $this->updPostsSelected($id, $selected);
    }

    /**
     * Updates posts selection.
     *
     * @param      int|array|ArrayObject          $ids       The identifiers
     * @param      bool|int|null                  $selected  The selected flag
     *
     * @throws     CoreException
     */
    public function updPostsSelected($ids, $selected): void
    {
        if (!dcCore()->auth->check('usage,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to change this entry category'));
        }

        $posts_ids = Utils::cleanIds($ids);
        $selected  = (bool) $selected;

        $strReq = "WHERE blog_id = '" . $this->con->escape($this->id) . "' " .
        'AND post_id ' . $this->con->in($posts_ids);

        # If user is only usage, we need to check the post's owner
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= "AND user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "' ";
        }

        $cur = $this->con->openCursor($this->prefix . 'post');

        $cur->post_selected = (int) $selected;
        $cur->post_upddt    = date('Y-m-d H:i:s');

        $cur->update($strReq);
        $this->triggerBlog();
    }

    /**
     * Updates post category. <var>$cat_id</var> can be null.
     *
     * @param      int  $id         The identifier
     * @param      bool|int|null    $cat_id  The cat identifier
     */
    public function updPostCategory(int $id, $cat_id): void
    {
        $this->updPostsCategory($id, $cat_id);
    }

    /**
     * Updates posts category. <var>$cat_id</var> can be null.
     *
     * @param      int|array|ArrayObject    $ids     The identifiers
     * @param      boo|int|null             $cat_id  The cat identifier
     *
     * @throws     CoreException
     */
    public function updPostsCategory($ids, $cat_id): void
    {
        if (!dcCore()->auth->check('usage,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to change this entry category'));
        }

        $posts_ids = Utils::cleanIds($ids);
        $cat_id    = (int) $cat_id;

        $strReq = "WHERE blog_id = '" . $this->con->escape($this->id) . "' " .
        'AND post_id ' . $this->con->in($posts_ids);

        # If user is only usage, we need to check the post's owner
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= "AND user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "' ";
        }

        $cur = $this->con->openCursor($this->prefix . 'post');

        $cur->cat_id     = ($cat_id ?: null);
        $cur->post_upddt = date('Y-m-d H:i:s');

        $cur->update($strReq);
        $this->triggerBlog();
    }

    /**
     * Updates posts category. <var>$new_cat_id</var> can be null.
     *
     * @param      int|null    $old_cat_id  The old cat identifier
     * @param      int|null    $new_cat_id  The new cat identifier
     *
     * @throws     CoreException
     */
    public function changePostsCategory(?int $old_cat_id, ?int $new_cat_id): void
    {
        if (!dcCore()->auth->check('contentadmin,categories', $this->id)) {
            throw new CoreException(__('You are not allowed to change entries category'));
        }

        $old_cat_id = (int) $old_cat_id;
        $new_cat_id = (int) $new_cat_id;

        $cur = $this->con->openCursor($this->prefix . 'post');

        $cur->cat_id     = ($new_cat_id ?: null);
        $cur->post_upddt = date('Y-m-d H:i:s');

        $cur->update(
            'WHERE cat_id = ' . $old_cat_id . ' ' .
            "AND blog_id = '" . $this->con->escape($this->id) . "' "
        );
        $this->triggerBlog();
    }

    /**
     * Deletes a post.
     *
     * @param      int  $id     The post identifier
     */
    public function delPost(int $id): void
    {
        $this->delPosts($id);
    }

    /**
     * Deletes multiple posts.
     *
     * @param      int|array|ArrayObject    $ids    The posts identifiers
     *
     * @throws     CoreException
     */
    public function delPosts($ids): void
    {
        if (!dcCore()->auth->check('delete,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to delete entries'));
        }

        $posts_ids = Utils::cleanIds($ids);

        if (empty($posts_ids)) {
            throw new CoreException(__('No such entry ID'));
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'post ' .
        "WHERE blog_id = '" . $this->con->escape($this->id) . "' " .
        'AND post_id ' . $this->con->in($posts_ids);

        #If user can only delete, we need to check the post's owner
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= "AND user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "' ";
        }

        $this->con->execute($strReq);
        $this->triggerBlog();
    }

    /**
     * Publishes all entries flaged as "scheduled".
     */
    public function publishScheduledEntries(): void
    {
        $strReq = 'SELECT post_id, post_dt, post_tz ' .
        'FROM ' . $this->prefix . 'post ' .
        'WHERE post_status = -1 ' .
        "AND blog_id = '" . $this->con->escape($this->id) . "' ";

        $rs = $this->con->select($strReq);

        $now       = Dt::toUTC(time());
        $to_change = new ArrayObject();

        if ($rs->isEmpty()) {
            return;
        }

        while ($rs->fetch()) {
            # Now timestamp with post timezone
            $now_tz = $now + Dt::getTimeOffset($rs->post_tz, $now);

            # Post timestamp
            $post_ts = strtotime($rs->post_dt);

            # If now_tz >= post_ts, we publish the entry
            if ($now_tz >= $post_ts) {
                $to_change[] = (int) $rs->post_id;
            }
        }
        if (count($to_change)) {

            # --BEHAVIOR-- coreBeforeScheduledEntriesPublish, Dotclear\Core\Blog, array
            dcCore()->behaviors->call('coreBeforeScheduledEntriesPublish', $this, $to_change);

            $strReq = 'UPDATE ' . $this->prefix . 'post SET ' .
            'post_status = 1 ' .
            "WHERE blog_id = '" . $this->con->escape($this->id) . "' " .
            'AND post_id ' . $this->con->in((array) $to_change) . ' ';
            $this->con->execute($strReq);
            $this->triggerBlog();

            # --BEHAVIOR-- coreAfterScheduledEntriesPublish, Dotclear\Core\Blog, array
            dcCore()->behaviors->call('coreAfterScheduledEntriesPublish', $this, $to_change);

            $this->firstPublicationEntries($to_change);
        }
    }

    /**
     * First publication mecanism (on post create, update, publish, status)
     *
     * @param      int|array|ArrayObject      $ids    The posts identifiers
     */
    public function firstPublicationEntries($ids): void
    {
        $posts = $this->getPosts([
            'post_id'       => Utils::cleanIds($ids),
            'post_status'   => 1,
            'post_firstpub' => 0,
        ]);

        $to_change = [];
        while ($posts->fetch()) {
            $to_change[] = $posts->post_id;
        }

        if (count($to_change)) {
            $strReq = 'UPDATE ' . $this->prefix . 'post ' .
            'SET post_firstpub = 1 ' .
            "WHERE blog_id = '" . $this->con->escape($this->id) . "' " .
            'AND post_id ' . $this->con->in((array) $to_change) . ' ';
            $this->con->execute($strReq);

            # --BEHAVIOR-- coreFirstPublicationEntries, Dotclear\Core\Blog, array
            dcCore()->behaviors->call('coreFirstPublicationEntries', $this, $to_change);
        }
    }

    /**
     * Retrieves all users having posts on current blog.
     *
     * @param    string     $post_type post_type filter (post)
     *
     * @return    Record
     */
    public function getPostsUsers(string $post_type = 'post'): Record
    {
        $strReq = 'SELECT P.user_id, user_name, user_firstname, ' .
        'user_displayname, user_email ' .
        'FROM ' . $this->prefix . 'post P, ' . $this->prefix . 'user U ' .
        'WHERE P.user_id = U.user_id ' .
        "AND blog_id = '" . $this->con->escape($this->id) . "' ";

        if ($post_type) {
            $strReq .= "AND post_type = '" . $this->con->escape($post_type) . "' ";
        }

        $strReq .= 'GROUP BY P.user_id, user_name, user_firstname, user_displayname, user_email ';

        return $this->con->select($strReq);
    }

    private function getPostsCategoryFilter(array $arr, string $field = 'cat_id'): string
    {
        $field = $field == 'cat_id' ? 'cat_id' : 'cat_url';

        $sub     = [];
        $not     = [];
        $queries = [];

        foreach ($arr as $v) {
            $v    = trim($v);
            $args = preg_split('/\s*[?]\s*/', $v, -1, PREG_SPLIT_NO_EMPTY);
            $id   = array_shift($args);
            $args = array_flip($args);

            if (isset($args['not'])) {
                $not[$id] = 1;
            }
            if (isset($args['sub'])) {
                $sub[$id] = 1;
            }
            if ($field == 'cat_id') {
                if (preg_match('/^null$/i', $id)) {
                    $queries[$id] = 'P.cat_id IS NULL';
                } else {
                    $queries[$id] = 'P.cat_id = ' . (int) $id;
                }
            } else {
                $queries[$id] = "C.cat_url = '" . $this->con->escape($id) . "' ";
            }
        }

        if (!empty($sub)) {
            $rs = $this->con->select(
                'SELECT cat_id, cat_url, cat_lft, cat_rgt FROM ' . $this->prefix . 'category ' .
                "WHERE blog_id = '" . $this->con->escape($this->id) . "' " .
                'AND ' . $field . ' ' . $this->con->in(array_keys($sub))
            );

            while ($rs->fetch()) {
                $queries[$rs->f($field)] = '(C.cat_lft BETWEEN ' . $rs->cat_lft . ' AND ' . $rs->cat_rgt . ')';
            }
        }

        # Create queries
        $sql = [
            0 => [], # wanted categories
            1 => [], # excluded categories
        ];

        foreach ($queries as $id => $q) {
            $sql[(int) isset($not[$id])][] = $q;
        }

        $sql[0] = implode(' OR ', $sql[0]);
        $sql[1] = implode(' OR ', $sql[1]);

        if ($sql[0]) {
            $sql[0] = '(' . $sql[0] . ')';
        } else {
            unset($sql[0]);
        }

        if ($sql[1]) {
            $sql[1] = '(P.cat_id IS NULL OR NOT(' . $sql[1] . '))';
        } else {
            unset($sql[1]);
        }

        return implode(' AND ', $sql);  // @phpstan-ignore-line
    }

    /**
     * Gets the post cursor.
     *
     * @param      Cursor      $cur      The post cursor
     * @param      int     $post_id  The post identifier
     *
     * @throws     CoreException
     */
    private function getPostCursor(Cursor $cur, int $post_id = null): void
    {
        if ($cur->post_title == '') {
            throw new CoreException(__('No entry title'));
        }

        if ($cur->post_content == '') {
            throw new CoreException(__('No entry content'));
        }

        if ($cur->post_password === '') {
            $cur->post_password = null;
        }

        if ($cur->post_dt == '') {
            $offset       = Dt::getTimeOffset(dcCore()->auth->getInfo('user_tz'));
            $now          = time() + $offset;
            $cur->post_dt = date('Y-m-d H:i:00', $now);
        }

        $post_id = is_int($post_id) ? $post_id : $cur->post_id;

        if ($cur->post_content_xhtml == '') {
            throw new CoreException(__('No entry content'));
        }

        # Words list
        if ($cur->post_title !== null && $cur->post_excerpt_xhtml !== null
            && $cur->post_content_xhtml !== null) {
            $words = $cur->post_title . ' ' .
            $cur->post_excerpt_xhtml . ' ' .
            $cur->post_content_xhtml;

            $cur->post_words = implode(' ', Text::splitWords($words));
        }

        if ($cur->isField('post_firstpub')) {
            $cur->unsetField('post_firstpub');
        }
    }

    /**
     * Gets the post content.
     *
     * @param      Cursor   $cur      The post cursor
     * @param      int      $post_id  The post identifier
     */
    private function getPostContent(Cursor $cur, int $post_id): void
    {
        $post_excerpt       = $cur->post_excerpt;
        $post_excerpt_xhtml = $cur->post_excerpt_xhtml;
        $post_content       = $cur->post_content;
        $post_content_xhtml = $cur->post_content_xhtml;

        $this->setPostContent(
            $post_id,
            $cur->post_format,
            $cur->post_lang,
            $post_excerpt,
            $post_excerpt_xhtml,
            $post_content,
            $post_content_xhtml
        );

        $cur->post_excerpt       = $post_excerpt;
        $cur->post_excerpt_xhtml = $post_excerpt_xhtml;
        $cur->post_content       = $post_content;
        $cur->post_content_xhtml = $post_content_xhtml;
    }

    /**
     * Creates post HTML content, taking format and lang into account.
     *
     * @param      int|null     $post_id        The post identifier
     * @param      string       $format         The format
     * @param      string       $lang           The language
     * @param      string|null  $excerpt        The excerpt
     * @param      string|null  $excerpt_xhtml  The excerpt xhtml
     * @param      string       $content        The content
     * @param      string       $content_xhtml  The content xhtml
     */
    public function setPostContent(?int $post_id, string $format, string $lang, ?string &$excerpt, ?string &$excerpt_xhtml, string &$content, string &$content_xhtml): void
    {
        if ($format == 'wiki') {
            dcCore()->initWikiPost();
            dcCore()->wiki2xhtml->setOpt('note_prefix', 'pnote-' . ($post_id ?? ''));
            switch ($this->settings->system->note_title_tag) {
                case 1:
                    $tag = 'h3';

                    break;
                case 2:
                    $tag = 'p';

                    break;
                default:
                    $tag = 'h4';

                    break;
            }
            dcCore()->wiki2xhtml->setOpt('note_str', '<div class="footnotes"><' . $tag . ' class="footnotes-title">' .
                __('Notes') . '</' . $tag . '>%s</div>');
            dcCore()->wiki2xhtml->setOpt('note_str_single', '<div class="footnotes"><' . $tag . ' class="footnotes-title">' .
                __('Note') . '</' . $tag . '>%s</div>');
            if (strpos($lang, 'fr') === 0) {
                dcCore()->wiki2xhtml->setOpt('active_fr_syntax', 1);
            }
        }

        if ($excerpt) {
            $excerpt_xhtml = dcCore()->callEditorFormater('LegacyEditor', $format, $excerpt);
            $excerpt_xhtml = dcCore()->HTMLfilter($excerpt_xhtml);
        } else {
            $excerpt_xhtml = '';
        }

        if ($content) {
            $content_xhtml = dcCore()->callEditorFormater('LegacyEditor', $format, $content);
            $content_xhtml = dcCore()->HTMLfilter($content_xhtml);
        } else {
            $content_xhtml = '';
        }

        # --BEHAVIOR-- coreAfterPostContentFormat, array
        dcCore()->behaviors->call('coreAfterPostContentFormat', [
            'excerpt'       => &$excerpt,
            'content'       => &$content,
            'excerpt_xhtml' => &$excerpt_xhtml,
            'content_xhtml' => &$content_xhtml,
        ]);
    }

    /**
     * Returns URL for a post according to blog setting <var>post_url_format</var>.
     * It will try to guess URL and append some figures if needed.
     *
     * @param      string|null  $url         The url
     * @param      string|null  $post_dt     The post dt
     * @param      string|null  $post_title  The post title
     * @param      int|null     $post_id     The post identifier
     *
     * @return     string  The post url.
     */
    public function getPostURL(?string $url, ?string $post_dt, ?string $post_title, ?int $post_id): string
    {
        $url = trim((string) $url);

        $url_patterns = [
            '{y}'  => date('Y', strtotime((string) $post_dt)),
            '{m}'  => date('m', strtotime((string) $post_dt)),
            '{d}'  => date('d', strtotime((string) $post_dt)),
            '{t}'  => Text::tidyURL((string) $post_title),
            '{id}' => (int) $post_id,
        ];

        # If URL is empty, we create a new one
        if ($url == '') {
            # Transform with format
            $url = str_replace(
                array_keys($url_patterns),
                array_values($url_patterns),
                $this->settings->system->post_url_format
            );
        } else {
            $url = Text::tidyURL($url);
        }

        # Let's check if URL is taken...
        $strReq = 'SELECT post_url FROM ' . $this->prefix . 'post ' .
        "WHERE post_url = '" . $this->con->escape($url) . "' " .
        'AND post_id <> ' . (int) $post_id . ' ' .
        "AND blog_id = '" . $this->con->escape($this->id) . "' " .
            'ORDER BY post_url DESC';

        $rs = $this->con->select($strReq);

        if (!$rs->isEmpty()) {
            if ($this->con->syntax() == 'mysql') {
                $clause = "REGEXP '^" . $this->con->escape(preg_quote($url)) . "[0-9]+$'";
            } elseif ($this->con->driver() == 'pgsql') {
                $clause = "~ '^" . $this->con->escape(preg_quote($url)) . "[0-9]+$'";
            } else {
                $clause = "LIKE '" .
                $this->con->escape(preg_replace(['%', '_', '!'], ['!%', '!_', '!!'], $url)) . "%' ESCAPE '!'";  // @phpstan-ignore-line
            }
            $strReq = 'SELECT post_url FROM ' . $this->prefix . 'post ' .
            'WHERE post_url ' . $clause . ' ' .
            'AND post_id <> ' . (int) $post_id . ' ' .
            "AND blog_id = '" . $this->con->escape($this->id) . "' " .
                'ORDER BY post_url DESC ';

            $rs = $this->con->select($strReq);
            $a  = [];
            while ($rs->fetch()) {
                $a[] = $rs->post_url;
            }

            natsort($a);
            $t_url = end($a);

            if (preg_match('/(.*?)([0-9]+)$/', $t_url, $m)) {
                $i   = (int) $m[2];
                $url = $m[1];
            } else {
                $i = 1;
            }

            return $url . ($i + 1);
        }

        # URL is empty?
        if ($url == '') {
            throw new CoreException(__('Empty entry URL'));
        }

        return $url;
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
                'comment_spam_status, comment_spam_filter, comment_ip, ' .
                'P.post_title, P.post_url, P.post_id, P.post_password, P.post_type, ' .
                'P.post_dt, P.user_id, U.user_email, U.user_url ';
        }

        $strReq .= 'FROM ' . $this->prefix . 'comment C ' .
        'INNER JOIN ' . $this->prefix . 'post P ON C.post_id = P.post_id ' .
        'INNER JOIN ' . $this->prefix . 'user U ON P.user_id = U.user_id ';

        if (!empty($params['from'])) {
            $strReq .= $params['from'] . ' ';
        }

        $strReq .= "WHERE P.blog_id = '" . $this->con->escape($this->id) . "' ";

        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= 'AND ((comment_status = 1 AND P.post_status = 1 ';

            if ($this->without_password) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (dcCore()->auth->userID()) {
                $strReq .= "OR P.user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        if (!empty($params['post_type'])) {
            $strReq .= 'AND post_type ' . $this->con->in($params['post_type']);
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
            $strReq .= 'AND comment_id ' . $this->con->in($params['comment_id']);
        }

        if (isset($params['comment_email'])) {
            $comment_email = $this->con->escape(str_replace('*', '%', $params['comment_email']));
            $strReq .= "AND comment_email LIKE '" . $comment_email . "' ";
        }

        if (isset($params['comment_site'])) {
            $comment_site = $this->con->escape(str_replace('*', '%', $params['comment_site']));
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
            $comment_ip = $this->con->escape(str_replace('*', '%', $params['comment_ip']));
            $strReq .= "AND comment_ip LIKE '" . $comment_ip . "' ";
        }

        if (isset($params['q_author'])) {
            $q_author = $this->con->escape(str_replace('*', '%', strtolower($params['q_author'])));
            $strReq .= "AND LOWER(comment_author) LIKE '" . $q_author . "' ";
        }

        if (!empty($params['search'])) {
            $words = Text::splitWords($params['search']);

            if (!empty($words)) {
                if (dcCore()->behaviors->has('coreCommentSearch')) {

                    # --BEHAVIOR coreCommentSearch, array
                    dcCore()->behaviors->call('coreCommentSearchs', [&$words, &$strReq, &$params]);
                }

                foreach ($words as $i => $w) {
                    $words[$i] = "comment_words LIKE '%" . $this->con->escape($w) . "%'";
                }
                $strReq .= 'AND ' . implode(' AND ', $words) . ' ';
            }
        }

        if (!empty($params['sql'])) {
            $strReq .= $params['sql'] . ' ';
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . $this->con->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY comment_dt DESC ';
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $strReq .= $this->con->limit($params['limit']);
        }

        $rs = $this->con->select($strReq);
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtComment');

        # --BEHAVIOR-- coreBlogGetComments, Dotclear\Database\Record
        dcCore()->behaviors->call('coreBlogGetComments', $rs);

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
        $this->con->writeLock($this->prefix . 'comment');

        try {
            # Get ID
            $rs = $this->con->select(
                'SELECT MAX(comment_id) ' .
                'FROM ' . $this->prefix . 'comment '
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
            dcCore()->behaviors->call('coreBeforeCommentCreate', $this, $cur);

            $cur->insert();
            $this->con->unlock();
        } catch (Exception $e) {
            $this->con->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterCommentCreate, Dotclear\Core\Blog, Dotclear\Database\Record
        dcCore()->behaviors->call('coreAfterCommentCreate', $this, $cur);

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
        if (!dcCore()->auth->check('usage,contentadmin', $this->id)) {
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
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            if ($rs->user_id != dcCore()->auth->userID()) {
                throw new CoreException(__('You are not allowed to update this comment'));
            }
        }

        $this->getCommentCursor($cur);

        $cur->comment_upddt = date('Y-m-d H:i:s');

        if (!dcCore()->auth->check('publish,contentadmin', $this->id)) {
            $cur->unsetField('comment_status');
        }

        # --BEHAVIOR-- coreBeforeCommentUpdate, Dotclear\Core\Blog, Dotclear\Database\Record
        dcCore()->behaviors->call('coreBeforeCommentUpdate', $this, $cur, $rs);

        $cur->update('WHERE comment_id = ' . $id . ' ');

        # --BEHAVIOR-- coreAfterCommentUpdate, Dotclear\Core\Blog, Dotclear\Database\Record
        dcCore()->behaviors->call('coreAfterCommentUpdate', $this, $cur, $rs);

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
        if (!dcCore()->auth->check('publish,contentadmin', $this->id)) {
            throw new CoreException(__("You are not allowed to change this comment's status"));
        }

        $co_ids = Utils::cleanIds($ids);
        $status = (int) $status;

        $strReq = 'UPDATE ' . $this->prefix . 'comment ' .
            'SET comment_status = ' . $status . ' ';
        $strReq .= 'WHERE comment_id' . $this->con->in($co_ids) .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . $this->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . $this->con->escape($this->id) . "' ";
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= "AND user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "' ";
        }
        $strReq .= ')';
        $this->con->execute($strReq);
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
        if (!dcCore()->auth->check('delete,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $co_ids = Utils::cleanIds($ids);

        if (empty($co_ids)) {
            throw new CoreException(__('No such comment ID'));
        }

        # Retrieve posts affected by comments edition
        $affected_posts = [];
        $strReq         = 'SELECT post_id ' .
        'FROM ' . $this->prefix . 'comment ' .
        'WHERE comment_id' . $this->con->in($co_ids) .
            'GROUP BY post_id';

        $rs = $this->con->select($strReq);

        while ($rs->fetch()) {
            $affected_posts[] = (int) $rs->post_id;
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'comment ' .
        'WHERE comment_id' . $this->con->in($co_ids) . ' ' .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . $this->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . $this->con->escape($this->id) . "' ";
        #If user can only delete, we need to check the post's owner
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= "AND tp.user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "' ";
        }
        $strReq .= ')';
        $this->con->execute($strReq);
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
        if (!dcCore()->auth->check('delete,contentadmin', $this->id)) {
            throw new CoreException(__('You are not allowed to delete comments'));
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'comment ' .
        'WHERE comment_status = -2 ' .
        'AND post_id in (SELECT tp.post_id ' .
        'FROM ' . $this->prefix . 'post tp ' .
        "WHERE tp.blog_id = '" . $this->con->escape($this->id) . "' ";
        #If user can only delete, we need to check the post's owner
        if (!dcCore()->auth->check('contentadmin', $this->id)) {
            $strReq .= "AND tp.user_id = '" . $this->con->escape(dcCore()->auth->userID()) . "' ";
        }
        $strReq .= ')';
        $this->con->execute($strReq);
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