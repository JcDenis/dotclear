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

use Dotclear\App;
use Dotclear\Core\Behavior;
use Dotclear\Core\Blogs;
use Dotclear\Core\Formater;
use Dotclear\Core\Nonce;
use Dotclear\Core\PostType;
use Dotclear\Core\Users;
use Dotclear\Core\Version;
use Dotclear\Database\AbstractHandler;
use Dotclear\Database\Cursor;
use Dotclear\Database\Driver\Mysqli\Handler as MysqliHandler;
use Dotclear\Database\Driver\Mysqlimb4\Handler as Mysqlimb4Handler;
use Dotclear\Database\Driver\Pgsql\Handler as PgsqlHandler;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Session;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\HtmlFilter;
use Dotclear\Helper\Html\Template\Template;
use Dotclear\Helper\Html\WikiToHtml;
use Dotclear\Helper\Text;
use Dotclear\Module\Plugins;
use Dotclear\Module\Themes;

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
    public $blog = null;

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
     * dcRestServer instance
     *
     * @var dcRestServer
     */
    public readonly dcRestServer $rest;

    /**
     * WikiToHtml instance
     *
     * @var WikiToHtml
     */
    public $wiki;

    /**
     * WikiToHtml instance
     *
     * alias of $this->wiki
     *
     * @var WikiToHtml
     */
    public $wiki2xhtml;

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
     * @var dcMedia|null
     */
    public $media;

    /**
     * dcPostMedia instance
     *
     * @var dcPostMedia
     */
    public $postmedia;

    /**
     * dcMeta instance
     *
     * @var dcMeta
     */
    public readonly dcMeta $meta;

    /**
     * dcError instance
     *
     * @var dcError
     */
    public readonly dcError $error;

    /**
     * dcNotices instance
     *
     * @var dcNotices
     */
    public $notices;

    /**
     * dcLog instance
     *
     * @var dcLog
     */
    public readonly dcLog $log;

    /**
     * Starting time
     *
     * @var float
     */
    public $stime;

    /**
     * Current language
     *
     * @var string
     */
    public $lang;

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
     * @var dcAdmin
     */
    public $admin;

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
     * @var dcPublic
     */
    public $public;

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

        if (defined('DC_START_TIME')) {
            $this->stime = DC_START_TIME;
        } else {
            $this->stime = microtime(true);
        }

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

        $this->behavior  = new Behavior();
        $this->error     = new dcError();
        $this->users     = new Users();
        $this->blogs     = new Blogs();
        $this->auth      = $this->authInstance();
        $this->session   = new Session($this->con, $this->prefix . self::SESSION_TABLE_NAME, DC_SESSION_NAME, '', null, DC_ADMIN_SSL, $ttl);
        $this->version   = new Version();
        $this->nonce     = new Nonce();
        $this->formater  = new Formater();
        $this->post_type = new PostType();
        $this->url       = new dcUrlHandlers();
        $this->plugins   = new Plugins();
        $this->themes    = new Themes();
        $this->rest      = new dcRestServer();
        $this->meta      = new dcMeta();
        $this->log       = new dcLog();

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

        // once blog is set, we can load their related themes
        $this->themes->loadModules($this->blog->themes_path);
    }

    /**
     * Unsets blog property
     */
    public function unsetBlog(): void
    {
        $this->blog = null;

        // reset themes instance
        $this->themes->resetModulesList();
    }
    //@}

    /// @name Blog status methods
    //@{
    /**
     * Gets all blog status.
     *
     * @return     array  An array of available blog status codes and names.
     */
    public function getAllBlogStatus(): array
    {
        return [
            dcBlog::BLOG_ONLINE  => __('online'),
            dcBlog::BLOG_OFFLINE => __('offline'),
            dcBlog::BLOG_REMOVED => __('removed'),
        ];
    }

    /**
     * Returns a blog status name given to a code. This is intended to be
     * human-readable and will be translated, so never use it for tests.
     * If status code does not exist, returns <i>offline</i>.
     *
     * @param      int      $s      Status code
     *
     * @return     string   The blog status name.
     */
    public function getBlogStatus(int $s): string
    {
        $r = $this->getAllBlogStatus();
        if (isset($r[$s])) {
            return $r[$s];
        }

        return $r[0];
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
        foreach($this->post_type->dump() as $pt) {
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
        foreach($this->users->getUserPermissions($id)->dump() as $p) {
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
        $this->users->removeUsersDefaultBlogs($ids);;
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
        foreach($this->blogs->getBlogPermissions($id, $with_super)->dump() as $p) {
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
        if ($this->blog instanceof dcBlog && !$this->blog->settings->system->enable_html_filter) {
            return $str;
        }

        $options = new ArrayObject([
            'keep_aria' => false,
            'keep_data' => false,
            'keep_js'   => false,
        ]);
        # --BEHAVIOR-- HTMLfilter -- ArrayObject
        $this->callBehavior('HTMLfilter', $options);

        $filter = new HtmlFilter($options['keep_aria'], $options['keep_data'], $options['keep_js']);
        $str    = trim($filter->apply($str));

        return $str;
    }
    //@}

    /// @name WikiToHtml methods
    //@{
    /**
     * Initializes the WikiToHtml methods.
     */
    private function initWiki(): void
    {
        $this->wiki       = new WikiToHtml();
        $this->wiki2xhtml = $this->wiki;
    }

    /**
     * Returns a transformed string with WikiToHtml.
     *
     * @param      string  $str    The string
     *
     * @return     string
     */
    public function wikiTransform(string $str): string
    {
        if (!($this->wiki instanceof WikiToHtml)) {
            $this->initWiki();
        }

        return $this->wiki->transform($str);
    }

    /**
     * Inits <var>wiki</var> property for blog post.
     */
    public function initWikiPost(): void
    {
        $this->initWiki();

        $this->wiki->setOpts([
            'active_title'        => 1,
            'active_setext_title' => 0,
            'active_hr'           => 1,
            'active_lists'        => 1,
            'active_defl'         => 1,
            'active_quote'        => 1,
            'active_pre'          => 1,
            'active_empty'        => 1,
            'active_auto_urls'    => 0,
            'active_auto_br'      => 0,
            'active_antispam'     => 1,
            'active_urls'         => 1,
            'active_auto_img'     => 0,
            'active_img'          => 1,
            'active_anchor'       => 1,
            'active_em'           => 1,
            'active_strong'       => 1,
            'active_br'           => 1,
            'active_q'            => 1,
            'active_code'         => 1,
            'active_acronym'      => 1,
            'active_ins'          => 1,
            'active_del'          => 1,
            'active_footnotes'    => 1,
            'active_wikiwords'    => 0,
            'active_macros'       => 1,
            'active_mark'         => 1,
            'active_aside'        => 1,
            'active_sup'          => 1,
            'active_sub'          => 1,
            'active_i'            => 1,
            'active_span'         => 1,
            'parse_pre'           => 1,
            'active_fr_syntax'    => 0,
            'first_title_level'   => 3,
            'note_prefix'         => 'wiki-footnote',
            'note_str'            => '<div class="footnotes"><h4>Notes</h4>%s</div>',
            'img_style_center'    => 'display:table; margin:0 auto;',
        ]);

        $this->wiki->registerFunction('url:post', [$this, 'wikiPostLink']);

        # --BEHAVIOR-- coreWikiPostInit -- WikiToHtml
        $this->callBehavior('coreInitWikiPost', $this->wiki);
    }

    /**
     * Inits <var>wiki</var> property for simple blog comment (basic syntax).
     */
    public function initWikiSimpleComment(): void
    {
        $this->initWiki();

        $this->wiki->setOpts([
            'active_title'        => 0,
            'active_setext_title' => 0,
            'active_hr'           => 0,
            'active_lists'        => 0,
            'active_defl'         => 0,
            'active_quote'        => 0,
            'active_pre'          => 0,
            'active_empty'        => 0,
            'active_auto_urls'    => 1,
            'active_auto_br'      => 1,
            'active_antispam'     => 1,
            'active_urls'         => 0,
            'active_auto_img'     => 0,
            'active_img'          => 0,
            'active_anchor'       => 0,
            'active_em'           => 0,
            'active_strong'       => 0,
            'active_br'           => 0,
            'active_q'            => 0,
            'active_code'         => 0,
            'active_acronym'      => 0,
            'active_ins'          => 0,
            'active_del'          => 0,
            'active_inline_html'  => 0,
            'active_footnotes'    => 0,
            'active_wikiwords'    => 0,
            'active_macros'       => 0,
            'active_mark'         => 0,
            'active_aside'        => 0,
            'active_sup'          => 0,
            'active_sub'          => 0,
            'active_i'            => 0,
            'active_span'         => 0,
            'parse_pre'           => 0,
            'active_fr_syntax'    => 0,
        ]);

        # --BEHAVIOR-- coreInitWikiSimpleComment --
        # --BEHAVIOR-- coreWikiPostInit -- WikiToHtml
        $this->callBehavior('coreInitWikiSimpleComment', $this->wiki);
    }

    /**
     * Inits <var>wiki</var> property for blog comment.
     */
    public function initWikiComment(): void
    {
        $this->initWiki();

        $this->wiki->setOpts([
            'active_title'        => 0,
            'active_setext_title' => 0,
            'active_hr'           => 0,
            'active_lists'        => 1,
            'active_defl'         => 0,
            'active_quote'        => 1,
            'active_pre'          => 1,
            'active_empty'        => 0,
            'active_auto_br'      => 1,
            'active_auto_urls'    => 1,
            'active_urls'         => 1,
            'active_auto_img'     => 0,
            'active_img'          => 0,
            'active_anchor'       => 0,
            'active_em'           => 1,
            'active_strong'       => 1,
            'active_br'           => 1,
            'active_q'            => 1,
            'active_code'         => 1,
            'active_acronym'      => 1,
            'active_ins'          => 1,
            'active_del'          => 1,
            'active_footnotes'    => 0,
            'active_inline_html'  => 0,
            'active_wikiwords'    => 0,
            'active_macros'       => 0,
            'active_mark'         => 1,
            'active_aside'        => 0,
            'active_sup'          => 1,
            'active_sub'          => 1,
            'active_i'            => 1,
            'active_span'         => 0,
            'parse_pre'           => 0,
            'active_fr_syntax'    => 0,
        ]);

        # --BEHAVIOR-- coreInitWikiComment --
        # --BEHAVIOR-- coreWikiPostInit -- WikiToHtml
        $this->callBehavior('coreInitWikiComment', $this->wiki);
    }

    /**
     * Get info about a post:id wiki macro
     *
     * @param      string  $url      The post url
     * @param      string  $content  The content
     *
     * @return     array
     */
    public function wikiPostLink(string $url, string $content): array
    {
        if (!($this->blog instanceof dcBlog)) {
            return [];
        }

        $post_id = abs((int) substr($url, 5));
        if (!$post_id) {
            return [];
        }

        $post = $this->blog->getPosts(['post_id' => $post_id]);
        if ($post->isEmpty()) {
            return [];
        }

        $res = ['url' => $post->getURL()];

        if ($content != $url) {
            $res['title'] = Html::escapeHTML($post->post_title);
        }

        if ($content == '' || $content == $url) {
            $res['content'] = Html::escapeHTML($post->post_title);
        }

        if ($post->post_lang) {
            $res['lang'] = $post->post_lang;
        }

        return $res;
    }
    //@}

    /// @name Maintenance methods
    //@{
    /**
     * Creates default settings for active blog. Optionnal parameter
     * <var>defaults</var> replaces default params while needed.
     *
     * @param      array  $defaults  The defaults settings
     */
    public function blogDefaults(?array $defaults = null): void
    {
        if (!is_array($defaults)) {
            $defaults = [
                ['allow_comments', 'boolean', true,
                    'Allow comments on blog', ],
                ['allow_trackbacks', 'boolean', true,
                    'Allow trackbacks on blog', ],
                ['blog_timezone', 'string', 'Europe/London',
                    'Blog timezone', ],
                ['comments_nofollow', 'boolean', true,
                    'Add rel="nofollow" to comments URLs', ],
                ['comments_pub', 'boolean', true,
                    'Publish comments immediately', ],
                ['comments_ttl', 'integer', 0,
                    'Number of days to keep comments open (0 means no ttl)', ],
                ['copyright_notice', 'string', '', 'Copyright notice (simple text)'],
                ['date_format', 'string', '%A, %B %e %Y',
                    'Date format. See PHP strftime function for patterns', ],
                ['editor', 'string', '',
                    'Person responsible of the content', ],
                ['enable_html_filter', 'boolean', 0,
                    'Enable HTML filter', ],
                ['lang', 'string', 'en',
                    'Default blog language', ],
                ['media_exclusion', 'string', '/\.(phps?|pht(ml)?|phl|phar|.?html?|xml|js|htaccess)[0-9]*$/i',
                    'File name exclusion pattern in media manager. (PCRE value)', ],
                ['media_img_m_size', 'integer', 448,
                    'Image medium size in media manager', ],
                ['media_img_s_size', 'integer', 240,
                    'Image small size in media manager', ],
                ['media_img_t_size', 'integer', 100,
                    'Image thumbnail size in media manager', ],
                ['media_img_title_pattern', 'string', 'Title ;; Date(%b %Y) ;; separator(, )',
                    'Pattern to set image title when you insert it in a post', ],
                ['media_video_width', 'integer', 400,
                    'Video width in media manager', ],
                ['media_video_height', 'integer', 300,
                    'Video height in media manager', ],
                ['nb_post_for_home', 'integer', 20,
                    'Number of entries on first home page', ],
                ['nb_post_per_page', 'integer', 20,
                    'Number of entries on home pages and category pages', ],
                ['nb_post_per_feed', 'integer', 20,
                    'Number of entries on feeds', ],
                ['nb_comment_per_feed', 'integer', 20,
                    'Number of comments on feeds', ],
                ['post_url_format', 'string', '{y}/{m}/{d}/{t}',
                    'Post URL format. {y}: year, {m}: month, {d}: day, {id}: post id, {t}: entry title', ],
                ['public_path', 'string', 'public',
                    'Path to public directory, begins with a / for a full system path', ],
                ['public_url', 'string', '/public',
                    'URL to public directory', ],
                ['robots_policy', 'string', 'INDEX,FOLLOW',
                    'Search engines robots policy', ],
                ['short_feed_items', 'boolean', false,
                    'Display short feed items', ],
                ['theme', 'string', DC_DEFAULT_THEME,
                    'Blog theme', ],
                ['themes_path', 'string', 'themes',
                    'Themes root path', ],
                ['themes_url', 'string', '/themes',
                    'Themes root URL', ],
                ['time_format', 'string', '%H:%M',
                    'Time format. See PHP strftime function for patterns', ],
                ['tpl_allow_php', 'boolean', false,
                    'Allow PHP code in templates', ],
                ['tpl_use_cache', 'boolean', true,
                    'Use template caching', ],
                ['trackbacks_pub', 'boolean', true,
                    'Publish trackbacks immediately', ],
                ['trackbacks_ttl', 'integer', 0,
                    'Number of days to keep trackbacks open (0 means no ttl)', ],
                ['url_scan', 'string', 'query_string',
                    'URL handle mode (path_info or query_string)', ],
                ['use_smilies', 'boolean', false,
                    'Show smilies on entries and comments', ],
                ['no_search', 'boolean', false,
                    'Disable search', ],
                ['inc_subcats', 'boolean', false,
                    'Include sub-categories in category page and category posts feed', ],
                ['wiki_comments', 'boolean', false,
                    'Allow commenters to use a subset of wiki syntax', ],
                ['import_feed_url_control', 'boolean', true,
                    'Control feed URL before import', ],
                ['import_feed_no_private_ip', 'boolean', true,
                    'Prevent import feed from private IP', ],
                ['import_feed_ip_regexp', 'string', '',
                    'Authorize import feed only from this IP regexp', ],
                ['import_feed_port_regexp', 'string', '/^(80|443)$/',
                    'Authorize import feed only from this port regexp', ],
                ['jquery_needed', 'boolean', true,
                    'Load jQuery library', ],
                ['sleepmode_timeout', 'integer', 31536000,
                    'Sleep mode timeout', ],
            ];
        }

        $settings = new dcSettings(null);

        foreach ($defaults as $v) {
            $settings->system->put($v[0], $v[2], $v[1], $v[3], false, true);
        }
    }

    /**
     * Recreates entries search engine index.
     *
     * @param      mixed   $start  The start entry index
     * @param      mixed   $limit  The limit of entry to index
     *
     * @return     mixed   sum of <var>$start</var> and <var>$limit</var>
     */
    public function indexAllPosts($start = null, $limit = null)
    {
        $strReq = 'SELECT COUNT(post_id) ' .
        'FROM ' . $this->prefix . dcBlog::POST_TABLE_NAME;
        $rs    = new MetaRecord($this->con->select($strReq));
        $count = $rs->f(0);

        $strReq = 'SELECT post_id, post_title, post_excerpt_xhtml, post_content_xhtml ' .
        'FROM ' . $this->prefix . dcBlog::POST_TABLE_NAME . ' ';

        if ($start !== null && $limit !== null) {
            $strReq .= $this->con->limit($start, $limit);
        }

        $rs = new MetaRecord($this->con->select($strReq));

        $cur = $this->con->openCursor($this->prefix . dcBlog::POST_TABLE_NAME);

        while ($rs->fetch()) {
            $words = $rs->post_title . ' ' . $rs->post_excerpt_xhtml . ' ' .
            $rs->post_content_xhtml;

            $cur->post_words = implode(' ', Text::splitWords($words));
            $cur->update('WHERE post_id = ' . (int) $rs->post_id);
            $cur->clean();
        }

        if ($start + $limit > $count) {
            return;
        }

        return $start + $limit;
    }

    /**
     * Recreates comments search engine index.
     *
     * @param      int   $start  The start comment index
     * @param      int   $limit  The limit of comment to index
     *
     * @return     mixed   sum of <var>$start</var> and <var>$limit</var>
     */
    public function indexAllComments(?int $start = null, ?int $limit = null)
    {
        $strReq = 'SELECT COUNT(comment_id) ' .
        'FROM ' . $this->prefix . dcBlog::COMMENT_TABLE_NAME;
        $rs    = new MetaRecord($this->con->select($strReq));
        $count = $rs->f(0);

        $strReq = 'SELECT comment_id, comment_content ' .
        'FROM ' . $this->prefix . dcBlog::COMMENT_TABLE_NAME . ' ';

        if ($start !== null && $limit !== null) {
            $strReq .= $this->con->limit($start, $limit);
        }

        $rs = new MetaRecord($this->con->select($strReq));

        $cur = $this->con->openCursor($this->prefix . dcBlog::COMMENT_TABLE_NAME);

        while ($rs->fetch()) {
            $cur->comment_words = implode(' ', Text::splitWords($rs->comment_content));
            $cur->update('WHERE comment_id = ' . (int) $rs->comment_id);
            $cur->clean();
        }

        if ($start + $limit > $count) {
            return;
        }

        return $start + $limit;
    }

    /**
     * Reinits nb_comment and nb_trackback in post table.
     */
    public function countAllComments(): void
    {
        $sql_com = new UpdateStatement();
        $sql_com
            ->ref($sql_com->alias($this->prefix . dcBlog::POST_TABLE_NAME, 'P'));

        $sql_tb = clone $sql_com;

        $sql_count_com = new SelectStatement();
        $sql_count_com
            ->field($sql_count_com->count('C.comment_id'))
            ->from($sql_count_com->alias($this->prefix . dcBlog::COMMENT_TABLE_NAME, 'C'))
            ->where('C.post_id = P.post_id')
            ->and('C.comment_status = ' . (string) dcBlog::COMMENT_PUBLISHED);

        $sql_count_tb = clone $sql_count_com;

        $sql_count_com->and('C.comment_trackback <> 1');    // Count comment only
        $sql_count_tb->and('C.comment_trackback = 1');      // Count trackback only

        $sql_com->set('nb_comment = (' . $sql_count_com->statement() . ')');
        $sql_com->update();

        $sql_tb->set('nb_trackback = (' . $sql_count_tb->statement() . ')');
        $sql_tb->update();
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
     * @param      bool  $serve  The flag
     */
    public function enableRestServer(bool $serve = true)
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
     * @return     bool
     */
    public function serveRestRequests(): bool
    {
        return !file_exists(DC_UPGRADE) && DC_REST_SERVICES;
    }

    /**
     * Return elapsed time since script has been started
     *
     * @param      float   $mtime  timestamp (microtime format) to evaluate delta from current time is taken if null
     *
     * @return     float   The elapsed time.
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
