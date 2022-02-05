<?php
/**
 * @class Dotclear\Core\Core
 * @brief Dotclear core class
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
use Closure;

use Dotclear\Exception\CoreException;

use Dotclear\Core\Behaviors;
use Dotclear\Core\Session;
use Dotclear\Core\UrlHandler;
use Dotclear\Core\RestServer;
use Dotclear\Core\Meta;
use Dotclear\Core\Log;
use Dotclear\Core\Utils;
use Dotclear\Core\Media;
use Dotclear\Core\Blog;
use Dotclear\Core\Auth;
use Dotclear\Core\Settings;

use Dotclear\Core\Sql\SelectStatement;
use Dotclear\Core\Sql\DeleteStatement;

use Dotclear\Container\User as ContainerUser;

use Dotclear\Database\Connection;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Html\TraitError;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Html\Wiki2xhtml;
use Dotclear\Html\HtmlFilter;
use Dotclear\Utils\Text;
use Dotclear\File\Files;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Core
{
    use TraitError;

    /** @var Connection         Connetion instance */
    public $con;

    /** @var string             Database table prefix */
    public $prefix;

    /** @var Auth               Auth instance */
    public $auth;

    /** @var Session            Session instance */
    public $session;

    /** @var UrlHandler         UrlHandler instance */
    public $url;

    /** @var Wiki2xhtml         Wiki2xhtml instance */
    public $wiki2xhtml;

    /** @var Media              Media instance */
    public $media;

    /** @var RestServer         RestServer instance */
    public $rest;

    /** @var Log                Log instance */
    public $log;

    /** @var Meta               Meta instance */
    public $meta;

    /** @var Blog               Blog instance */
    public $blog;

    /** @var Behaviors          Behaviors instance */
    public $behaviors;

    /** @var array              versions container */
    private $versions   = null;

    /** @var array              formaters container */
    private $formaters  = [];

    /** @var array              top behaviors */
    protected static $top_behaviors = [];

    /** @var array              posts types container */
    private $post_types = [];

    /** @var Core               Core singleton instance */
    protected static $instance;

    /**
     * Disabled children constructor and direct instance
     */
    final protected function __construct()
    {
    }

    /*
     * @throws CoreException
     */
    final public function __clone()
    {
        throw new CoreException('Core instance can not be cloned.');
    }

    /**
     * @throws CoreException
     */
    final public function __sleep()
    {
        throw new CoreException('Core instance can not be serialized.');
    }

    /**
     * @throws CoreException
     */
    final public function __wakeup()
    {
        throw new CoreException('Core instance can not be deserialized.');
    }

    /**
     * Get core unique instance
     *
     * @param   string|null     $blog_id    Blog ID on first process call
     * @return  Core                        Core (Process) instance
     */
    final public static function coreInstance(?string $blog_id = null): Core
    {
        if (null === static::$instance) {
            # Two stage instanciation (construct then process)
            static::$instance = new static();
            static::$instance->process($blog_id);
        }

        return static::$instance;
    }

    /**
     * Start Dotclear process
     *
     * @param   string  $process    public/admin/install/...
     */
    public function process()
    {
        static::startStatistics();

        $this->behaviors = new Behaviors();
        $this->con       = $this->conInstance();
        $this->auth      = $this->authInstance();
        $this->session   = new Session($this->con, $this->prefix . 'session', DOTCLEAR_SESSION_NAME, null, null, DOTCLEAR_ADMIN_SSL, $this->getTTL());
        $this->url       = new UrlHandler();
        $this->rest      = new RestServer();
        $this->meta      = new Meta();
        $this->log       = new Log();

        $this->registerTopBehaviors();
    }

    /// @name Core init methods
    //@{
    /**
     * Get session ttl
     *
     * @return  string|null  The TTL
     */
    private function getTTL(): ?string
    {
        # Session time
        $ttl = DOTCLEAR_SESSION_TTL;
        if (!is_null($ttl)) {   // @phpstan-ignore-line
            $tll = (string) $ttl;
            if (substr(trim($ttl), 0, 1) != '-') {
                // We requires negative session TTL
                $ttl = '-' . trim($ttl);
            }
        }

        return $ttl;
    }

    /**
     * Instanciate database connection
     *
     * @throws  CoreException
     *
     * @return  Connection      Database connection instance
     */
    private function conInstance(): Connection
    {
        $prefix        = DOTCLEAR_DATABASE_PREFIX;
        $driver        = DOTCLEAR_DATABASE_DRIVER;
        $default_class = 'Dotclear\\Database\\Connection';

        # You can set DOTCLEAR_CON_CLASS to whatever you want.
        # Your new class *should* inherits Dotclear\Database\Connection class.
        $class = defined('DOTCLEAR_CON_CLASS') ? DOTCLEAR_CON_CLASS : $default_class ;

        if (!class_exists($class)) {
            throw new CoreException('Database connection class ' . $class . ' does not exist.');
        }

        if ($class != $default_class && !is_subclass_of($class, $default_class)) {
            throw new CoreException('Database connection class ' . $class . ' does not inherit ' . $default_class);
        }

        # PHP 7.0 mysql driver is obsolete, map to mysqli
        if ($driver === 'mysql') {
            $driver = 'mysqli';
        }

        # Set full namespace of distributed database driver
        if (in_array($driver, ['mysqli', 'mysqlimb4', 'pgsql', 'sqlite'])) {
            $class = 'Dotclear\\Database\\Driver\\' . ucfirst($driver) . '\\Connection';
        }

        # Check if database connection class exists
        if (!class_exists($class)) {
            trigger_error('Unable to load DB layer for ' . $driver, E_USER_ERROR);
            exit(1);
        }

        # Create connection instance
        $con = new $class(
            DOTCLEAR_DATABASE_HOST,
            DOTCLEAR_DATABASE_NAME,
            DOTCLEAR_DATABASE_USER,
            DOTCLEAR_DATABASE_PASSWORD,
            DOTCLEAR_DATABASE_PERSIST
        );

        # Define weak_locks for mysql
        if (in_array($driver, ['mysqli', 'mysqlimb4'])) {
            $con::$weak_locks = true;
        }

        # Define searchpath for postgresql
        if ($driver == 'pgsql') {
            $searchpath = explode('.', $prefix, 2);
            if (count($searchpath) > 1) {
                $prefix = $searchpath[1];
                $sql    = 'SET search_path TO ' . $searchpath[0] . ',public;';
                $con->execute($sql);
            }
        }

        # Set table prefix in core
        $this->prefix = $prefix;

        return $con;
    }

    /**
     * Instanciate authentication
     *
     * @throws  CoreException
     *
     * @return  Auth    Auth instance
     */
    private function authInstance(): Auth
    {
        # You can set DC_AUTH_CLASS to whatever you want.
        # Your new class *should* inherits Dotclear\Core\Auth class.
        $class = defined('DOTCLEAR_AUTH_CLASS') ? DOTCLEAR_AUTH_CLASS : __NAMESPACE__ . '\\Auth';

        # Check if auth class exists
        if (!class_exists($class)) {
            throw new CoreException('Authentication class ' . $class . ' does not exist.');
        }

        # Check if auth class inherit Dotclear auth class
        if ($class != __NAMESPACE__ . '\\Auth' && !is_subclass_of($class, __NAMESPACE__ . '\\Auth')) {
            throw new CoreException('Authentication class ' . $class . ' does not inherit ' . __NAMESPACE__ . '\\Auth.');
        }

        return new $class();
    }
    //@}

    /// @name Optionnal Core init methods
    //@{
    /**
     * Instanciate media manager into Core
     *
     * @param   bool    $reload     Force to reload instance
     *
     * @return  Media               Media instance
     */
    public function mediaInstance(bool $reload = false): Media
    {
        if (!($this->media instanceof Media) || $reload) {
            $this->media = new Media();
        }

        return $this->media;
    }
    //@}

    /// @name Blog init methods
    //@{
    /**
     * Sets the blog to use.
     *
     * @param   string  $blog_id    The blog ID
     */
    public function setBlog(string $blog_id): void
    {
        $this->blog = new Blog($blog_id);
    }

    /**
     * Unsets blog property
     */
    public function unsetBlog(): void
    {
        $this->blog = null;
    }
    //@}

    /// @name Blog status methods
    //@{
    /**
     * Gets all blog status.
     *
     * @return  array   An array of available blog status codes and names.
     */
    public function getAllBlogStatus(): array
    {
        return [
            1  => __('online'),
            0  => __('offline'),
            -1 => __('removed')
        ];
    }

    /**
     * Get blog status
     *
     * Returns a blog status name given to a code. This is intended to be
     * human-readable and will be translated, so never use it for tests.
     * If status code does not exist, returns <i>offline</i>.
     *
     * @param   int     $status_code    Status code
     *
     * @return  string  The blog status name.
     */
    public function getBlogStatus(int $status_code): string
    {
        $all = $this->getAllBlogStatus();

        return isset($all[$status_code]) ? $all[$status_code] : $all[0];
    }
    //@}

    /// @name Admin nonce secret methods
    //@{

    /**
     * Gets the nonce.
     *
     * @return  string  The nonce.
     */
    public function getNonce(): string
    {
        return $this->auth->cryptLegacy(session_id());
    }

    /**
     * Check the nonce
     *
     * @param   string  $secret     The nonce
     *
     * @return  bool    The success
     */
    public function checkNonce(string $secret): bool
    {
        return preg_match('/^([0-9a-f]{40,})$/i', $secret) ? $secret == $this->getNonce() : false;
    }

    /**
     * Get the nonce HTML code
     *
     * @return  string|null     HTML hidden form for nonce
     */
    public function formNonce(): ?string
    {
        return session_id() ? Form::hidden(['xd_check'], $this->getNonce()) : null;
    }
    //@}

    /// @name Text Formatters methods
    //@{
    /**
     * Add editor formater
     *
     * Adds a new text formater which will call the function <var>$callback</var> to
     * transform text. The function must be a valid callback and takes one
     * argument: the string to transform. It returns the transformed string.
     *
     * @param   string                  $editor     The editor identifier (LegacyEditor, dcCKEditor, ...)
     * @param   string                  $formater   The formater name
     * @param   string|array|Closure    $callback   The function to use, must be a valid and callable callback
     */
    public function addEditorFormater(string $editor, string $formater, string|array|Closure $callback): void
    {
        # Silently failed non callable function
        if (is_callable($callback)) {
            $this->formaters[$editor][$formater] = $callback;
        }
    }

    /**
     * Gets the editors list.
     *
     * @return  array   The editors.
     */
    public function getEditors(): array
    {
        $editors = [];

        foreach (array_keys($this->formaters) as $editor) {
            if (null !== ($module = $this->plugins->getModule($editor))) {
                $editors[$editor] = $module->name();
            }
        }

        return $editors;
    }

    /**
     * Gets the formaters.
     *
     * if @param editor is empty:
     * return all formaters sorted by actives editors
     *
     * if @param editor is not empty
     * return formaters for an editor if editor is active
     * return empty() array if editor is not active.
     * It can happens when a user choose an editor and admin deactivate that editor later
     *
     * @param   string  $editor     The editor identifier (LegacyEditor, dcCKEditor, ...)
     *
     * @return  array   The formaters.
     */
    public function getFormaters(string $editor = ''): array
    {
        $formaters_list = [];

        if (!empty($editor)) {
            if (isset($this->formaters[$editor])) {
                $formaters_list = array_keys($this->formaters[$editor]);
            }
        } else {
            foreach ($this->formaters as $editor => $formaters) {
                $formaters_list[$editor] = array_keys($formaters);
            }
        }

        return $formaters_list;
    }

    /**
     * Call editor formater. (format a string)
     *
     * If <var>$formater</var> is a valid formater, it returns <var>$str</var>
     * transformed using that formater.
     *
     * @param   string  $editor     The editor identifier (LegacyEditor, dcCKEditor, ...)
     * @param   string  $formater   The formater name
     * @param   string  $str        The string to transform
     *
     * @return  string  The formated string
     */
    public function callEditorFormater(string $editor, string $formater, string $str): string
    {
        if (isset($this->formaters[$editor]) && isset($this->formaters[$editor][$formater])) {
            return call_user_func($this->formaters[$editor][$formater], $str);
        }

        // Fallback with another editor if possible
        foreach ($this->formaters as $editor => $formaters) {
            if (array_key_exists($name, $formaters)) {
                return call_user_func($this->formaters[$editor][$name], $str);
            }
        }

        return $str;
    }
    //@}

    /// @name Behaviors methods
    //@{
    /**
     * Add Top Behavior statically before Core instance
     *
     * Dotclear\Core\Core::addTopBehavior('MyBehavior', 'MyFunction');
     * also work from Dotclear\Core\Prepend and other child class
     *
     * @param  string           $behavior   The behavior
     * @param  string|array     $callback   The function
     */
    public static function addTopBehavior(string $behavior, string|array $callback): void
    {
        array_push(self::$top_behaviors, [$behavior, $callback]);
    }

    /**
     * Register Top Behaviors into Core instance behaviors
     */
    protected function registerTopBehaviors(): void
    {
        foreach (self::$top_behaviors as $behavior) {
            $this->behaviors->add($behavior[0], $behavior[1]);
        }
    }
    //@}

    /// @name Post types URLs management
    //@{
    /**
     * Gets the post admin url.
     *
     * @param   string      $type       The type
     * @param   string|int  $post_id    The post identifier
     * @param   bool        $escaped    Escape the URL
     *
     * @return  string  The post admin url.
     */
    public function getPostAdminURL(string $type, string|int $post_id, bool $escaped = true): string
    {
        if (!isset($this->post_types[$type])) {
            $type = 'post';
        }

        $url = sprintf($this->post_types[$type]['admin_url'], $post_id);

        return $escaped ? Html::escapeURL($url) : $url;
    }

    /**
     * Gets the post public url.
     *
     * @param  string   $type       The type
     * @param  string   $post_url   The post url
     * @param  bool     $escaped    Escape the URL
     *
     * @return string   The post public url.
     */
    public function getPostPublicURL(string $type, string $post_url, bool $escaped = true): string
    {
        if (!isset($this->post_types[$type])) {
            $type = 'post';
        }

        $url = sprintf($this->post_types[$type]['public_url'], $post_url);

        return $escaped ? Html::escapeURL($url) : $url;
    }

    /**
     * Sets the post type.
     *
     * @param   string  $type           The type
     * @param   string  $admin_url      The admin url
     * @param   string  $public_url     The public url
     * @param   string  $label          The label
     */
    public function setPostType(string $type, string $admin_url, string $public_url, string $label = ''): void
    {
        $this->post_types[$type] = [
            'admin_url'  => $admin_url,
            'public_url' => $public_url,
            'label'      => ($label != '' ? $label : $type)
        ];
    }

    /**
     * Gets the post types.
     *
     * @return  array   The post types.
     */
    public function getPostTypes(): array
    {
        return $this->post_types;
    }
    //@}

    /// @name Versions management methods
    //@{
    /**
     * Gets the version of a module.
     *
     * @param   string  $module     The module
     *
     * @return  string|null  The version.
     */
    public function getVersion(string $module = 'core'): ?string
    {
        # Fetch versions if needed
        if (!is_array($this->versions)) {
            $sql = new SelectStatement('CoreCoreGetVersion');
            $sql
                ->columns(['module', 'version'])
                ->from($this->prefix . 'version');

            $rs = $sql->select();

            while ($rs->fetch()) {
                $this->versions[$rs->module] = $rs->version;
            }
        }

        return isset($this->versions[$module]) ? (string) $this->versions[$module] : null;
    }

    /**
     * Sets the version of a module.
     *
     * @param   string  $module     The module
     * @param   string  $version    The version
     */
    public function setVersion(string $module, string $version): void
    {
        $cur          = $this->con->openCursor($this->prefix . 'version');
        $cur->module  = $module;
        $cur->version = $version;

        if ($this->getVersion($module) === null) {
            $cur->insert();
        } else {
            $cur->update("WHERE module='" . $this->con->escape($module) . "'");
        }

        $this->versions[$module] = $version;
    }

    /**
     * Remove a module version entry
     *
     * @param   string  $module     The module
     */
    public function delVersion(string $module): void
    {
        $sql = new DeleteStatement('CoreCoreDelVersion');
        $sql->from($this->prefix . 'version')
            ->where("module = '" . $this->con->escape($module) . "'")
            ->delete();

        if (is_array($this->versions)) {
            unset($this->versions[$module]);
        }
    }
    //@}

    /// @name Users management methods
    //@{
    /**
     * Gets the user by its ID.
     *
     * @param   string  $user_id    The identifier
     *
     * @return  Record  The user.
     */
    public function getUser(string $user_id): Record
    {
        return $this->getUsers(['user_id' => $user_id]);
    }

    /**
     * Get users
     *
     * Returns a users list. <b>$params</b> is an array with the following
     * optionnal parameters:
     *
     * - <var>q</var>: search string (on user_id, user_name, user_firstname)
     * - <var>user_id</var>: user ID
     * - <var>order</var>: ORDER BY clause (default: user_id ASC)
     * - <var>limit</var>: LIMIT clause (should be an array ![limit,offset])
     *
     * @param   array|ArrayObject   $params         The parameters
     * @param   bool                $count_only     Count only results
     *
     * @return  Record  The users
     */
    public function getUsers(array|ArrayObject $params = [], bool $count_only = false): Record
    {
        if ($count_only) {
            $strReq = 'SELECT count(U.user_id) ' .
            'FROM ' . $this->prefix . 'user U ' .
                'WHERE NULL IS NULL ';
        } else {
            $strReq = 'SELECT U.user_id,user_super,user_status,user_pwd,user_change_pwd,' .
                'user_name,user_firstname,user_displayname,user_email,user_url,' .
                'user_desc, user_lang,user_tz, user_post_status,user_options, ' .
                'count(P.post_id) AS nb_post ';
            if (!empty($params['columns'])) {
                $strReq .= ',';
                if (is_array($params['columns'])) {
                    $strReq .= implode(',', $params['columns']);
                } else {
                    $strReq .= $params['columns'];
                }
                $strReq .= ' ';
            }
            $strReq .= 'FROM ' . $this->prefix . 'user U ' .
            'LEFT JOIN ' . $this->prefix . 'post P ON U.user_id = P.user_id ' .
                'WHERE NULL IS NULL ';
        }

        if (!empty($params['q'])) {
            $q = $this->con->escape(str_replace('*', '%', strtolower($params['q'])));
            $strReq .= 'AND (' .
                "LOWER(U.user_id) LIKE '" . $q . "' " .
                "OR LOWER(user_name) LIKE '" . $q . "' " .
                "OR LOWER(user_firstname) LIKE '" . $q . "' " .
                ') ';
        }

        if (!empty($params['user_id'])) {
            $strReq .= "AND U.user_id = '" . $this->con->escape($params['user_id']) . "' ";
        }

        if (!$count_only) {
            $strReq .= 'GROUP BY U.user_id,user_super,user_status,user_pwd,user_change_pwd,' .
                'user_name,user_firstname,user_displayname,user_email,user_url,' .
                'user_desc, user_lang,user_tz,user_post_status,user_options ';

            if (!empty($params['order'])) {
                if (preg_match('`^([^. ]+) (?:asc|desc)`i', $params['order'], $matches)) {
                    if (in_array($matches[1], ['user_id', 'user_name', 'user_firstname', 'user_displayname'])) {
                        $table_prefix = 'U.';
                    } else {
                        $table_prefix = ''; // order = nb_post (asc|desc)
                    }
                    $strReq .= 'ORDER BY ' . $table_prefix . $this->con->escape($params['order']) . ' ';
                } else {
                    $strReq .= 'ORDER BY ' . $this->con->escape($params['order']) . ' ';
                }
            } else {
                $strReq .= 'ORDER BY U.user_id ASC ';
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $strReq .= $this->con->limit($params['limit']);
        }
        $rs = $this->con->select($strReq);
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtUser');

        return $rs;
    }

    /**
     * Adds a new user. Takes a cursor as input and returns the new user ID.
     *
     * @param   Cursor  $cur    The user cursor
     *
     * @throws  CoreException
     *
     * @return  string
     */
    public function addUser(Cursor $cur): string
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        if ($cur->user_id == '') {
            throw new CoreException(__('No user ID given'));
        }

        if ($cur->user_pwd == '') {
            throw new CoreException(__('No password given'));
        }

        $this->getUserCursor($cur);

        if ($cur->user_creadt === null) {
            $cur->user_creadt = date('Y-m-d H:i:s');
        }

        $cur->insert();

        $this->auth->afterAddUser($cur);

        return $cur->user_id;
    }

    /**
     * Updates an existing user. Returns the user ID.
     *
     * @param   string  $user_id    The user identifier
     * @param   Cursor  $cur        The cursor
     *
     * @throws  CoreException
     *
     * @return  string
     */
    public function updUser(string $user_id, Cursor $cur): string
    {
        $this->getUserCursor($cur);

        if (($cur->user_id !== null || $user_id != $this->auth->userID()) && !$this->auth->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $cur->update("WHERE user_id = '" . $this->con->escape($user_id) . "' ");

        $this->auth->afterUpdUser($user_id, $cur);

        if ($cur->user_id !== null) {
            $user_id = $cur->user_id;
        }

        # Updating all user's blogs
        $rs = $this->con->select(
            'SELECT DISTINCT(blog_id) FROM ' . $this->prefix . 'post ' .
            "WHERE user_id = '" . $this->con->escape($user_id) . "' "
        );

        while ($rs->fetch()) {
            $b = new Blog($rs->blog_id);
            $b->triggerBlog();
            unset($b);
        }

        return $user_id;
    }

    /**
     * Deletes a user.
     *
     * @param   string  $user_id    The user identifier
     *
     * @throws  CoreException
     */
    public function delUser(string $user_id): void
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        if ($user_id == $this->auth->userID()) {
            return;
        }

        $rs = $this->getUser($user_id);

        if ($rs->nb_post > 0) {
            return;
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'user ' .
        "WHERE user_id = '" . $this->con->escape($user_id) . "' ";

        $this->con->execute($strReq);

        $this->auth->afterDelUser($user_id);
    }

    /**
     * Determines if user exists.
     *
     * @param   string  $user_id    The identifier
     *
     * @return  bool  True if user exists, False otherwise.
     */
    public function userExists(string $user_id): bool
    {
        $strReq = 'SELECT user_id ' .
        'FROM ' . $this->prefix . 'user ' .
        "WHERE user_id = '" . $this->con->escape($user_id) . "' ";

        $rs = $this->con->select($strReq);

        return !$rs->isEmpty();
    }

    /**
     * Returns all user permissions as an array which looks like:
     *
     * - [blog_id]
     * - [name] => Blog name
     * - [url] => Blog URL
     * - [p]
     * - [permission] => true
     * - ...
     *
     * @param   string  $user_id    The user identifier
     *
     * @return  array   The user permissions.
     */
    public function getUserPermissions(string $user_id): array
    {
        $strReq = 'SELECT B.blog_id, blog_name, blog_url, permissions ' .
        'FROM ' . $this->prefix . 'permissions P ' .
        'INNER JOIN ' . $this->prefix . 'blog B ON P.blog_id = B.blog_id ' .
        "WHERE user_id = '" . $this->con->escape($user_id) . "' ";

        $rs = $this->con->select($strReq);

        $res = [];

        while ($rs->fetch()) {
            $res[$rs->blog_id] = [
                'name' => $rs->blog_name,
                'url'  => $rs->blog_url,
                'p'    => $this->auth->parsePermissions($rs->permissions)
            ];
        }

        return $res;
    }

    /**
     * Sets user permissions.
     *
     * The <var>$perms</var> array looks like:
     * - [blog_id] => '|perm1|perm2|'
     * - ...
     *
     * @param   string     $user_id     The user identifier
     * @param   array      $perms       The permissions
     *
     * @throws  CoreException
     */
    public function setUserPermissions(string $user_id, array $perms): void
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'permissions ' .
        "WHERE user_id = '" . $this->con->escape($user_id) . "' ";

        $this->con->execute($strReq);

        foreach ($perms as $blog_id => $p) {
            $this->setUserBlogPermissions($user_id, $blog_id, $p, false);
        }
    }

    /**
     * Sets the user blog permissions.
     *
     * @param   string      $user_id        The user identifier
     * @param   string      $blog_id        The blog identifier
     * @param   array       $perms          The permissions
     * @param   bool        $delete_first   Delete permissions first
     *
     * @throws  CoreException
     */
    public function setUserBlogPermissions(string $user_id, string $blog_id, array $perms, bool $delete_first = true): void
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $no_perm = empty($perms);

        $perms = '|' . implode('|', array_keys($perms)) . '|';

        $cur = $this->con->openCursor($this->prefix . 'permissions');

        $cur->user_id     = $user_id;
        $cur->blog_id     = $blog_id;
        $cur->permissions = $perms;

        if ($delete_first || $no_perm) {
            $strReq = 'DELETE FROM ' . $this->prefix . 'permissions ' .
            "WHERE blog_id = '" . $this->con->escape($blog_id) . "' " .
            "AND user_id = '" . $this->con->escape($user_id) . "' ";

            $this->con->execute($strReq);
        }

        if (!$no_perm) {
            $cur->insert();
        }
    }

    /**
     * Sets the user default blog.
     *
     * This blog will be selected when user log in.
     *
     * @param   string  $user_id    The user identifier
     * @param   string  $blog_id    The blog identifier
     */
    public function setUserDefaultBlog(string $user_id, string $blog_id): void
    {
        $cur = $this->con->openCursor($this->prefix . 'user');

        $cur->user_default_blog = $blog_id;

        $cur->update("WHERE user_id = '" . $this->con->escape($user_id) . "'");
    }

    /**
     * Gets the user cursor.
     *
     * @param   Cursor  $cur    The user cursor
     *
     * @throws  CoreException
     */
    private function getUserCursor(Cursor $cur): void
    {
        if ($cur->isField('user_id')
            && !preg_match('/^[A-Za-z0-9@._-]{2,}$/', $cur->user_id)) {
            throw new CoreException(__('User ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if ($cur->user_url !== null && $cur->user_url != '') {
            if (!preg_match('|^http(s?)://|', $cur->user_url)) {
                $cur->user_url = 'http://' . $cur->user_url;
            }
        }

        if ($cur->isField('user_pwd')) {
            if (strlen($cur->user_pwd) < 6) {
                throw new CoreException(__('Password must contain at least 6 characters.'));
            }
            $cur->user_pwd = $this->auth->crypt($cur->user_pwd);
        }

        if ($cur->user_lang !== null && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $cur->user_lang)) {
            throw new CoreException(__('Invalid user language code'));
        }

        if ($cur->user_upddt === null) {
            $cur->user_upddt = date('Y-m-d H:i:s');
        }

        if ($cur->user_options !== null) {
            $cur->user_options = serialize((array) $cur->user_options);
        }
    }
    //@}

    /// @name Blog management methods
    //@{
    /**
     * Returns all blog permissions (users) as an array which looks like:
     *
     * - [user_id]
     * - [name] => User name
     * - [firstname] => User firstname
     * - [displayname] => User displayname
     * - [super] => (true|false) super admin
     * - [p]
     * - [permission] => true
     * - ...
     *
     * @param   string  $blog_id        The blog identifier
     * @param   bool    $with_super     Includes super admins in result
     *
     * @return  array   The blog permissions.
     */
    public function getBlogPermissions(string $blog_id, bool $with_super = true): array
    {
        $strReq = 'SELECT U.user_id AS user_id, user_super, user_name, user_firstname, ' .
        'user_displayname, user_email, permissions ' .
        'FROM ' . $this->prefix . 'user U ' .
        'JOIN ' . $this->prefix . 'permissions P ON U.user_id = P.user_id ' .
        "WHERE blog_id = '" . $this->con->escape($blog_id) . "' ";

        if ($with_super) {
            $strReq .= 'UNION ' .
            'SELECT U.user_id AS user_id, user_super, user_name, user_firstname, ' .
            'user_displayname, user_email, NULL AS permissions ' .
            'FROM ' . $this->prefix . 'user U ' .
                'WHERE user_super = 1 ';
        }

        $rs = $this->con->select($strReq);

        $res = [];

        while ($rs->fetch()) {
            $res[$rs->user_id] = [
                'name'        => $rs->user_name,
                'firstname'   => $rs->user_firstname,
                'displayname' => $rs->user_displayname,
                'email'       => $rs->user_email,
                'super'       => (bool) $rs->user_super,
                'p'           => $this->auth->parsePermissions($rs->permissions)
            ];
        }

        return $res;
    }

    /**
     * Gets the blog.
     *
     * @param   string  $blog_id    The blog identifier
     *
     * @return  Record|null         The blog.
     */
    public function getBlog(string $blog_id): ?Record
    {
        $blog = $this->getBlogs(['blog_id' => $blog_id]);

        if ($blog->isEmpty()) {
            return null;
        }

        return $blog;
    }

    /**
     * Returns a record of blogs.
     *
     * <b>$params</b> is an array with the following optionnal parameters:
     * - <var>blog_id</var>: Blog ID
     * - <var>q</var>: Search string on blog_id, blog_name and blog_url
     * - <var>limit</var>: limit results
     *
     * @param   array|ArrayObject   $params         The parameters
     * @param   bool                $count_only     Count only results
     *
     * @return  Record  The blogs.
     */
    public function getBlogs(array|ArrayObject $params = [], bool $count_only = false): Record
    {
        $join  = ''; // %1$s
        $where = ''; // %2$s

        if ($count_only) {
            $strReq = 'SELECT count(B.blog_id) ' .
            'FROM ' . $this->prefix . 'blog B ' .
                '%1$s ' .
                'WHERE NULL IS NULL ' .
                '%2$s ';
        } else {
            $strReq = 'SELECT B.blog_id, blog_uid, blog_url, blog_name, blog_desc, blog_creadt, ' .
                'blog_upddt, blog_status ';
            if (!empty($params['columns'])) {
                $strReq .= ',';
                if (is_array($params['columns'])) {
                    $strReq .= implode(',', $params['columns']);
                } else {
                    $strReq .= $params['columns'];
                }
                $strReq .= ' ';
            }
            $strReq .= 'FROM ' . $this->prefix . 'blog B ' .
                '%1$s ' .
                'WHERE NULL IS NULL ' .
                '%2$s ';

            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . $this->con->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY B.blog_id ASC ';
            }

            if (!empty($params['limit'])) {
                $strReq .= $this->con->limit($params['limit']);
            }
        }

        if ($this->auth->userID() && !$this->auth->isSuperAdmin()) {
            $join  = 'INNER JOIN ' . $this->prefix . 'permissions PE ON B.blog_id = PE.blog_id ';
            $where = "AND PE.user_id = '" . $this->con->escape($this->auth->userID()) . "' " .
                "AND (permissions LIKE '%|usage|%' OR permissions LIKE '%|admin|%' OR permissions LIKE '%|contentadmin|%') " .
                'AND blog_status IN (1,0) ';
        } elseif (!$this->auth->userID()) {
            $where = 'AND blog_status IN (1,0) ';
        }

        if (isset($params['blog_status']) && $params['blog_status'] !== '' && $this->auth->isSuperAdmin()) {
            $where .= 'AND blog_status = ' . (int) $params['blog_status'] . ' ';
        }

        if (isset($params['blog_id']) && $params['blog_id'] !== '') {
            if (!is_array($params['blog_id'])) {
                $params['blog_id'] = [$params['blog_id']];
            }
            $where .= 'AND B.blog_id ' . $this->con->in($params['blog_id']);
        }

        if (!empty($params['q'])) {
            $params['q'] = strtolower(str_replace('*', '%', $params['q']));
            $where .= 'AND (' .
            "LOWER(B.blog_id) LIKE '" . $this->con->escape($params['q']) . "' " .
            "OR LOWER(B.blog_name) LIKE '" . $this->con->escape($params['q']) . "' " .
            "OR LOWER(B.blog_url) LIKE '" . $this->con->escape($params['q']) . "' " .
                ') ';
        }

        $strReq = sprintf($strReq, $join, $where);

        $rs = $this->con->select($strReq);
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtBlog');

        return $rs;
    }

    /**
     * Adds a new blog.
     *
     * @param   cursor  $cur    The blog cursor
     *
     * @throws  CoreException
     */
    public function addBlog(Cursor $cur): void
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $this->getBlogCursor($cur);

        $cur->blog_creadt = date('Y-m-d H:i:s');
        $cur->blog_upddt  = date('Y-m-d H:i:s');
        $cur->blog_uid    = md5(uniqid());

        $cur->insert();
    }

    /**
     * Updates a given blog.
     *
     * @param   string  $blog_id    The blog identifier
     * @param   Cursor  $cur        The cursor
     */
    public function updBlog(string $blog_id, Cursor $cur): void
    {
        $this->getBlogCursor($cur);

        $cur->blog_upddt = date('Y-m-d H:i:s');

        $cur->update("WHERE blog_id = '" . $this->con->escape($blog_id) . "'");
    }

    /**
     * Gets the blog cursor.
     *
     * @param   Cursor  $cur    The cursor
     *
     * @throws  CoreException
     */
    private function getBlogCursor(Cursor $cur): void
    {
        if (($cur->blog_id !== null
            && !preg_match('/^[A-Za-z0-9._-]{2,}$/', $cur->blog_id)) || (!$cur->blog_id)) {
            throw new CoreException(__('Blog ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (($cur->blog_name !== null && $cur->blog_name == '') || (!$cur->blog_name)) {
            throw new CoreException(__('No blog name'));
        }

        if (($cur->blog_url !== null && $cur->blog_url == '') || (!$cur->blog_url)) {
            throw new CoreException(__('No blog URL'));
        }

        if ($cur->blog_desc !== null) {
            $cur->blog_desc = Html::clean($cur->blog_desc);
        }
    }

    /**
     * Removes a given blog.
     * @warning This will remove everything related to the blog (posts,
     * categories, comments, links...)
     *
     * @param   string  $blog_id    The blog identifier
     *
     * @throws  CoreException
     */
    public function delBlog(string $blog_id): void
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new CoreException(__('You are not an administrator'));
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'blog ' .
        "WHERE blog_id = '" . $this->con->escape($blog_id) . "' ";

        $this->con->execute($strReq);
    }

    /**
     * Determines if blog exists.
     *
     * @param   string  $blog_id    The blog identifier
     *
     * @return  bool    True if blog exists, False otherwise.
     */
    public function blogExists(string $blog_id): bool
    {
        $strReq = 'SELECT blog_id ' .
        'FROM ' . $this->prefix . 'blog ' .
        "WHERE blog_id = '" . $this->con->escape($blog_id) . "' ";

        $rs = $this->con->select($strReq);

        return !$rs->isEmpty();
    }

    /**
     * Counts the number of blog posts.
     *
     * @param   string          $blog_id    The blog identifier
     * @param   string|null     $post_type  The post type
     *
     * @return  int     Number of blog posts.
     */
    public function countBlogPosts(string $blog_id, ?string $post_type = null): int
    {
        $strReq = 'SELECT COUNT(post_id) ' .
        'FROM ' . $this->prefix . 'post ' .
        "WHERE blog_id = '" . $this->con->escape($blog_id) . "' ";

        if ($post_type) {
            $strReq .= "AND post_type = '" . $this->con->escape($post_type) . "' ";
        }

        return (int) $this->con->select($strReq)->f(0);
    }
    //@}

    /// @name HTML Filter methods
    //@{
    /**
     * Filter HTML string
     *
     * Calls HTML filter to drop bad tags and produce valid XHTML output (if
     * tidy extension is present). If <b>enable_html_filter</b> blog setting is
     * false, returns not filtered string.
     *
     * @param   string  $str    The string
     *
     * @return  string
     */
    public function HTMLfilter(string $str): string
    {
        if ($this->blog instanceof Blog && !$this->blog->settings->system->enable_html_filter) {
            return $str;
        }

        $options = new ArrayObject([
            'keep_aria' => false,
            'keep_data' => false,
            'keep_js'   => false
        ]);

        # --BEHAVIOR-- HTMLfilter, \ArrayObject
        $this->behaviors->call('HTMLfilter', $options);

        $filter = new HtmlFilter($options['keep_aria'], $options['keep_data'], $options['keep_js']);
        $str    = trim($filter->apply($str));

        return $str;
    }
    //@}

    /// @name wiki2xhtml methods
    //@{

    /**
     * Initializes the wiki2xhtml methods.
     */
    private function initWiki(): void
    {
        $this->wiki2xhtml = new Wiki2xhtml();
    }

    /**
     * Returns a transformed string with wiki2xhtml.
     *
     * @param   string  $str    The string
     *
     * @return  string
     */
    public function wikiTransform(string $str): string
    {
        if (!($this->wiki2xhtml instanceof Wiki2xhtml)) {
            $this->initWiki();
        }

        return $this->wiki2xhtml->transform($str);
    }

    /**
     * Inits <var>wiki2xhtml</var> property for blog post.
     */
    public function initWikiPost(): void
    {
        $this->initWiki();

        $this->wiki2xhtml->setOpts([
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
            'img_style_center'    => 'display:table; margin:0 auto;'
        ]);

        $this->wiki2xhtml->registerFunction('url:post', [$this, 'wikiPostLink']);

        # --BEHAVIOR-- coreInitWikiPost, Dotclear\Html\Wiki2xhtml
        $this->behaviors->call('coreInitWikiPost', $this->wiki2xhtml);
    }

    /**
     * Inits <var>wiki2xhtml</var> property for simple blog comment (basic syntax).
     */
    public function initWikiSimpleComment(): void
    {
        $this->initWiki();

        $this->wiki2xhtml->setOpts([
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
            'active_fr_syntax'    => 0
        ]);

        # --BEHAVIOR-- coreInitWikiSimpleComment, Dotclear\Html\Wiki2xhtml
        $this->behaviors->call('coreInitWikiSimpleComment', $this->wiki2xhtml);
    }

    /**
     * Inits <var>wiki2xhtml</var> property for blog comment.
     */
    public function initWikiComment(): void
    {
        $this->initWiki();

        $this->wiki2xhtml->setOpts([
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
            'active_fr_syntax'    => 0
        ]);

        # --BEHAVIOR-- coreInitWikiComment, Dotclear\Html\Wiki2xhtml
        $this->behaviors->call('coreInitWikiComment', $this->wiki2xhtml);
    }

    /**
     * Get info about a post:id wiki macro
     *
     * @param   string  $url        The post url
     * @param   string  $content    The content
     *
     * @return  array
     */
    public function wikiPostLink(string $url, string $content): array
    {
        if (!($this->blog instanceof Blog)) {
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

        $res        = ['url' => $post->getURL()];
        $post_title = $post->post_title;

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
     * Get blog default settings
     *
     * Creates default settings for active blog. Optionnal parameter
     * <var>defaults</var> replaces default params while needed.
     *
     * @param   array   $defaults   The defaults settings
     */
    public function blogDefaults($defaults = null): void
    {
        if (!is_array($defaults)) {
            $defaults = [
                ['allow_comments', 'boolean', true,
                    'Allow comments on blog'],
                ['allow_trackbacks', 'boolean', true,
                    'Allow trackbacks on blog'],
                ['blog_timezone', 'string', 'Europe/London',
                    'Blog timezone'],
                ['comments_nofollow', 'boolean', true,
                    'Add rel="nofollow" to comments URLs'],
                ['comments_pub', 'boolean', true,
                    'Publish comments immediately'],
                ['comments_ttl', 'integer', 0,
                    'Number of days to keep comments open (0 means no ttl)'],
                ['copyright_notice', 'string', '', 'Copyright notice (simple text)'],
                ['date_format', 'string', '%A, %B %e %Y',
                    'Date format. See PHP strftime function for patterns'],
                ['editor', 'string', '',
                    'Person responsible of the content'],
                ['enable_html_filter', 'boolean', 0,
                    'Enable HTML filter'],
                ['enable_xmlrpc', 'boolean', 0,
                    'Enable XML/RPC interface'],
                ['lang', 'string', 'en',
                    'Default blog language'],
                ['media_exclusion', 'string', '/\.(phps?|pht(ml)?|phl|.?html?|xml|js|htaccess)[0-9]*$/i',
                    'File name exclusion pattern in media manager. (PCRE value)'],
                ['media_img_m_size', 'integer', 448,
                    'Image medium size in media manager'],
                ['media_img_s_size', 'integer', 240,
                    'Image small size in media manager'],
                ['media_img_t_size', 'integer', 100,
                    'Image thumbnail size in media manager'],
                ['media_img_title_pattern', 'string', 'Title ;; Date(%b %Y) ;; separator(, )',
                    'Pattern to set image title when you insert it in a post'],
                ['media_video_width', 'integer', 400,
                    'Video width in media manager'],
                ['media_video_height', 'integer', 300,
                    'Video height in media manager'],
                ['nb_post_for_home', 'integer', 20,
                    'Number of entries on first home page'],
                ['nb_post_per_page', 'integer', 20,
                    'Number of entries on home pages and category pages'],
                ['nb_post_per_feed', 'integer', 20,
                    'Number of entries on feeds'],
                ['nb_comment_per_feed', 'integer', 20,
                    'Number of comments on feeds'],
                ['post_url_format', 'string', '{y}/{m}/{d}/{t}',
                    'Post URL format. {y}: year, {m}: month, {d}: day, {id}: post id, {t}: entry title'],
                ['public_path', 'string', 'public',
                    'Path to public directory, begins with a / for a full system path'],
                ['public_url', 'string', '/public',
                    'URL to public directory'],
                ['robots_policy', 'string', 'INDEX,FOLLOW',
                    'Search engines robots policy'],
                ['short_feed_items', 'boolean', false,
                    'Display short feed items'],
                ['theme', 'string', 'berlin',
                    'Blog theme'],
                ['time_format', 'string', '%H:%M',
                    'Time format. See PHP strftime function for patterns'],
                ['tpl_allow_php', 'boolean', false,
                    'Allow PHP code in templates'],
                ['tpl_use_cache', 'boolean', true,
                    'Use template caching'],
                ['trackbacks_pub', 'boolean', true,
                    'Publish trackbacks immediately'],
                ['trackbacks_ttl', 'integer', 0,
                    'Number of days to keep trackbacks open (0 means no ttl)'],
                ['url_scan', 'string', 'query_string',
                    'URL handle mode (path_info or query_string)'],
                ['use_smilies', 'boolean', false,
                    'Show smilies on entries and comments'],
                ['no_search', 'boolean', false,
                    'Disable search'],
                ['inc_subcats', 'boolean', false,
                    'Include sub-categories in category page and category posts feed'],
                ['wiki_comments', 'boolean', false,
                    'Allow commenters to use a subset of wiki syntax'],
                ['import_feed_url_control', 'boolean', true,
                    'Control feed URL before import'],
                ['import_feed_no_private_ip', 'boolean', true,
                    'Prevent import feed from private IP'],
                ['import_feed_ip_regexp', 'string', '',
                    'Authorize import feed only from this IP regexp'],
                ['import_feed_port_regexp', 'string', '/^(80|443)$/',
                    'Authorize import feed only from this port regexp'],
                ['jquery_needed', 'boolean', true,
                    'Load jQuery library']
            ];
        }

        $settings = new Settings(null);
        $settings->addNamespace('system');

        foreach ($defaults as $v) {
            $settings->system->put($v[0], $v[2], $v[1], $v[3], false, true);
        }
    }

    /**
     * Recreates entries search engine index.
     *
     * @param   int|null    $start  The start entry index
     * @param   int|null    $limit  The limit of entry to index
     *
     * @return  int|null    Sum of <var>$start</var> and <var>$limit</var>
     */
    public function indexAllPosts(?int $start = null, ?int $limit = null): ?int
    {
        $strReq = 'SELECT COUNT(post_id) ' .
        'FROM ' . $this->prefix . 'post';
        $rs    = $this->con->select($strReq);
        $count = $rs->f(0);

        $strReq = 'SELECT post_id, post_title, post_excerpt_xhtml, post_content_xhtml ' .
        'FROM ' . $this->prefix . 'post ';

        if ($start !== null && $limit !== null) {
            $strReq .= $this->con->limit($start, $limit);
        }

        $rs = $this->con->select($strReq, true);

        $cur = $this->con->openCursor($this->prefix . 'post');

        while ($rs->fetch()) {
            $words = $rs->post_title . ' ' . $rs->post_excerpt_xhtml . ' ' .
            $rs->post_content_xhtml;

            $cur->post_words = implode(' ', Text::splitWords($words));
            $cur->update('WHERE post_id = ' . (int) $rs->post_id);
            $cur->clean();
        }

        if ($start + $limit > $count) {
            return null;
        }

        return $start + $limit;
    }

    /**
     * Recreates comments search engine index.
     *
     * @param  int|null     $start  The start comment index
     * @param  int|null     $limit  The limit of comment to index
     *
     * @return int|null     Sum of <var>$start</var> and <var>$limit</var>
     */
    public function indexAllComments(?int $start = null, ?int $limit = null): ?int
    {
        $strReq = 'SELECT COUNT(comment_id) ' .
        'FROM ' . $this->prefix . 'comment';
        $rs    = $this->con->select($strReq);
        $count = $rs->f(0);

        $strReq = 'SELECT comment_id, comment_content ' .
        'FROM ' . $this->prefix . 'comment ';

        if ($start !== null && $limit !== null) {
            $strReq .= $this->con->limit($start, $limit);
        }

        $rs = $this->con->select($strReq);

        $cur = $this->con->openCursor($this->prefix . 'comment');

        while ($rs->fetch()) {
            $cur->comment_words = implode(' ', Text::splitWords($rs->comment_content));
            $cur->update('WHERE comment_id = ' . (int) $rs->comment_id);
            $cur->clean();
        }

        if ($start + $limit > $count) {
            return null;
        }

        return $start + $limit;
    }

    /**
     * Reinits nb_comment and nb_trackback in post table.
     */
    public function countAllComments(): void
    {
        $updCommentReq = 'UPDATE ' . $this->prefix . 'post P ' .
        'SET nb_comment = (' .
        'SELECT COUNT(C.comment_id) from ' . $this->prefix . 'comment C ' .
            'WHERE C.post_id = P.post_id AND C.comment_trackback <> 1 ' .
            'AND C.comment_status = 1 ' .
            ')';
        $updTrackbackReq = 'UPDATE ' . $this->prefix . 'post P ' .
        'SET nb_trackback = (' .
        'SELECT COUNT(C.comment_id) from ' . $this->prefix . 'comment C ' .
            'WHERE C.post_id = P.post_id AND C.comment_trackback = 1 ' .
            'AND C.comment_status = 1 ' .
            ')';
        $this->con->execute($updCommentReq);
        $this->con->execute($updTrackbackReq);
    }

    /**
     * Empty templates cache directory
     */
    public static function emptyTemplatesCache(): void
    {
        if (is_dir(static::path(DOTCLEAR_CACHE_DIR, 'cbtpl'))) {
            Files::deltree(static::path(DOTCLEAR_CACHE_DIR, 'cbtpl'));
        }
    }

    /**
     * Save start time and memory usage
     */
    public static function startStatistics(): void
    {
        # Timer and memory usage for stats and dev
        if (!defined('DOTCLEAR_START_TIME')) {
            define('DOTCLEAR_START_TIME',
                microtime(true)
            );
        }
        if (!defined('DOTCLEAR_START_MEMORY')) {
            define('DOTCLEAR_START_MEMORY',
                memory_get_usage(false)
            );
        }
    }

    /**
     * Return elapsed time since script has been started
     *
     * @param   int|null    $mtime  Timestamp (microtime format) to evaluate delta from current time is taken if null
     *
     * @return  string  The elapsed time.
     */
    public static function getElapsedTime(?int $mtime = null): string
    {
        $start = defined('DOTCLEAR_START_TIME') ? DOTCLEAR_START_TIME : microtime(true);

        return strval(round(($mtime === null ? microtime(true) - $start : $mtime - $start), 5));
    }

    /**
     * Return memory consumed since script has been started
     *
     * @param   int|null    $mmem   Memory usage to evaluate
     * delta from current memory usage is taken if null
     *
     * @return  string  The consumed memory.
     */
    public static function getConsumedMemory(?int $mmem = null): string
    {
        $start = defined('DOTCLEAR_START_MEMORY') ? DOTCLEAR_START_MEMORY : memory_get_usage(false);

        $usage = $mmem === null ? memory_get_usage(false) - $start : $mmem - $start;
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return strval(round($usage / pow(1024, ($i = floor(log($usage, 1024)))), 2)) . ' ' . $unit[$i];
    }

    /**
     * Join folder function
     *
     * Starting from Dotclear root directory
     *
     * @param  string   $args   One argument per folder
     *
     * @return string   Directory
     */
    public static function root(string ...$args): string
    {
        if (!defined('DOTCLEAR_ROOT_DIR')) {
            define('DOTCLEAR_ROOT_DIR', __DIR__);
        }

        return implode(DIRECTORY_SEPARATOR, array_merge([DOTCLEAR_ROOT_DIR], $args));
    }

    /**
     * Join folder function
     *
     * @param  string   $args   One argument per folder
     *
     * @return string   Directory
     */
    public static function path(string ...$args): string
    {
        return implode(DIRECTORY_SEPARATOR, $args);
    }

    /**
     * Join sub namespace function
     *
     * @param  string   $args   One argument per sub namespace
     *
     * @return string   Namespace
     */
    public static function ns(string ...$args): string
    {
        return implode('\\', $args);
    }
    //@}
}
