<?php
/**
 * @brief Dotclear core auth class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Exception;
use Dotclear\Exception\CoreException;

use Dotclear\Core\Sql\SqlStatement;
use Dotclear\Core\Sql\SelectStatement;
use Dotclear\Core\Sql\UpdateStatement;

use Dotclear\Database\Connection;
use Dotclear\Database\Cursor;
use Dotclear\Utils\Crypt;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Auth
{
    /** @var Core           Core instance */
    protected $core;

    /** @var Connection     Connection instance */
    protected $con;

    /** @var string         User table name */
    protected $user_table;

    /** @var string Perm table name */
    protected $perm_table;

    /** @var string Blog table name */
    protected $blog_table;

    /** @var string Current user ID */
    protected $user_id;

    /** @var array Array with user information */
    protected $user_info = [];

    /** @var array Array with user options */
    protected $user_options = [];

    /** @var boolean User must change his password after login */
    protected $user_change_pwd;

    /** @var boolean User is super admin */
    protected $user_admin;

    /** @var array Permissions for each blog */
    protected $permissions = [];

    /** @var boolean User can change its password */
    protected $allow_pass_change = true;

    /** @var array List of blogs on which the user has permissions */
    protected $blogs = [];

    /** @var integer Count of user blogs */
    public $blog_count = null;

    /** @var array Permission types */
    protected $perm_types;

    /** @var Prefs Prefs object */
    public $user_prefs;

    /**
     * Class constructor. Takes Core object as single argument.
     *
     * @param Core    $core        Core object
     */
    public function __construct(Core $core)
    {
        $this->core       = &$core;
        $this->con        = &$core->con;
        $this->blog_table = $core->prefix . 'blog';
        $this->user_table = $core->prefix . 'user';
        $this->perm_table = $core->prefix . 'permissions';

        $this->perm_types = [
            'admin'        => __('administrator'),
            'contentadmin' => __('manage all entries and comments'),
            'usage'        => __('manage their own entries and comments'),
            'publish'      => __('publish entries and comments'),
            'delete'       => __('delete entries and comments'),
            'categories'   => __('manage categories'),
            'media_admin'  => __('manage all media items'),
            'media'        => __('manage their own media items'),
        ];
    }

    /// @name Credentials and user permissions
    //@{
    /**
     * Checks if user exists and can log in. <var>$pwd</var> argument is optionnal
     * while you may need to check user without password. This method will create
     * credentials and populate all needed object properties.
     *
     * @param string    $user_id        User ID
     * @param string    $pwd            User password
     * @param string    $user_key        User key check
     * @param boolean    $check_blog    checks if user is associated to a blog or not.
     *
     * @return boolean
     */
    public function checkUser(string $user_id, ?string $pwd = null, ?string $user_key = null, bool $check_blog = true): bool
    {
        # Check user and password
        $sql = new SelectStatement($this->core, 'coreAuthCheckUser');
        $sql
            ->columns([
                'user_id',
                'user_super',
                'user_pwd',
                'user_change_pwd',
                'user_name',
                'user_firstname',
                'user_displayname',
                'user_email',
                'user_url',
                'user_default_blog',
                'user_options',
                'user_lang',
                'user_tz',
                'user_post_status',
                'user_creadt',
                'user_upddt',
            ])
            ->from($this->user_table)
            ->where('user_id = ' . $sql->quote($user_id));

        try {
            $rs = $sql->select();
        } catch (Exception $e) {
            $err = $e->getMessage();

            return false;
        }

        if ($rs->isEmpty()) {
            sleep(rand(2, 5));

            return false;
        }

        $rs->extend('Dotclear\\Core\\RsExt\\RsExtUser');

        if ($pwd != '') {
            $rehash = false;
            if (password_verify($pwd, $rs->user_pwd)) {
                // User password ok
                if (password_needs_rehash($rs->user_pwd, PASSWORD_DEFAULT)) {
                    $rs->user_pwd = $this->crypt($pwd);
                    $rehash       = true;
                }
            } else {
                // Check if pwd still stored in old fashion way
                $ret = password_get_info($rs->user_pwd);
                if (is_array($ret) && isset($ret['algo']) && $ret['algo'] == 0) {
                    // hash not done with password_hash() function, check by old fashion way
                    if (Crypt::hmac(DOTCLEAR_MASTER_KEY, $pwd, DOTCLEAR_CRYPT_ALGO) == $rs->user_pwd) {
                        // Password Ok, need to store it in new fashion way
                        $rs->user_pwd = $this->crypt($pwd);
                        $rehash       = true;
                    } else {
                        // Password KO
                        sleep(rand(2, 5));

                        return false;
                    }
                } else {
                    // Password KO
                    sleep(rand(2, 5));

                    return false;
                }
            }
            if ($rehash) {
                // Store new hash in DB
                $cur           = $this->con->openCursor($this->user_table);
                $cur->user_pwd = (string) $rs->user_pwd;

                $sql = new UpdateStatement($this->core, 'coreAuthCheckUser');
                $sql->where('user_id = ' . $sql->quote($rs->user_id));

                $sql->update($cur);
            }
        } elseif ($user_key != '') {
            // Avoid time attacks by measuring server response time during comparison
            if (!hash_equals(http::browserUID(DOTCLEAR_MASTER_KEY . $rs->user_id . $this->cryptLegacy($rs->user_id)), $user_key)) {
                return false;
            }
        }

        $this->user_id         = $rs->user_id;
        $this->user_change_pwd = (bool) $rs->user_change_pwd;
        $this->user_admin      = (bool) $rs->user_super;

        $this->user_info['user_pwd']          = $rs->user_pwd;
        $this->user_info['user_name']         = $rs->user_name;
        $this->user_info['user_firstname']    = $rs->user_firstname;
        $this->user_info['user_displayname']  = $rs->user_displayname;
        $this->user_info['user_email']        = $rs->user_email;
        $this->user_info['user_url']          = $rs->user_url;
        $this->user_info['user_default_blog'] = $rs->user_default_blog;
        $this->user_info['user_lang']         = $rs->user_lang;
        $this->user_info['user_tz']           = $rs->user_tz;
        $this->user_info['user_post_status']  = $rs->user_post_status;
        $this->user_info['user_creadt']       = $rs->user_creadt;
        $this->user_info['user_upddt']        = $rs->user_upddt;

        $this->user_info['user_cn'] = Utils::getUserCN(
            $rs->user_id,
            $rs->user_name,
            $rs->user_firstname,
            $rs->user_displayname
        );

        $this->user_options = array_merge($this->core->userDefaults(), $rs->options());

        $this->user_prefs = new Prefs($this->core, $this->user_id);

        # Get permissions on blogs
        if ($check_blog && ($this->findUserBlog() === false)) {
            return false;
        }

        return true;
    }

    /**
     * This method crypt given string (password, session_id, …).
     *
     * @param string $pwd string to be crypted
     *
     * @return string crypted value
     */
    public function crypt(string $pwd): string
    {
        return password_hash($pwd, PASSWORD_DEFAULT);
    }

    /**
     * This method crypt given string (password, session_id, …).
     *
     * @param string $pwd string to be crypted
     *
     * @return string crypted value
     */
    public function cryptLegacy(string $pwd): string
    {
        return Crypt::hmac(DOTCLEAR_MASTER_KEY, $pwd, DOTCLEAR_CRYPT_ALGO);
    }

    /**
     * This method only check current user password.
     *
     * @param string    $pwd            User password
     *
     * @return boolean
     */
    public function checkPassword(string $pwd): bool
    {
        if (!empty($this->user_info['user_pwd'])) {
            return password_verify($pwd, $this->user_info['user_pwd']);
        }

        return false;
    }

    /**
     * This method checks if user session cookie exists
     *
     * @return boolean
     */
    public function sessionExists(): bool
    {
        return isset($_COOKIE[DOTCLEAR_SESSION_NAME]);
    }

    /**
     * This method checks user session validity.
     *
     * @return boolean
     */
    public function checkSession(?string $uid = null): bool
    {
        $this->core->session->start();

        # If session does not exist, logout.
        if (!isset($_SESSION['sess_user_id'])) {
            $this->core->session->destroy();

            return false;
        }

        # Check here for user and IP address
        $this->checkUser($_SESSION['sess_user_id']);
        $uid = $uid ?: Http::browserUID(DOTCLEAR_MASTER_KEY);

        $user_can_log = $this->userID() !== null && $uid == $_SESSION['sess_browser_uid'];

        if (!$user_can_log) {
            $this->core->session->destroy();

            return false;
        }

        return true;
    }

    /**
     * Checks if user must change his password in order to login.
     *
     * @return boolean
     */
    public function mustChangePassword(): bool
    {
        return $this->user_change_pwd;
    }

    /**
     * Checks if user is super admin
     *
     * @return boolean
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_admin;
    }

    /**
     * Checks if user has permissions given in <var>$permissions</var> for blog
     * <var>$blog_id</var>. <var>$permissions</var> is a coma separated list of
     * permissions.
     *
     * @param string    $permissions    Permissions list
     * @param string    $blog_id        Blog ID
     *
     * @return boolean
     */
    public function check(string $permissions, string $blog_id): bool
    {
        if ($this->user_admin) {
            return true;
        }

        $p = array_map('trim', explode(',', $permissions));
        $b = $this->getPermissions($blog_id);

        if ($b != false) {
            if (isset($b['admin'])) {
                return true;
            }

            foreach ($p as $v) {
                if (isset($b[$v])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns true if user is allowed to change its password.
     *
     * @return    boolean
     */
    public function allowPassChange(): bool
    {
        return $this->allow_pass_change;
    }
    //@}

    /// @name Sudo
    //@{
    /**
     * Calls $f function with super admin rights.
     * Returns the function result.
     *
     * @param callable    $f            Callback function
     *
     * @return mixed
     */
    public function sudo($f, ...$args)
    {
        if (!is_callable($f)) {
            throw new CoreException($f . ' function doest not exist');
        }

        if ($this->user_admin) {
            $res = call_user_func_array($f, $args);
        } else {
            $this->user_admin = true;

            try {
                $res              = call_user_func_array($f, $args);
                $this->user_admin = false;
            } catch (Exception $e) {
                $this->user_admin = false;

                throw $e;
            }
        }

        return $res;
    }
    //@}

    /// @name User information and options
    //@{
    /**
     * Returns user permissions for a blog as an array which looks like:
     *
     *  - [blog_id]
     *    - [permission] => true
     *    - ...
     *
     * @param string    $blog_id        Blog ID
     *
     * @return array
     */
    public function getPermissions(string $blog_id): array
    {
        if (isset($this->blogs[$blog_id])) {
            return $this->blogs[$blog_id];
        }

        if ($this->user_admin) {
            $sql = new SelectStatement($this->core, 'coreAuthGetPermissions');
            $sql
                ->column('blog_id')
                ->from($this->blog_table)
                ->where('blog_id = ' . $sql->quote($blog_id));

            $rs = $sql->select();

            $this->blogs[$blog_id] = $rs->isEmpty() ? false : ['admin' => true];

            return $this->blogs[$blog_id];
        }

        $sql = new SelectStatement($this->core, 'coreAuthGetPermissions');
        $sql
            ->column('permissions')
            ->from($this->perm_table)
            ->where('user_id = ' . $sql->quote($this->user_id))
            ->and('blog_id = ' . $sql->quote($blog_id))
            ->and($sql->orGroup([
                $sql->like('permissions', '%|usage|%'),
                $sql->like('permissions', '%|admin|%'),
                $sql->like('permissions', '%|contentadmin|%'),
            ]));

        $rs = $sql->select();

        $this->blogs[$blog_id] = $rs->isEmpty() ? false : $this->parsePermissions($rs->permissions);

        return $this->blogs[$blog_id];
    }

    /**
     * Gets the blog count.
     *
     * @return     integer  The blog count.
     */
    public function getBlogCount(): int
    {
        if ($this->blog_count === null) {
            $this->blog_count = $this->core->getBlogs([], true)->f(0);  // @phpstan-ignore-line
        }

        return (int) $this->blog_count;
    }

    /**
     * Finds an user blog.
     *
     * @param      mixed  $blog_id  The blog identifier
     *
     * @return     string|false
     */
    public function findUserBlog(?string $blog_id = null): string|false
    {
        if ($blog_id && $this->getPermissions($blog_id) !== false) {
            return $blog_id;
        }

        $sql = new SelectStatement($this->core, 'coreAuthFindUserBlog');

        if ($this->user_admin) {
            /* @phpstan-ignore-next-line */
            $sql
                ->column('blog_id')
                ->from($this->blog_table)
                ->order('blog_id ASC')
                ->limit(1);
        } else {
            /* @phpstan-ignore-next-line */
            $sql
                ->column('P.blog_id')
                ->from([
                    $this->perm_table . ' P',
                    $this->blog_table . ' B',
                ])
                ->where('user_id = ' . $sql->quote($this->user_id))
                ->and('P.blog_id = B.blog_id')
                ->and($sql->orGroup([
                    $sql->like('permissions', '%|usage|%'),
                    $sql->like('permissions', '%|admin|%'),
                    $sql->like('permissions', '%|contentadmin|%'),
                ]))
                ->and('blog_status >= 0')
                ->order('P.blog_id ASC')
                ->limit(1);
        }

        $rs = $sql->select();
        if (!$rs->isEmpty()) {
            return $rs->blog_id;
        }

        return false;
    }

    /**
     * Returns current user ID
     *
     * @return string
     */
    public function userID(): string
    {
        return $this->user_id ?? '';
    }

    /**
     * Returns information about a user .
     *
     * @param string    $n            Information name
     *
     * @return mixed
     */
    public function getInfo(string $n)
    {
        if (isset($this->user_info[$n])) {
            return $this->user_info[$n];
        }
    }

    /**
     * Returns a specific user option
     *
     * @param string    $n            Option name
     *
     * @return mixed
     */
    public function getOption(string $n)
    {
        if (isset($this->user_options[$n])) {
            return $this->user_options[$n];
        }
    }

    /**
     * Returns all user options in an associative array.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->user_options;
    }
    //@}

    /// @name Permissions
    //@{
    /**
     * Returns an array with permissions parsed from the string <var>$level</var>
     *
     * @param string|null    $level        Permissions string
     *
     * @return array
     */
    public function parsePermissions(?string $level): array
    {
        $level = (string) $level;
        $level = preg_replace('/^\|/', '', $level);
        $level = preg_replace('/\|$/', '', $level);

        $res = [];
        foreach (explode('|', $level) as $v) {
            $res[$v] = true;
        }

        return $res;
    }

    /**
     * Returns <var>perm_types</var> property content.
     *
     * @return array
     */
    public function getPermissionsTypes(): array
    {
        return $this->perm_types;
    }

    /**
     * Adds a new permission type.
     *
     * @param string    $name        Permission name
     * @param string    $title        Permission title
     */
    public function setPermissionType(string $name, string $title): void
    {
        $this->perm_types[$name] = $title;
    }
    //@}

    /// @name Password recovery
    //@{
    /**
     * Add a recover key to a specific user identified by its email and
     * password.
     *
     * @param string    $user_id        User ID
     * @param string    $user_email    User Email
     *
     * @return string
     */
    public function setRecoverKey(string $user_id, string $user_email): string
    {
        $sql = new SelectStatement($this->core, 'coreAuthSetRecoverKey');
        $sql
            ->column('user_id')
            ->from($this->user_table)
            ->where('user_id = ' . $sql->quote($user_id))
            ->and('user_email = ' . $sql->quote($user_email));

        $rs = $sql->select();

        if ($rs->isEmpty()) {
            throw new CoreException(__('That user does not exist in the database.'));
        }

        $key = md5(uniqid('', true));

        $cur                   = $this->con->openCursor($this->user_table);
        $cur->user_recover_key = $key;

        $sql = new UpdateStatement($this->core, 'coreAuthSetRecoverKey');
        $sql->where('user_id = ' . $sql->quote($user_id));

        $sql->update($cur);

        return $key;
    }

    /**
     * Creates a new user password using recovery key. Returns an array:
     *
     * - user_email
     * - user_id
     * - new_pass
     *
     * @param string    $recover_key    Recovery key
     *
     * @return array
     */
    public function recoverUserPassword(string $recover_key): array
    {
        $sql = new SelectStatement($this->core, 'coreAuthRecoverUserPassword');
        $sql
            ->columns(['user_id', 'user_email'])
            ->from($this->user_table)
            ->where('user_recover_key = ' . $sql->quote($recover_key));

        $rs = $sql->select();

        if ($rs->isEmpty()) {
            throw new CoreException(__('That key does not exist in the database.'));
        }

        $new_pass = Crypt::createPassword();

        $cur                   = $this->con->openCursor($this->user_table);
        $cur->user_pwd         = $this->crypt($new_pass);
        $cur->user_recover_key = null;
        $cur->user_change_pwd  = 1; // User will have to change this temporary password at next login

        $sql = new UpdateStatement($this->core, 'coreAuthRecoverUserPassword');
        $sql->where('user_recover_key = ' . $sql->quote($recover_key));

        $sql->update($cur);

        return ['user_email' => $rs->user_email, 'user_id' => $rs->user_id, 'new_pass' => $new_pass];
    }
    //@}

    /** @name User management callbacks
    This 3 functions only matter if you extend this class and use
    DC_AUTH_CLASS constant.
    These are called after core user management functions.
    Could be useful if you need to add/update/remove stuff in your
    LDAP directory    or other third party authentication database.
     */
    //@{

    /**
     * Called after core->addUser
     * @see Core::addUser
     *
     * @param Cursor    $cur            User cursor
     */
    public function afterAddUser(Cursor $cur): void
    {
    }

    /**
     * Called after core->updUser
     * @see Core::updUser
     *
     * @param string    $id            User ID
     * @param cursor    $cur            User cursor
     */
    public function afterUpdUser(string $id, Cursor $cur): void
    {
    }

    /**
     * Called after core->delUser
     * @see Core::delUser
     *
     * @param string    $id            User ID
     */
    public function afterDelUser(string $id): void
    {
    }
    //@}
}
