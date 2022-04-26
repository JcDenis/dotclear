<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\User;

// Dotclear\Core\User\User
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtUser;
use Dotclear\Core\User\Preference\Preference;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Database\Cursor;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Crypt;
use Exception;

/**
 * User authentication class.
 *
 * @ingroup  Core User
 */
class User
{
    /**
     * @var UserContainer $user
     *                    Container instance
     */
    protected $user;

    /**
     * @var Preference $preference
     *                 Preference instance
     */
    protected $preference;

    /**
     * @var string $user_table
     *             ser table name
     */
    protected $user_table = 'user';

    /**
     * @var string $perm_table
     *             Perm table name
     */
    protected $perm_table = 'permissions';

    /**
     * @var string $blog_table
     *             Blog table name
     */
    protected $blog_table = 'blog';

    /**
     * @var bool $allow_pass_change
     *           User can change its password
     */
    protected $allow_pass_change = true;

    /**
     * @var array<string,array> $blogs
     *                          List of blogs on which the user has permissions
     */
    protected $blogs = [];

    /**
     * @var array<string,string> $perm_types
     *                           Permission types
     */
    protected $perm_types = [];

    /**
     * @var int $blog_count
     *          Count of user blogs
     */
    public $blog_count;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->user = new UserContainer();

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

    // / @name Credentials and user permissions
    // @{
    /**
     * Check if user exists and can log in.
     *
     * <var>$pwd</var> argument is optionnal
     * while you may need to check user without password. This method will create
     * credentials and populate all needed object properties.
     *
     * @param string $user_id    User ID
     * @param string $pwd        User password
     * @param string $user_key   User key check
     * @param bool   $check_blog checks if user is associated to a blog or not
     */
    public function checkUser(string $user_id, ?string $pwd = null, ?string $user_key = null, bool $check_blog = true): bool
    {
        // Check user and password
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->columns(array_keys($this->user->getCurrentProperties()))
            ->from(App::core()->prefix . $this->user_table)
            ->where('user_id = ' . $sql->quote($user_id))
        ;

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

        $rs->extend(new RsExtUser());

        if ('' != $pwd) {
            $user_pwd = $rs->f('user_pwd');
            $rehash   = false;
            if (password_verify($pwd, $rs->f('user_pwd'))) {
                // User password ok
                if (password_needs_rehash($rs->f('user_pwd'), PASSWORD_DEFAULT)) {
                    $user_pwd = $this->crypt($pwd);
                    $rehash   = true;
                }
            } else {
                // Check if pwd still stored in old fashion way
                $ret = password_get_info($rs->f('user_pwd'));
                if (is_array($ret) && isset($ret['algo']) && 0 == $ret['algo']) {
                    // hash not done with password_hash() function, check by old fashion way
                    if (Crypt::hmac(App::core()->config()->get('master_key'), $pwd, App::core()->config()->get('crypt_algo')) == $user_pwd) {
                        // Password Ok, need to store it in new fashion way
                        $user_pwd = $this->crypt($pwd);
                        $rehash   = true;
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
                $sql = new UpdateStatement(__METHOD__);
                $sql
                    ->set('user_pwd = ' . $sql->quote($user_pwd))
                    ->from(App::core()->prefix . $this->user_table)
                    ->where('user_id = ' . $sql->quote($rs->f('user_id')))
                    ->update()
                ;
            }
        } elseif ('' != $user_key) {
            // Avoid time attacks by measuring server response time during comparison
            if (!hash_equals(Http::browserUID(App::core()->config()->get('master_key') . $rs->f('user_id') . $this->cryptLegacy($rs->f('user_id'))), $user_key)) {
                return false;
            }
        }

        $this->user->parseFromRecord($rs);
        $this->preference = new Preference($this->user->getProperty('user_id'));

        // Get permissions on blogs
        return !($check_blog && false === $this->findUserBlog());
    }

    /**
     * Get user preference instance.
     *
     * @return null|Preference Preference instance
     */
    public function preference(): ?Preference
    {
        return $this->preference;
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
        return Crypt::hmac(App::core()->config()->get('master_key'), $pwd, App::core()->config()->get('crypt_algo'));
    }

    /**
     * This method only check current user password.
     *
     * @param string $pwd User password
     */
    public function checkPassword(string $pwd): bool
    {
        return empty($this->user->getProperty('user_pwd')) ? false : password_verify($pwd, $this->user->getProperty('user_pwd'));
    }

    /**
     * This method checks if user session cookie exists.
     */
    public function sessionExists(): bool
    {
        return isset($_COOKIE[App::core()->config()->get('session_name')]);
    }

    /**
     * This method checks user session validity.
     *
     * @param null|string $uid The session uid
     *
     * @return bool Session validity
     */
    public function checkSession(?string $uid = null): bool
    {
        App::core()->session()->start();

        // If session does not exist, logout.
        if (!isset($_SESSION['sess_user_id'])) {
            App::core()->session()->destroy();

            return false;
        }

        // Check here for user and IP address
        $this->checkUser($_SESSION['sess_user_id']);
        $uid = $uid ?: Http::browserUID(App::core()->config()->get('master_key'));

        $user_can_log = null !== $this->userID() && $uid == $_SESSION['sess_browser_uid'];

        if (!$user_can_log) {
            App::core()->session()->destroy();

            return false;
        }

        return true;
    }

    /**
     * Check if user must change his password in order to login.
     */
    public function mustChangePassword(): bool
    {
        return (bool) $this->user->getProperty('user_change_pwd');
    }

    /**
     * Check if user is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->user->getProperty('user_super');
    }

    /**
     * Check if user has permissions given in <var>$permissions</var> for blog
     * <var>$blog_id</var>.
     *
     * <var>$permissions</var> is a coma separated list of
     * permissions.
     *
     * @param string $permissions Permissions list
     * @param string $blog_id     Blog ID
     */
    public function check(string $permissions, string $blog_id): bool
    {
        if ($this->user->getProperty('user_super')) {
            return true;
        }

        $p = array_map('trim', explode(',', $permissions));
        $b = $this->getPermissions($blog_id);

        if (false != $b) {
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
     * Return true if user is allowed to change its password.
     */
    public function allowPassChange(): bool
    {
        return $this->allow_pass_change;
    }
    // @}

    // / @name Sudo
    // @{
    /**
     * Call $f function with super admin rights.
     *
     * Returns the function result.
     *
     * @param callable $f    Callback function
     * @param array    $args
     *
     * @return mixed
     */
    public function sudo($f, ...$args)
    {
        if (!is_callable($f)) {
            throw new CoreException($f . ' function does not exist');
        }

        if ($this->user->getProperty('user_super')) {
            $res = call_user_func_array($f, $args);
        } else {
            $this->user->setProperty('user_super', true);

            try {
                $res = call_user_func_array($f, $args);
                $this->user->setProperty('user_super', false);
            } catch (Exception $e) {
                $this->user->setProperty('user_super', false);

                throw $e;
            }
        }

        return $res;
    }
    // @}

    // / @name User information and options
    // @{
    /**
     * Return user permissions for a blog as an array which looks like:.
     *
     *  - [blog_id]
     *    - [permission] => true
     *    - ...
     *
     * @param string $blog_id Blog ID
     */
    public function getPermissions(string $blog_id): array|false
    {
        if (isset($this->blogs[$blog_id])) {
            return $this->blogs[$blog_id];
        }

        if ($this->user->getProperty('user_super')) {
            $sql = new SelectStatement(__METHOD__);
            $rs  = $sql
                ->column('blog_id')
                ->from(App::core()->prefix . $this->blog_table)
                ->where('blog_id = ' . $sql->quote($blog_id))
                ->select()
            ;

            $this->blogs[$blog_id] = $rs->isEmpty() ? false : ['admin' => true];

            return $this->blogs[$blog_id];
        }

        $sql = new SelectStatement(__METHOD__);
        $rs  = $sql
            ->column('permissions')
            ->from(App::core()->prefix . $this->perm_table)
            ->where('user_id = ' . $sql->quote($this->user->getProperty('user_id')))
            ->and('blog_id = ' . $sql->quote($blog_id))
            ->and($sql->orGroup([
                $sql->like('permissions', '%|usage|%'),
                $sql->like('permissions', '%|admin|%'),
                $sql->like('permissions', '%|contentadmin|%'),
            ]))
            ->select()
        ;

        $this->blogs[$blog_id] = $rs->isEmpty() ? false : $this->parsePermissions($rs->f('permissions'));

        return $this->blogs[$blog_id];
    }

    /**
     * Get the blog count.
     *
     * @return int the blog count
     */
    public function getBlogCount(): int
    {
        if (null === $this->blog_count) {
            $this->blog_count = App::core()->blogs()->getBlogs([], true)->fInt();
        }

        return $this->blog_count;
    }

    /**
     * Find an user blog.
     *
     * @param null|string $blog_id The blog identifier
     */
    public function findUserBlog(?string $blog_id = null): string|false
    {
        if ($blog_id && false !== $this->getPermissions($blog_id)) {
            return $blog_id;
        }

        $sql = new SelectStatement(__METHOD__);

        if ($this->user->getProperty('user_super')) {
            $sql
                ->column('blog_id')
                ->from(App::core()->prefix . $this->blog_table)
                ->order('blog_id ASC')
                ->limit(1)
            ;
        } else {
            $sql
                ->column('P.blog_id')
                ->from([
                    App::core()->prefix . $this->perm_table . ' P',
                    App::core()->prefix . $this->blog_table . ' B',
                ])
                ->where('user_id = ' . $sql->quote($this->user->getProperty('user_id')))
                ->and('P.blog_id = B.blog_id')
                ->and($sql->orGroup([
                    $sql->like('permissions', '%|usage|%'),
                    $sql->like('permissions', '%|admin|%'),
                    $sql->like('permissions', '%|contentadmin|%'),
                ]))
                ->and('blog_status >= 0')
                ->order('P.blog_id ASC')
                ->limit(1)
            ;
        }

        $rs = $sql->select();

        return $rs->isEmpty() ? false : $rs->f('blog_id');
    }

    /**
     * Return current user ID.
     */
    public function userID(): string
    {
        return $this->user->getProperty('user_id');
    }

    public function userCN(): string
    {
        return $this->user->getUserCN(
            $this->user->getProperty('user_id'),
            $this->user->getProperty('user_name'),
            $this->user->getProperty('user_firstname'),
            $this->user->getProperty('user_displayname')
        );
    }

    /**
     * Return information about a user .
     *
     * @param string $n Information name
     *
     * @return mixed
     */
    public function getInfo(string $n)
    {
        return $this->user->getProperty($n);
    }

    /**
     * Return a specific user option.
     *
     * @param string $n Option name
     *
     * @return mixed
     */
    public function getOption(string $n)
    {
        return $this->user->getOption($n);
    }

    /**
     * Return all user options in an associative array.
     */
    public function getOptions(): array
    {
        return $this->user->getOptions();
    }
    // @}

    // / @name Permissions
    // @{
    /**
     * Return an array with permissions parsed from the string <var>$level</var>.
     *
     * @param null|string $level Permissions string
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
     * Return <var>perm_types</var> property content.
     */
    public function getPermissionsTypes(): array
    {
        return $this->perm_types;
    }

    /**
     * Add a new permission type.
     *
     * @param string $name  Permission name
     * @param string $title Permission title
     */
    public function setPermissionType(string $name, string $title): void
    {
        $this->perm_types[$name] = $title;
    }
    // @}

    // / @name Password recovery
    // @{
    /**
     * Add a recover key to a specific user identified by its email and
     * password.
     *
     * @param string $user_id    User ID
     * @param string $user_email User Email
     */
    public function setRecoverKey(string $user_id, string $user_email): string
    {
        $sql = new SelectStatement(__METHOD__);
        $rs  = $sql
            ->column('user_id')
            ->from(App::core()->prefix . $this->user_table)
            ->where('user_id = ' . $sql->quote($user_id))
            ->and('user_email = ' . $sql->quote($user_email))
            ->select()
        ;

        if ($rs->isEmpty()) {
            throw new CoreException(__('That user does not exist in the database.'));
        }

        $key = md5(uniqid('', true));

        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->set('user_recover_key = ' . $sql->quote($key))
            ->from(App::core()->prefix . $this->user_table)
            ->where('user_id = ' . $sql->quote($user_id))
            ->update()
        ;

        return $key;
    }

    /**
     * Create a new user password using recovery key.
     *
     * Returns an array:
     * - user_email
     * - user_id
     * - new_pass
     *
     * @param string $recover_key Recovery key
     */
    public function recoverUserPassword(string $recover_key): array
    {
        $sql = new SelectStatement(__METHOD__);
        $rs  = $sql
            ->columns(['user_id', 'user_email'])
            ->from(App::core()->prefix . $this->user_table)
            ->where('user_recover_key = ' . $sql->quote($recover_key))
            ->select()
        ;

        if ($rs->isEmpty()) {
            throw new CoreException(__('That key does not exist in the database.'));
        }

        $new_pass = Crypt::createPassword();

        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->set('user_pwd = ' . $sql->quote($this->crypt($new_pass)))
            ->set('user_recover_key = NULL')
            ->set('user_change_pwd = 1') // User will have to change this temporary password at next login
            ->from(App::core()->prefix . $this->user_table)
            ->where('user_recover_key = ' . $sql->quote($recover_key))
            ->update()
        ;

        return ['user_email' => $rs->f('user_email'), 'user_id' => $rs->f('user_id'), 'new_pass' => $new_pass];
    }
    // @}

    /** @name User management callbacks
     * This 3 functions only matter if you extend this class and use
     * DOTCLEAR_USER_CLASS constant.
     * These are called after core user management functions.
     * Could be useful if you need to add/update/remove stuff in your
     * LDAP directory    or other third party authentication database.
     */
    // @{

    /**
     * Called after core->addUser.
     *
     * @see Core::addUser
     *
     * @param Cursor $cur User cursor
     */
    public function afterAddUser(Cursor $cur): void
    {
    }

    /**
     * Called after core->updUser.
     *
     * @see Core::updUser
     *
     * @param string $id  User ID
     * @param cursor $cur User cursor
     */
    public function afterUpdUser(string $id, Cursor $cur): void
    {
    }

    /**
     * Called after core->delUser.
     *
     * @see Core::delUser
     *
     * @param string $id User ID
     */
    public function afterDelUser(string $id): void
    {
    }
    // @}
}
