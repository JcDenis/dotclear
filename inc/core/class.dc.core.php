<?php
/**
 * @brief Dotclear core class
 *
 * True to its name dcCore is the core of Dotclear. It handles everything related
 * to blogs, database connection, plugins...
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

use Dotclear\App;
use Dotclear\Core\Behavior;
use Dotclear\Core\Blogs;
use Dotclear\Core\Error;
use Dotclear\Core\Formater;
use Dotclear\Core\Log;
use Dotclear\Core\Nonce;
use Dotclear\Core\PostType;
use Dotclear\Core\Rest;
use Dotclear\Core\Users;
use Dotclear\Core\Version;
use Dotclear\Core\Wiki;
use Dotclear\Database\AbstractHandler;
use Dotclear\Database\Cursor;
use Dotclear\Database\Driver\Mysqli\Handler as MysqliHandler;
use Dotclear\Database\Driver\Mysqlimb4\Handler as Mysqlimb4Handler;
use Dotclear\Database\Driver\Pgsql\Handler as PgsqlHandler;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Session;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\HtmlFilter;
use Dotclear\Helper\Html\Template\Template;
use Dotclear\Helper\Html\WikiToHtml;
use Dotclear\Helper\Text;
use Dotclear\Module\Plugins;
use Dotclear\Module\Themes;
use Dotclear\Plugin\maintenance\Task\CountComments;
use Dotclear\Plugin\maintenance\Task\IndexPosts;
use Dotclear\Plugin\maintenance\Task\IndexComments;

final class dcCore
{
    use dcTraitDynamicProperties;

    // Constants

    /**
     * Session table name
     *
     * @var string
     */
    public const SESSION_TABLE_NAME = 'session';

    /**
     * Versions table name
     *
     * @deprecated since 2.27, use Version::VERSION_TABLE_NAME instead
     *
     * @var string
     */
    public const VERSION_TABLE_NAME = Version::VERSION_TABLE_NAME;

    // Properties

    /**
     * dcCore singleton instance
     *
     * @var dcCore
     */
    private static $instance;

    /**
     * Database connection
     *
     * @var AbstractHandler
     */
    public readonly AbstractHandler $con;

    /**
     * Database tables prefix
     *
     * @var string
     */
    public readonly string $prefix;

    /**
     * dcBlog instance
     *
     * @var dcBlog|null
     */
    public ?dcBlog $blog = null;

    /**
     * Users instance
     *
     * @var Users
     */
    public readonly Users $users;

    /**
     * Blogs instance
     *
     * @var Blogs
     */
    public readonly Blogs $blogs;

    /**
     * dcAuth instance
     *
     * @var dcAuth
     */
    public readonly dcAuth $auth;

    /**
     * Session in database instance
     *
     * @var Session
     */
    public readonly Session $session;

    /**
     * Behavior instance
     *
     * @var Behavior
     */
    public readonly Behavior $behavior;

    /**
     * Version instance
     *
     * @var Version
     */
    public readonly Version $version;

    /**
     * Nonce instance
     *
     * @var Nonce
     */
    public readonly Nonce $nonce;

    /**
     * Formater instance
     *
     * @var Formater
     */
    public readonly Formater $formater;

    /**
     * PostType instance
     *
     * @var PostType
     */
    public readonly PostType $post_type;

    /**
     * dcUrlHandlers instance
     *
     * @var dcUrlHandlers
     */
    public readonly dcUrlHandlers $url;

    /**
     *Rest instance
     *
     * @var Rest
     */
    public readonly Rest $rest;

    /**
     * WikiToHtml instance
     *
     * @var Wiki
     */
    public readonly Wiki $wiki;

    /**
     * WikiToHtml instance
     *
     * alias of $this->wiki
     *
     * @deprecated since 2.27 Use self::$wiki instead
     *
     * @var Wiki
     */
    public readonly Wiki $wiki2xhtml;

    /**
     * Plugins
     *
     * @var Plugins
     */
    public readonly Plugins $plugins;

    /**
     * Themes
     *
     * @var Themes
     */
    public readonly Themes $themes;

    /**
     * dcMedia instance
     *
     * @deprecated since 2.27 Use dcCore::app()->blog->media instead
     *
     * @var dcMedia|null
     */
    public ?dcMedia $media = null;

    /**
     * dcMeta instance
     *
     * @var dcMeta
     */
    public readonly dcMeta $meta;

    /**
     * Error instance
     *
     * @var Error
     */
    public readonly Error $error;

    /**
     * dcNotices instance
     *
     * @var dcNotices
     */
    public $notices;

    /**
     * Log instance
     *
     * @var Log
     */
    public readonly Log $log;

    /**
     * Starting time
     *
     * @var float
     */
    public readonly float $stime;

    /**
     * Current language
     *
     * @var string
     */
    public ?string $lang = null;

    /**
     * Php namespace autoloader
     *
     * @var Autoloader
     *
     * @deprecated since 2.26, use App::autoload() instead
     */
    public $autoload;

    // Admin context

    /**
     * dcAdmin instance
     *
     * @var dcAdmin|null
     */
    public ?dcAdmin $admin = null;

    /**
     * dcAdminURL instance
     *
     * May be transfered as property of dcAdmin instance in future
     *
     * @var dcAdminURL|null
     */
    public $adminurl;

    /**
     * dcFavorites instance
     *
     * May be transfered as property of dcAdmin instance in future
     *
     * @var dcFavorites
     */
    public $favs;

    /**
     * Array of several dcMenu instance
     *
     * May be transfered as property of dcAdmin instance in future
     *
     * @var ArrayObject
     */
    public $menu;

    /**
     * Array of resources
     *
     * May be transfered as property of dcAdmin instance in future
     *
     * @var array
     */
    public $resources = [];

    // Public context

    /**
     * dcPublic instance
     *
     * @var dcPublic|null
     */
    public ?dcPublic $public = null;

    /**
     * dcTemplate instance
     *
     * May be transfered as property of dcPublic instance in future
     *
     * @var dcTemplate
     */
    public $tpl;

    /**
     * context instance
     *
     * May be transfered as property of dcPublic instance in future
     *
     * @var context|null
     */
    public $ctx;

    /**
     * HTTP Cache stack
     *
     * May be transfered as property of dcPublic instance in future
     *
     * @var array
     */
    public $cache = [
        'mod_files' => [],
        'mod_ts'    => [],
    ];

    /**
     * Array of antispam filters (names)
     *
     * May be transfered as property of dcPublic instance in future
     *
     * @var array|null
     */
    public $spamfilters = [];

    // Private

    /**
     * dcCore constructor inits everything related to Dotclear. It takes arguments
     * to init database connection.
     *
     * @param      string  $driver    The db driver
     * @param      string  $host      The db host
     * @param      string  $db        The db name
     * @param      string  $user      The db user
     * @param      string  $password  The db password
     * @param      string  $prefix    The tables prefix
     * @param      bool    $persist   Persistent database connection
     */
    public function __construct(string $driver, string $host, string $db, string $user, string $password, string $prefix, bool $persist)
    {
        // Singleton mode
        if (is_a(self::$instance, self::class)) {
            throw new Exception('Application can not be started twice.', 500);
        }
        self::$instance = $this;

        $this->stime = defined('DC_START_TIME') ? DC_START_TIME : microtime(true);

        // Deprecated since 2.26
        $this->autoload = App::autoload();

        $this->con = AbstractHandler::init($driver, $host, $db, $user, $password, $persist);

        // Define weak_locks for mysql
        if (is_a($this->con, Mysqlimb4Handler::class)) {
            Mysqlimb4Handler::$weak_locks = true;
        } elseif (is_a($this->con, MysqliHandler::class)) {
            MysqliHandler::$weak_locks = true;
        }

        # define searchpath for postgresql
        if (is_a($this->con, PgsqlHandler::class)) {
            $searchpath = explode('.', $prefix, 2);
            if (count($searchpath) > 1) {
                $prefix = $searchpath[1];
                $sql    = 'SET search_path TO ' . $searchpath[0] . ',public;';
                $this->con->execute($sql);
            }
        }

        $this->prefix = $prefix;

        $ttl = DC_SESSION_TTL;
        if (!is_null($ttl)) {
            if (substr(trim((string) $ttl), 0, 1) != '-') {
                // Clearbricks requires negative session TTL
                $ttl = '-' . trim((string) $ttl);
            }
        }

        $this->behavior   = new Behavior();
        $this->error      = new Error();
        $this->users      = new Users();
        $this->blogs      = new Blogs();
        $this->wiki       = new Wiki();
        $this->wiki2xhtml = $this->wiki; // deprecated
        $this->auth       = $this->authInstance();
        $this->session    = new Session($this->con, $this->prefix . self::SESSION_TABLE_NAME, DC_SESSION_NAME, '', null, DC_ADMIN_SSL, $ttl);
        $this->version    = new Version();
        $this->nonce      = new Nonce();
        $this->formater   = new Formater();
        $this->post_type  = new PostType();
        $this->rest       = new Rest();
        $this->log        = new Log();
        $this->url        = new dcUrlHandlers();
        $this->plugins    = new Plugins();
        $this->themes     = new Themes();
        $this->meta       = new dcMeta();

        if (defined('DC_CONTEXT_ADMIN')) {
            /*
             * @deprecated Since 2.23, use dcCore::app()->resources instead
             */
            $GLOBALS['__resources'] = &$this->resources;
        }
    }

    /**
     * Get dcCore singleton instance
     *
     * @return     dcCore
     */
    public static function app(): dcCore
    {
        // throw Exception in order to return only dcCore (not null)
        if (!is_a(self::$instance, self::class)) {
            throw new Exception('Application is not started.', 500);
        }

        return self::$instance;
    }

    /**
     * Create a new instance of authentication class (user-defined or default)
     *
     * @throws     Exception
     *
     * @return     dcAuth
     */
    private function authInstance()
    {
        // You can set DC_AUTH_CLASS to whatever you want.
        // Your new class *should* inherits dcAuth.
        $class = defined('DC_AUTH_CLASS') ? DC_AUTH_CLASS : dcAuth::class;

        if (!class_exists($class)) {
            throw new Exception('Authentication class ' . $class . ' does not exist.');
        }

        if ($class !== dcAuth::class && !is_subclass_of($class, dcAuth::class)) {
            throw new Exception('Authentication class ' . $class . ' does not inherit dcAuth.');
        }

        return new $class($this);
    }

    /**
     * Kill admin session helper
     */
    public function killAdminSession(): void
    {
        // Kill session
        dcCore::app()->session->destroy();

        // Unset cookie if necessary
        if (isset($_COOKIE['dc_admin'])) {
            unset($_COOKIE['dc_admin']);
            setcookie('dc_admin', '', -600, '', '', DC_ADMIN_SSL);
        }
    }

    /// @name Blog init methods
    //@{
    /**
     * Sets the blog to use.
     *
     * @param      string  $id     The blog ID
     */
    public function setBlog($id): void
    {
        $this->blog = new dcBlog($id);

        // load media
        $this->blog->media = new dcMedia();

        // for compatibility only
        $this->media = $this->blog->media;

        // once blog is set, we can load their related themes
        $this->themes->loadModules($this->blog->themes_path);
    }

    /**
     * Unsets blog property
     */
    public function unsetBlog(): void
    {
        $this->blog = null;

        // for compatibility only
        $this->media = null;

        // reset themes instance
        $this->themes->resetModulesList();
    }
    //@}

    /// @name Blog status methods
    //@{
    /**
     * Gets all blog status.
     *
     * @deprecated since 2.27, use dcBlog::getAllBlogStatus() instead
     *
     * @return  array<int,string>    An array of available blog status codes and names.
     */
    public function getAllBlogStatus(): array
    {
        return dcBlog::getAllBlogStatus();
    }

    /**
     * Returns a blog status name given to a code.
     *
     * @deprecated since 2.27, use dcBlog::getAllBlogStatus() instead
     */
    public function getBlogStatus(int $s): string
    {
        return dcBlog::getBlogStatus($s);
    }
    //@}

    /// @name Admin nonce secret methods
    //@{
    /**
     * Gets the nonce.
     *
     * @deprecated since 2.27, use dcCore::app()->nonce->get() instead
     */
    public function getNonce(): string
    {
        return $this->nonce->get();
    }

    /**
     * Check the nonce.
     *
     * @deprecated since 2.27, use dcCore::app()->nonce->check() instead
     */
    public function checkNonce(string $secret): bool
    {
        return $this->nonce->check($secret);
    }

    /**
     * Get the nonce HTML code.
     *
     * @deprecated since 2.27, use dcCore::app()->nonce->form() instead
     */
    public function formNonce(bool $render = true): mixed
    {
        return $render ? $this->nonce->form()->render() : $this->nonce->form();
    }
    //@}

    /// @name Text Formatters methods
    //@{
    /**
     * Adds a new text formater.
     *
     * @deprecated since 2.27, use dcCore::app()->formater->add() instead
     */
    public function addEditorFormater(string $editor_id, string $name, mixed $func): void
    {
        $this->formater->add($editor_id, $name, $func);
    }

    /**
     * Adds a new dcLegacyEditor text formater.
     *
     * @deprecated since 2.27, use dcCore::app()->formater->add() instead
     */
    public function addFormater(string $name, mixed $func): void
    {
        $this->formater->add(Formater::LEGACY_FORMATER, $name, $func);
    }

    /**
     * Adds a formater name.
     *
     * @deprecated since 2.27, use dcCore::app()->formater->setName() instead
     */
    public function addFormaterName(string $format, string $name): void
    {
        $this->formater->setName($format, $name);
    }

    /**
     * Gets the formater name.
     *
     * @deprecated since 2.27, use dcCore::app()->formater->getName() instead
     */
    public function getFormaterName(string $format): string
    {
        return $this->formater->getName($format);
    }

    /**
     * Gets the editors list.
     *
     * @deprecated since 2.27, use dcCore::app()->formater->getEditors() instead
     *
     * @return  array<string,string>
     */
    public function getEditors(): array
    {
        return $this->formater->getEditors();
    }

    /**
     * Gets the formaters.
     *
     * @deprecated since 2.27, use dcCore::app()->formater->getFormaters() or ->getEditorFormaters() instead
     *
     * @return  array<string,array<int,string>>|array<int,string>
     */
    public function getFormaters(string $editor_id = ''): array
    {
        return empty($editor_id) ? $this->formater->getFormaters() : $this->formater->getEditorFormaters($editor_id);
    }

    /**
     * Call formater.
     *
     * @deprecated since 2.27, use dcCore::app()->formater->call() instead
     */
    public function callEditorFormater(string $editor_id, string $name, string $str): string
    {
        return $this->formater->call($editor_id, $name, $str);
    }

    /**
     * Call legacy formater.
     *
     * @deprecated since 2.27, use dcCore::app()->formater->call() instead
     */
    public function callFormater(string $name, string $str): string
    {
        return $this->formater->call(Formater::LEGACY_FORMATER, $name, $str);
    }
    //@}

    /// @name Behaviors methods
    //@{
    /**
     * Adds a new behavior to behaviors stack.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->add() instead
     */
    public function addBehavior(string $behavior, mixed $func): void
    {
        $this->behavior->add($behavior, $func);
    }

    /**
     * Adds a behaviour (alias).
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->add() instead
     */
    public function addBehaviour(string $behaviour, mixed $func): void
    {
        $this->behavior->add($behaviour, $func);
    }

    /**
     * Adds new behaviors to behaviors stack.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->add() instead
     *
     * @param   array<string,mixed>     $behaviors
     */
    public function addBehaviors(array $behaviors): void
    {
        $this->behavior->add($behaviors);
    }

    /**
     * Adds behaviours.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->add() instead
     *
     * @param   array<string,mixed>     $behaviours
     */
    public function addBehaviours(array $behaviours): void
    {
        $this->behavior->add($behaviours);
    }

    /**
     * Determines if behavior exists in behaviors stack.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->has() instead
     *
     * @return  bool
     */
    public function hasBehavior(string $behavior): bool
    {
        return $this->behavior->has($behavior);
    }

    /**
     * Determines if behaviour exists.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->has() instead
     *
     * @return  bool
     */
    public function hasBehaviour(string $behaviour): bool
    {
        return $this->behavior->has($behaviour);
    }

    /**
     * Gets the behaviors stack.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->get() instead
     *
     * @return  mixed
     */
    public function getBehaviors(string $behavior = ''): mixed
    {
        return empty($behavior) ? $this->behavior->dump() : $this->behavior->get($behavior);
    }

    /**
     * Gets the behaviours stack.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->get() instead
     *
     * @return  mixed
     */
    public function getBehaviours(string $behaviour = ''): mixed
    {
        return empty($behaviour) ? $this->behavior->dump() : $this->behavior->get($behaviour);
    }

    /**
     * Calls every function in behaviors stack.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->call() instead
     *
     * @param   mixed   ...$args
     *
     * @return  string
     */
    public function callBehavior(string $behavior, ...$args): string
    {
        return $this->behavior->call($behavior, ...$args);
    }

    /**
     * Calls every function in behaviours stack.
     *
     * @deprecated since 2.27, use dcCore::app()->behavior->call() instead
     *
     * @param   mixed   ...$args
     *
     * @return  string
     */
    public function callBehaviour(string $behaviour, ...$args): string
    {
        return $this->behavior->call($behaviour, ...$args);
    }
    //@}

    /// @name Post types URLs management
    //@{
    /**
     * Gets the post admin url.
     *
     * @deprecated since 2.27, use dcCore::app()->post_type->backend() instead
     */
    public function getPostAdminURL(string $type, int|string $post_id, bool $escaped = true): string
    {
        return $this->post_type->backend($type, $post_id, $escaped);
    }

    /**
     * Gets the post public url.
     *
     * @deprecated since 2.27, use dcCore::app()->post_type->frontend() instead
     */
    public function getPostPublicURL(string $type, string $post_url, bool $escaped = true): string
    {
        return $this->post_type->frontend($type, $post_url, $escaped);
    }

    /**
     * Sets the post type.
     *
     * @deprecated since 2.27, use dcCore::app()->post_type->add() or ->set() instead
     */
    public function setPostType(string $type, string $admin_url, string $public_url, string $label = ''): void
    {
        $this->post_type->set($type, $admin_url, $public_url, $label);
    }

    /**
     * Gets the post types.
     *
     * @deprecated since 2.27, use dcCore::app()->post_type->dump() instead
     *
     * @return     array<string,array{admin_url: string, public_url: string, label: string}>
     */
    public function getPostTypes(): array
    {
        $res = [];
        foreach ($this->post_type->dump() as $pt) {
            $res[$pt->type] = [
                'admin_url'  => $pt->backend,
                'public_url' => $pt->frontend,
                'label'      => $pt->label,
            ];
        }

        return $res;
    }
    //@}

    /// @name Versions management methods
    //@{
    /**
     * Gets the version of a module.
     *
     * @deprecated since 2.27, use dcCore::app()->version->get() instead
     */
    public function getVersion(?string $module = 'core'): ?string
    {
        return $this->version->get((string) $module);
    }

    /**
     * Gets all known versions.
     *
     * @deprecated since 2.27, use dcCore::app()->version->dump() instead
     *
     * @return  array<string,string>
     */
    public function getVersions(): array
    {
        return $this->version->dump();
    }

    /**
     * Sets the version of a module.
     *
     * @deprecated since 2.27, use dcCore::app()->version->set() instead
     */
    public function setVersion(string $module, string $version): void
    {
        $this->version->set($module, $version);
    }

    /**
     * Compare the given version of a module with the registered one.
     *
     * @deprecated since 2.27, use dcCore::app()->version->compare() instead
     */
    public function testVersion(?string $module, ?string $version): int
    {
        return $this->version->compare((string) $module, (string) $version);
    }

    /**
     * Test if a version is a new one.
     *
     * @deprecated since 2.27, use dcCore::app()->version->newer() instead
     */
    public function newVersion(?string $module, ?string $version): bool
    {
        return $this->version->newer((string) $module, (string) $version);
    }

    /**
     * Remove a module version entry.
     *
     * @deprecated since 2.27, use dcCore::app()->version->delete() instead
     */
    public function delVersion(string $module): void
    {
        $this->version->delete($module);
    }
    //@}

    /// @name Users management methods
    //@{
    /**
     * Gets the user by its ID.
     *
     * @deprecated since 2.27, use dcCore::app()->users->get() instead
     */
    public function getUser(string $id): MetaRecord
    {
        return $this->users->get($id);
    }

    /**
     * Returns a users list.
     *
     * @deprecated since 2.27, use dcCore::app()->users->search() instead
     *
     * @param   array<string,mixed>|ArrayObject     $params         The parameters
     */
    public function getUsers($params = [], bool $count_only = false): MetaRecord
    {
        return $this->users->search($params, $count_only);
    }

    /**
     * Adds a new user.
     *
     * @deprecated since 2.27, use dcCore::app()->users->add() instead
     */
    public function addUser(Cursor $cur): string
    {
        return $this->users->add($cur);
    }

    /**
     * Updates an existing user.
     *
     * @deprecated since 2.27, use dcCore::app()->users->update() instead
     */
    public function updUser(string $id, Cursor $cur): string
    {
        return $this->users->update($id, $cur);
    }

    /**
     * Deletes a user.
     *
     * @deprecated since 2.27, use dcCore::app()->users->delete() instead
     */
    public function delUser(string $id): void
    {
        $this->users->delete($id);
    }

    /**
     * Determines if user exists.
     *
     * @deprecated since 2.27, use dcCore::app()->users->has() instead
     */
    public function userExists(string $id): bool
    {
        return $this->users->has($id);
    }

    /**
     * Returns all user permissions as an array.
     *
     * @deprecated since 2.27, use dcCore::app()->users->getUserPermissions() instead
     *
     * @return  array<string,mixed>
     */
    public function getUserPermissions(string $id): array
    {
        $res = [];
        foreach ($this->users->getUserPermissions($id)->dump() as $p) {
            $res[$p->id] = $p->dump();
        }

        return $res;
    }

    /**
     * Sets user permissions.
     *
     * @deprecated since 2.27, use dcCore::app()->users->setUserPermissions() instead
     *
     * @param   array<string,array<string,bool>>    $perms  The permissions
     */
    public function setUserPermissions(string $id, array $perms): void
    {
        $this->users->setUserPermissions($id, $perms);
    }

    /**
     * Sets the user blog permissions.
     *
     * @deprecated since 2.27, use dcCore::app()->users->setUserBlogPermissions() instead
     *
     * @param   array<string,mixed>     $perms  The permissions
     */
    public function setUserBlogPermissions(string $id, string $blog_id, array $perms, bool $delete_first = true): void
    {
        $this->users->setUserBlogPermissions($id, $blog_id, $perms, $delete_first);
    }

    /**
     * Sets the user default blog.
     *
     * @deprecated since 2.27, use dcCore::app()->users->setUserDefaultBlog() instead
     */
    public function setUserDefaultBlog(string $id, string $blog_id): void
    {
        $this->users->setUserDefaultBlog($id, $blog_id);
    }

    /**
     * Removes users default blogs.
     *
     * @deprecated since 2.27, use dcCore::app()->users->removeUsersDefaultBlogs() instead
     *
     * @param   array<int,string>   $ids    The blogs to remove
     */
    public function removeUsersDefaultBlogs(array $ids): void
    {
        $this->users->removeUsersDefaultBlogs($ids);
        ;
    }

    /**
     * Returns user default settings in an associative array with setting names in keys.
     *
     * @deprecated since 2.27, use dcCore::app()->users::USER_DEFAULT_OPTIONS instead
     *
     * @return  array<string,mixed>
     */
    public function userDefaults(): array
    {
        return $this->users::USER_DEFAULT_OPTIONS;
    }
    //@}

    /// @name Blog management methods
    //@{
    /**
     * Returns all blog permissions (users) as an array.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->getBlogPermissions() instead
     *
     * @return  array<string,mixed>
     */
    public function getBlogPermissions(string $id, bool $with_super = true): array
    {
        $res = [];
        foreach ($this->blogs->getBlogPermissions($id, $with_super)->dump() as $p) {
            $res[$p->id] = $p->dump();
        }

        return $res;
    }

    /**
     * Gets the blog.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->get() instead
     */
    public function getBlog(string $id): MetaRecord
    {
        return $this->blogs->get($id);
    }

    /**
     * Returns a MetaRecord of blogs.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->search() instead
     *
     * @param   array<string,mixed>|ArrayObject     $params     The parameters
     */
    public function getBlogs($params = [], bool $count_only = false): MetaRecord
    {
        return $this->blogs->search($params, $count_only);
    }

    /**
     * Adds a new blog.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->add() instead
     */
    public function addBlog(Cursor $cur): void
    {
        $this->blogs->add($cur);
    }

    /**
     * Updates a given blog.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->update() instead
     */
    public function updBlog(string $id, Cursor $cur): void
    {
        $this->blogs->update($id, $cur);
    }

    /**
     * Removes a given blog.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->delete() instead
     */
    public function delBlog(string $id): void
    {
        $this->blogs->delete($id);
    }

    /**
     * Determines if blog exists.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->has() instead
     */
    public function blogExists(string $id): bool
    {
        return $this->blogs->has($id);
    }

    /**
     * Counts the number of blog posts.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->countPosts() instead
     */
    public function countBlogPosts(string $id, string $type = null): int
    {
        return $this->blogs->countPosts($id, $type);
    }

    /**
     * Creates default settings for active blog.
     *
     * @deprecated since 2.27, use dcCore::app()->blogs->setDefaultSettings() instead
     *
     * @param   array<int,array{0:string,1:string,2:bool|int|string,3:string}>  $defaults   The defaults settings
     */
    public function blogDefaults(?array $defaults = null): void
    {
        $this->blogs->setDefaultSettings($defaults);
    }
    //@}

    /// @name HTML Filter methods
    //@{
    /**
     * Calls HTML filter to drop bad tags and produce valid HTML output (if
     * tidy extension is present). If <b>enable_html_filter</b> blog setting is
     * false, returns not filtered string.
     *
     * @param      string  $str    The string
     *
     * @return     string
     */
    public function HTMLfilter(string $str): string
    {
        if ($this->blog instanceof dcBlog && !$this->blog->settings->get('system')->get('enable_html_filter')) {
            return $str;
        }

        $options = new ArrayObject([
            'keep_aria' => false,
            'keep_data' => false,
            'keep_js'   => false,
        ]);
        # --BEHAVIOR-- HTMLfilter -- ArrayObject
        $this->callBehavior('HTMLfilter', $options);

        $filter = new HtmlFilter((bool) $options['keep_aria'], (bool) $options['keep_data'], (bool) $options['keep_js']);
        $str    = trim($filter->apply($str));

        return $str;
    }
    //@}

    /// @name WikiToHtml methods
    //@{
    /**
     * Returns a transformed string with WikiToHtml.
     *
     * @deprecated since 2.27, use dcCore::app()->wiki->transform() instead
     */
    public function wikiTransform(string $str): string
    {
        return $this->wiki->transform($str);
    }

    /**
     * Inits <var>wiki</var> property for blog post.
     *
     * @deprecated since 2.27, use dcCore::app()->wiki->initWikiPost() instead
     */
    public function initWikiPost(): void
    {
        $this->wiki->initWikiPost();
    }

    /**
     * Inits <var>wiki</var> property for simple blog comment (basic syntax).
     *
     * @deprecated since 2.27, use dcCore::app()->wiki->initWikiSimpleComment() instead
     */
    public function initWikiSimpleComment(): void
    {
        $this->wiki->initWikiSimpleComment();
    }

    /**
     * Inits <var>wiki</var> property for blog comment.
     *
     * @deprecated since 2.27, use dcCore::app()->wiki->initWikiComment() instead
     */
    public function initWikiComment(): void
    {
        $this->wiki->initWikiComment();
    }

    /**
     * Get info about a post:id wiki macro.
     *
     * @deprecated since 2.27, use dcCore::app()->wiki->wikiPostLink() instead
     *
     * @return     array<string,string>
     */
    public function wikiPostLink(string $url, string $content): array
    {
        return $this->wiki->wikiPostLink($url, $content);
    }
    //@}

    /// @name Maintenance methods
    //@{
    /**
     * Recreates entries search engine index.
     *
     * @deprecated since 2.27, use Dotclear\Plugin\maintenance\Task\IndexPosts::IndexAllPosts() instead
     */
    public function indexAllPosts(?int $start = null, ?int $limit = null): ?int
    {
        return IndexPosts::indexAllPosts($start, $limit);
    }

    /**
     * Recreates comments search engine index.
     *
     * @deprecated since 2.27, use Dotclear\Plugin\maintenance\Task\IndexComments::indexAllComments() instead
     */
    public function indexAllComments(?int $start = null, ?int $limit = null): ?int
    {
        return IndexComments::indexAllComments($start, $limit);
    }

    /**
     * Reinits nb_comment and nb_trackback in post table.
     *
     * @deprecated since 2.27, use Dotclear\Plugin\maintenance\Task\CountComments::countAllComments() instead
     */
    public function countAllComments(): void
    {
        CountComments::countAllComments();
    }

    /**
     * Empty templates cache directory
     */
    public function emptyTemplatesCache(): void
    {
        if (is_dir(DC_TPL_CACHE . DIRECTORY_SEPARATOR . Template::CACHE_FOLDER)) {
            Files::deltree(DC_TPL_CACHE . DIRECTORY_SEPARATOR . Template::CACHE_FOLDER);
        }
    }

    /**
     * Serve or not the REST requests (using a file as token)
     *
     * @param   bool    $serve  The flag
     */
    public function enableRestServer(bool $serve = true): void
    {
        try {
            if ($serve && file_exists(DC_UPGRADE)) {
                // Remove watchdog file
                unlink(DC_UPGRADE);
            } elseif (!$serve && !file_exists(DC_UPGRADE)) {
                // Create watchdog file
                touch(DC_UPGRADE);
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Check if we need to serve REST requests
     *
     * @return  bool
     */
    public function serveRestRequests(): bool
    {
        return !file_exists(DC_UPGRADE) && DC_REST_SERVICES;
    }

    /**
     * Return elapsed time since script has been started
     *
     * @param   float   $mtime  timestamp (microtime format) to evaluate delta from current time is taken if null
     *
     * @return  float   The elapsed time.
     */
    public function getElapsedTime(?float $mtime = null): float
    {
        if ($mtime !== null) {
            return $mtime - $this->stime;
        }

        return microtime(true) - $this->stime;
    }
    //@}
}
