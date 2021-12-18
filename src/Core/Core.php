<?php
/**
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

use Dotclear\Utils\Form;
use Dotclear\Utils\Html;

class Core
{
    /** @var Connection         Connetion instance */
    public $con;

    /** @var string             Database table prefix */
    public $prefix;

    /** @var Error              Error instance */
    public $error;

    /** @var Auth               Auth instance */
    public $auth;

    /** @var Session            Session instance */
    public $session;

    /** @var UrlHandler         UrlHandler instance */
    public $url;

    /** @var RestServer         RestServer instance */
    public $rest;

    /** @var Log                Log instance */
    public $log;

    /** @var Meta               Meta instance */
    public $meta;

    /** @var Blog               Blog instance */
    public $blog;

    /** @var array              Behaviors */
    private $behaviors = [];

    /**
     * Start Dotclear process
     *
     * @param  string $process public/admin/install/...
     */
    public function __construct()
    {
        $this->con     = $this->conInstance();
        $this->error   = new Error();
        $this->auth    = $this->authInstance();
        $this->session = new session($this->con, $this->prefix . 'session', DOTCLEAR_SESSION_NAME, null, null, DOTCLEAR_ADMIN_SSL, $this->getTTL());
        $this->url     = new UrlHandler($this);
        $this->plugins = null;//new Plugins($this);
        $this->rest    = new RestServer($this);
        $this->meta    = new Meta($this);
        $this->log     = new Log($this);
    }

    /// @name Core init methods
    //@{
    private function getTTL()
    {
        /* Session time */
        $ttl = DOTCLEAR_SESSION_TTL;
        if (!is_null($ttl)) {   // @phpstan-ignore-line
            if (substr(trim($ttl), 0, 1) != '-') {
                // We requires negative session TTL
                $ttl = '-' . trim($ttl);
            }
        }

        return $ttl;
    }

    private function conInstance()
    {
        $prefix        = DOTCLEAR_DATABASE_PREFIX;
        $driver        = DOTCLEAR_DATABASE_DRIVER;
        $default_class = 'Dotclear\\Database\\Connection';

        # You can set DC_Con_CLASS to whatever you want.
        # Your new class *should* inherits Dotclear\Database\Connection class.
        $class = defined('DOTCLEAR_CON_CLASS') ? DOTCLEAR_CON_CLASS : $default_class ;

        if (!class_exists($class)) {
            throw new Exception('Database connection class ' . $class . ' does not exist.');
        }

        if ($class != $default_class && !is_subclass_of($class, $default_class)) {
            throw new Exception('Database connection class ' . $class . ' does not inherit ' . $default_class);
        }

        /* PHP 7.0 mysql driver is obsolete, map to mysqli */
        if ($driver === 'mysql') {
            $driver = 'mysqli';
        }

        /* Set full namespace of distributed database driver */
        if (in_array($driver, ['mysqli', 'mysqlimb4', 'pgsql', 'sqlite'])) {
            $class = 'Dotclear\\Database\\Driver\\' . ucfirst($driver) . '\\Connection';
        }

        /* Check if database connection class exists */
        if (!class_exists($class)) {
            trigger_error('Unable to load DB layer for ' . $driver, E_USER_ERROR);
            exit(1);
        }

        /* create connection instance */
        $con = new $class(
            DOTCLEAR_DATABASE_HOST,
            DOTCLEAR_DATABASE_NAME,
            DOTCLEAR_DATABASE_USER,
            DOTCLEAR_DATABASE_PASSWORD,
            DOTCLEAR_DATABASE_PERSIST
        );

        /* define weak_locks for mysql */
        if (in_array($driver, ['mysqli', 'mysqlimb4'])) {
            $con::$weak_locks = true;
        }

        /* define searchpath for postgresql */
        if ($driver == 'pgsql') {
            $searchpath = explode('.', $prefix, 2);
            if (count($searchpath) > 1) {
                $prefix = $searchpath[1];
                $sql    = 'SET search_path TO ' . $searchpath[0] . ',public;';
                $con->execute($sql);
            }
        }

        /* set table prefix in core */
        $this->prefix = $prefix;

        return $con;
    }

    private function authInstance()
    {
        # You can set DC_AUTH_CLASS to whatever you want.
        # Your new class *should* inherits Dotclear\Core\Auth class.
        if (!defined('DOTCLEAR_AUTH_CLASS')) {
            $class = __NAMESPACE__ . '\\Auth';
        } else {
            $class = DOTCLEAR_AUTH_CLASS;
        }

        if (!class_exists($class)) {
            throw new Exception('Authentication class ' . $class . ' does not exist.');
        }

        if ($class != __NAMESPACE__ . '\\Auth' && !is_subclass_of($class, __NAMESPACE__ . '\\Auth')) {
            throw new Exception('Authentication class ' . $class . ' does not inherit ' . __NAMESPACE__ . '\\Auth.');
        }

        return new $class($this);
    }
    //@}

    /// @name Blog init methods
    //@{
    /**
     * Sets the blog to use.
     *
     * @param      string  $id     The blog ID
     */
    public function setBlog($id)
    {
        $this->blog = new Blog($this, $id);
    }

    /**
     * Unsets blog property
     */
    public function unsetBlog()
    {
        $this->blog = null;
    }
    //@}

    /// @name Blog status methods
    //@{
    /**
     * Gets all blog status.
     *
     * @return     array  An array of available blog status codes and names.
     */
    public function getAllBlogStatus()
    {
        return [
            1  => __('online'),
            0  => __('offline'),
            -1 => __('removed')
        ];
    }

    /**
     * Returns a blog status name given to a code. This is intended to be
     * human-readable and will be translated, so never use it for tests.
     * If status code does not exist, returns <i>offline</i>.
     *
     * @param      integer  $s      Status code
     *
     * @return     string   The blog status name.
     */
    public function getBlogStatus($s)
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
     * @return     string  The nonce.
     */
    public function getNonce()
    {
        return $this->auth->cryptLegacy(session_id());
    }

    /**
     * Check the nonce
     *
     * @param      string  $secret  The nonce
     *
     * @return     bool
     */
    public function checkNonce($secret)
    {
        // 40 alphanumeric characters min
        if (!preg_match('/^([0-9a-f]{40,})$/i', $secret)) {
            return false;
        }

        return $secret == $this->auth->cryptLegacy(session_id());
    }

    /**
     * Get the nonce HTML code
     *
     * @return     mixed
     */
    public function formNonce()
    {
        if (!session_id()) {
            return;
        }

        return Form::hidden(['xd_check'], $this->getNonce());
    }
    //@}

    /// @name Behaviors methods
    //@{
    /**
     * Adds a new behavior to behaviors stack. <var>$func</var> must be a valid
     * and callable callback.
     *
     * @param      string    $behavior  The behavior
     * @param      callable  $func      The function
     */
    public function addBehavior($behavior, $func)
    {
        if (is_callable($func)) {
            $this->behaviors[$behavior][] = $func;
        }
    }

    /**
     * Determines if behavior exists in behaviors stack.
     *
     * @param      string  $behavior  The behavior
     *
     * @return     bool    True if behavior exists, False otherwise.
     */
    public function hasBehavior($behavior)
    {
        return isset($this->behaviors[$behavior]);
    }

    /**
     * Gets the behaviors stack (or part of).
     *
     * @param      string  $behavior  The behavior
     *
     * @return     mixed   The behaviors.
     */
    public function getBehaviors($behavior = '')
    {
        if (empty($this->behaviors)) {
            return;
        }

        if ($behavior == '') {
            return $this->behaviors;
        } elseif (isset($this->behaviors[$behavior])) {
            return $this->behaviors[$behavior];
        }

        return [];
    }

    /**
     * Calls every function in behaviors stack for a given behavior and returns
     * concatened result of each function.
     *
     * Every parameters added after <var>$behavior</var> will be pass to
     * behavior calls.
     *
     * @param      string  $behavior  The behavior
     * @param      mixed   ...$args   The arguments
     *
     * @return     mixed   Behavior concatened result
     */
    public function callBehavior($behavior, ...$args)
    {
        if (isset($this->behaviors[$behavior])) {
            $res = '';

            foreach ($this->behaviors[$behavior] as $f) {
                $res .= call_user_func_array($f, $args);
            }

            return $res;
        }
    }
    //@}

    /// @name Versions management methods
    //@{
    /**
     * Gets the version of a module.
     *
     * @param      string  $module  The module
     *
     * @return     mixed  The version.
     */
    public function getVersion($module = 'core')
    {
        # Fetch versions if needed
        if (!is_array($this->versions)) {
            $strReq = 'SELECT module, version FROM ' . $this->prefix . 'version';
            $rs     = $this->con->select($strReq);

            while ($rs->fetch()) {
                $this->versions[$rs->module] = $rs->version;
            }
        }

        if (isset($this->versions[$module])) {
            return $this->versions[$module];
        }
    }

    /**
     * Sets the version of a module.
     *
     * @param      string  $module   The module
     * @param      string  $version  The version
     */
    public function setVersion($module, $version)
    {
        $cur_version = $this->getVersion($module);

        $cur          = $this->con->openCursor($this->prefix . 'version');
        $cur->module  = (string) $module;
        $cur->version = (string) $version;

        if ($cur_version === null) {
            $cur->insert();
        } else {
            $cur->update("WHERE module='" . $this->con->escape($module) . "'");
        }

        $this->versions[$module] = $version;
    }

    /**
     * Remove a module version entry
     *
     * @param      string  $module  The module
     */
    public function delVersion($module)
    {
        $strReq = 'DELETE FROM ' . $this->prefix . 'version ' .
        "WHERE module = '" . $this->con->escape($module) . "' ";

        $this->con->execute($strReq);

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
     * @param      string  $id     The identifier
     *
     * @return     record  The user.
     */
    public function getUser($id)
    {
        $params['user_id'] = $id;

        return $this->getUsers($params);
    }

    /**
     * Returns a users list. <b>$params</b> is an array with the following
     * optionnal parameters:
     *
     * - <var>q</var>: search string (on user_id, user_name, user_firstname)
     * - <var>user_id</var>: user ID
     * - <var>order</var>: ORDER BY clause (default: user_id ASC)
     * - <var>limit</var>: LIMIT clause (should be an array ![limit,offset])
     *
     * @param      array|ArrayObject    $params      The parameters
     * @param      bool                 $count_only  Count only results
     *
     * @return     record  The users.
     */
    public function getUsers($params = [], $count_only = false)
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
     * @param      cursor     $cur    The user cursor
     *
     * @throws     Exception
     *
     * @return     string
     */
    public function addUser($cur)
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        if ($cur->user_id == '') {
            throw new Exception(__('No user ID given'));
        }

        if ($cur->user_pwd == '') {
            throw new Exception(__('No password given'));
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
     * @param      string     $id     The user identifier
     * @param      cursor     $cur    The cursor
     *
     * @throws     Exception
     *
     * @return     string
     */
    public function updUser($id, $cur)
    {
        $this->getUserCursor($cur);

        if (($cur->user_id !== null || $id != $this->auth->userID()) && !$this->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        $cur->update("WHERE user_id = '" . $this->con->escape($id) . "' ");

        $this->auth->afterUpdUser($id, $cur);

        if ($cur->user_id !== null) {
            $id = $cur->user_id;
        }

        # Updating all user's blogs
        $rs = $this->con->select(
            'SELECT DISTINCT(blog_id) FROM ' . $this->prefix . 'post ' .
            "WHERE user_id = '" . $this->con->escape($id) . "' "
        );

        while ($rs->fetch()) {
            $b = new dcBlog($this, $rs->blog_id);
            $b->triggerBlog();
            unset($b);
        }

        return $id;
    }

    /**
     * Deletes a user.
     *
     * @param      string     $id     The user identifier
     *
     * @throws     Exception
     */
    public function delUser($id)
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        if ($id == $this->auth->userID()) {
            return;
        }

        $rs = $this->getUser($id);

        if ($rs->nb_post > 0) {
            return;
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'user ' .
        "WHERE user_id = '" . $this->con->escape($id) . "' ";

        $this->con->execute($strReq);

        $this->auth->afterDelUser($id);
    }

    /**
     * Determines if user exists.
     *
     * @param      string  $id     The identifier
     *
     * @return      bool  True if user exists, False otherwise.
     */
    public function userExists($id)
    {
        $strReq = 'SELECT user_id ' .
        'FROM ' . $this->prefix . 'user ' .
        "WHERE user_id = '" . $this->con->escape($id) . "' ";

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
     * @param      string  $id     The user identifier
     *
     * @return     array   The user permissions.
     */
    public function getUserPermissions($id)
    {
        $strReq = 'SELECT B.blog_id, blog_name, blog_url, permissions ' .
        'FROM ' . $this->prefix . 'permissions P ' .
        'INNER JOIN ' . $this->prefix . 'blog B ON P.blog_id = B.blog_id ' .
        "WHERE user_id = '" . $this->con->escape($id) . "' ";

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
     * Sets user permissions. The <var>$perms</var> array looks like:
     *
     * - [blog_id] => '|perm1|perm2|'
     * - ...
     *
     * @param      string     $id     The user identifier
     * @param      array      $perms  The permissions
     *
     * @throws     Exception
     */
    public function setUserPermissions($id, $perms)
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'permissions ' .
        "WHERE user_id = '" . $this->con->escape($id) . "' ";

        $this->con->execute($strReq);

        foreach ($perms as $blog_id => $p) {
            $this->setUserBlogPermissions($id, $blog_id, $p, false);
        }
    }

    /**
     * Sets the user blog permissions.
     *
     * @param      string     $id            The user identifier
     * @param      string     $blog_id       The blog identifier
     * @param      array      $perms         The permissions
     * @param      bool       $delete_first  Delete permissions first
     *
     * @throws     Exception  (description)
     */
    public function setUserBlogPermissions($id, $blog_id, $perms, $delete_first = true)
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        $no_perm = empty($perms);

        $perms = '|' . implode('|', array_keys($perms)) . '|';

        $cur = $this->con->openCursor($this->prefix . 'permissions');

        $cur->user_id     = (string) $id;
        $cur->blog_id     = (string) $blog_id;
        $cur->permissions = $perms;

        if ($delete_first || $no_perm) {
            $strReq = 'DELETE FROM ' . $this->prefix . 'permissions ' .
            "WHERE blog_id = '" . $this->con->escape($blog_id) . "' " .
            "AND user_id = '" . $this->con->escape($id) . "' ";

            $this->con->execute($strReq);
        }

        if (!$no_perm) {
            $cur->insert();
        }
    }

    /**
     * Sets the user default blog. This blog will be selected when user log in.
     *
     * @param      string  $id       The user identifier
     * @param      string  $blog_id  The blog identifier
     */
    public function setUserDefaultBlog($id, $blog_id)
    {
        $cur = $this->con->openCursor($this->prefix . 'user');

        $cur->user_default_blog = (string) $blog_id;

        $cur->update("WHERE user_id = '" . $this->con->escape($id) . "'");
    }

    /**
     * Gets the user cursor.
     *
     * @param      cursor     $cur    The user cursor
     *
     * @throws     Exception
     */
    private function getUserCursor($cur)
    {
        if ($cur->isField('user_id')
            && !preg_match('/^[A-Za-z0-9@._-]{2,}$/', $cur->user_id)) {
            throw new Exception(__('User ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if ($cur->user_url !== null && $cur->user_url != '') {
            if (!preg_match('|^http(s?)://|', $cur->user_url)) {
                $cur->user_url = 'http://' . $cur->user_url;
            }
        }

        if ($cur->isField('user_pwd')) {
            if (strlen($cur->user_pwd) < 6) {
                throw new Exception(__('Password must contain at least 6 characters.'));
            }
            $cur->user_pwd = $this->auth->crypt($cur->user_pwd);
        }

        if ($cur->user_lang !== null && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $cur->user_lang)) {
            throw new Exception(__('Invalid user language code'));
        }

        if ($cur->user_upddt === null) {
            $cur->user_upddt = date('Y-m-d H:i:s');
        }

        if ($cur->user_options !== null) {
            $cur->user_options = serialize((array) $cur->user_options);
        }
    }

    /**
     * Returns user default settings in an associative array with setting names in keys.
     *
     * @return     array
     */
    public function userDefaults()
    {
        return [
            'edit_size'      => 24,
            'enable_wysiwyg' => true,
            'toolbar_bottom' => false,
            'editor'         => ['xhtml' => 'dcCKEditor', 'wiki' => 'dcLegacyEditor'],
            'post_format'    => 'xhtml'
        ];
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
     * @param      string  $id          The blog identifier
     * @param      bool    $with_super  Includes super admins in result
     *
     * @return     array   The blog permissions.
     */
    public function getBlogPermissions($id, $with_super = true)
    {
        $strReq = 'SELECT U.user_id AS user_id, user_super, user_name, user_firstname, ' .
        'user_displayname, user_email, permissions ' .
        'FROM ' . $this->prefix . 'user U ' .
        'JOIN ' . $this->prefix . 'permissions P ON U.user_id = P.user_id ' .
        "WHERE blog_id = '" . $this->con->escape($id) . "' ";

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
                'super'       => (boolean) $rs->user_super,
                'p'           => $this->auth->parsePermissions($rs->permissions)
            ];
        }

        return $res;
    }

    /**
     * Gets the blog.
     *
     * @param      string  $id     The blog identifier
     *
     * @return     mixed    The blog.
     */
    public function getBlog($id)
    {
        $blog = $this->getBlogs(['blog_id' => $id]);

        if ($blog->isEmpty()) {
            return false;
        }

        return $blog;
    }

    /**
     * Returns a record of blogs. <b>$params</b> is an array with the following
     * optionnal parameters:
     *
     * - <var>blog_id</var>: Blog ID
     * - <var>q</var>: Search string on blog_id, blog_name and blog_url
     * - <var>limit</var>: limit results
     *
     * @param      array|ArrayObject    $params      The parameters
     * @param      bool                 $count_only  Count only results
     *
     * @return     record  The blogs.
     */
    public function getBlogs($params = [], $count_only = false)
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
            $where .= 'AND blog_status = ' . (integer) $params['blog_status'] . ' ';
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

        return $this->con->select($strReq);
    }

    /**
     * Adds a new blog.
     *
     * @param      cursor     $cur    The blog cursor
     *
     * @throws     Exception
     */
    public function addBlog($cur)
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
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
     * @param      string  $id     The blog identifier
     * @param      cursor  $cur    The cursor
     */
    public function updBlog($id, $cur)
    {
        $this->getBlogCursor($cur);

        $cur->blog_upddt = date('Y-m-d H:i:s');

        $cur->update("WHERE blog_id = '" . $this->con->escape($id) . "'");
    }

    /**
     * Gets the blog cursor.
     *
     * @param      cursor  $cur    The cursor
     *
     * @throws     Exception
     */
    private function getBlogCursor($cur)
    {
        if (($cur->blog_id !== null
            && !preg_match('/^[A-Za-z0-9._-]{2,}$/', $cur->blog_id)) || (!$cur->blog_id)) {
            throw new Exception(__('Blog ID must contain at least 2 characters using letters, numbers or symbols.'));
        }

        if (($cur->blog_name !== null && $cur->blog_name == '') || (!$cur->blog_name)) {
            throw new Exception(__('No blog name'));
        }

        if (($cur->blog_url !== null && $cur->blog_url == '') || (!$cur->blog_url)) {
            throw new Exception(__('No blog URL'));
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
     * @param      string     $id     The blog identifier
     *
     * @throws     Exception
     */
    public function delBlog($id)
    {
        if (!$this->auth->isSuperAdmin()) {
            throw new Exception(__('You are not an administrator'));
        }

        $strReq = 'DELETE FROM ' . $this->prefix . 'blog ' .
        "WHERE blog_id = '" . $this->con->escape($id) . "' ";

        $this->con->execute($strReq);
    }

    /**
     * Determines if blog exists.
     *
     * @param      string  $id     The blog identifier
     *
     * @return     bool  True if blog exists, False otherwise.
     */
    public function blogExists($id)
    {
        $strReq = 'SELECT blog_id ' .
        'FROM ' . $this->prefix . 'blog ' .
        "WHERE blog_id = '" . $this->con->escape($id) . "' ";

        $rs = $this->con->select($strReq);

        return !$rs->isEmpty();
    }

    /**
     * Counts the number of blog posts.
     *
     * @param      string  $id     The blog identifier
     * @param      mixed   $type   The post type
     *
     * @return     integer  Number of blog posts.
     */
    public function countBlogPosts($id, $type = null)
    {
        $strReq = 'SELECT COUNT(post_id) ' .
        'FROM ' . $this->prefix . 'post ' .
        "WHERE blog_id = '" . $this->con->escape($id) . "' ";

        if ($type) {
            $strReq .= "AND post_type = '" . $this->con->escape($type) . "' ";
        }

        return $this->con->select($strReq)->f(0);
    }
    //@}

}
