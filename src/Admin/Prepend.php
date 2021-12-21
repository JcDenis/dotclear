<?php
/**
 * @brief Dotclear admin core prepend class
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Prepend as BasePrepend;
use Dotclear\Core\Core;
Use Dotclear\Core\Utils;
Use Dotclear\Core\Notices;

Use Dotclear\Admin\Notices as AdminNotices;

use Dotclear\Utils\L10n;
use Dotclear\Utils\Path;
use Dotclear\Utils\Files;
use Dotclear\Utils\Http;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends BasePrepend
{
    protected $process = 'Admin';

    /** @var UrlHandler UrlHandler instance */
    public $adminurl;

    /** @var Notices    Notices instance */
    public $notices;

    /** @var Favorites  Favorites instance */
    public $favs;

    /** @var \ArrayObject sidebar menu */
    public $_menu;

    /** @var string     user lang */
    public $_lang = 'en';

    /** @var array      help resources container */
    public $_resources = [];

    public function __construct()
    {
        /* Serve admin file (css, png, ...) */
        if (!empty($_GET['df'])) {
            Utils::fileServer([static::root('Admin', 'files')], 'df');
            exit;
        }

        parent::__construct();

        /* Serve var file */
        if (!empty($_GET['vf'])) {
            Utils::fileServer([DOTCLEAR_VAR_DIR], 'vf');
            exit;
        }

        /* Serve plugin file */
        if (!empty($_GET['pf'])) {
            $paths = array_reverse(explode(PATH_SEPARATOR, DOTCLEAR_PLUGINS_DIR));
            $paths[] = static::root('Core', 'files', 'js');
            $paths[] = static::root('Core', 'files', 'css');
            Utils::fileServer($paths, 'pf');
            exit;
        }

        // HTTP/1.1
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

        // HTTP/1.0
        header('Pragma: no-cache');

        $this->adminLoadURL();
        $this->adminLoadSession();

        if (!$this->auth->userID() || $this->blog === null) {
            if ($this->adminurl->called() != 'admin.auth') {
                $this->adminurl->redirect('admin.auth');
                exit;
            }
        } else {
            $this->adminLoadRessources();
            $this->adminLoadMenu();

            if (empty($this->blog->settings->system->jquery_migrate_mute)) {
                $this->blog->settings->system->put('jquery_migrate_mute', true, 'boolean', 'Mute warnings for jquery migrate plugin ?', false);
            }
            if (empty($this->blog->settings->system->jquery_allow_old_version)) {
                $this->blog->settings->system->put('jquery_allow_old_version', false, 'boolean', 'Allow older version of jQuery', false, true);
            }

            # Ensure theme's settings namespace exists
            $this->blog->settings->addNamespace('themes');

            # Admin behaviors
            //$this->addBehavior('adminPopupPosts', ['dcAdminBlogPref', 'adminPopupPosts']);
        }

        $this->adminLoadPage();
    }

    private function adminLoadURL(): void
    {
        $this->adminurl = new UrlHandler($this, defined('DOTCLEAR_ADMIN_URL') ? DOTCLEAR_ADMIN_URL : '');

        $this->adminurl->register('admin.home', 'Dotclear\Admin\Page\Home');
        $this->adminurl->register('admin.auth', 'Dotclear\Admin\Page\Auth');
        $this->adminurl->register('admin.posts', 'posts.php');
        $this->adminurl->register('admin.popup_posts', 'popup_posts.php');
        $this->adminurl->register('admin.post', 'post.php');
        $this->adminurl->register('admin.post.media', 'post_media.php');
        $this->adminurl->register('admin.blog.theme', 'blog_theme.php');
        $this->adminurl->register('admin.blog.pref', 'blog_pref.php');
        $this->adminurl->register('admin.blog.del', 'blog_del.php');
        $this->adminurl->register('admin.blog', 'blog.php');
        $this->adminurl->register('admin.blogs', 'blogs.php');
        $this->adminurl->register('admin.categories', 'categories.php');
        $this->adminurl->register('admin.category', 'category.php');
        $this->adminurl->register('admin.comments', 'comments.php');
        $this->adminurl->register('admin.comment', 'comment.php');
        $this->adminurl->register('admin.help', 'help.php');
        $this->adminurl->register('admin.langs', 'langs.php');
        $this->adminurl->register('admin.media', 'media.php');
        $this->adminurl->register('admin.media.item', 'media_item.php');
        $this->adminurl->register('admin.plugins', 'plugins.php');
        $this->adminurl->register('admin.plugin', 'plugin.php');
        $this->adminurl->register('admin.search', 'search.php');
        $this->adminurl->register('admin.user.preferences', 'preferences.php');
        $this->adminurl->register('admin.user', 'user.php');
        $this->adminurl->register('admin.user.actions', 'users_actions.php');
        $this->adminurl->register('admin.users', 'users.php');
        $this->adminurl->register('admin.help', 'help.php');
        $this->adminurl->register('admin.update', 'update.php');

        $this->adminurl->registercopy('load.plugin.file', 'admin.home', ['pf' => 'dummy.css']);
        $this->adminurl->registercopy('load.var.file', 'admin.home', ['vf' => 'dummy.json']);
    }

    private function adminLoadSession(): bool
    {
        if (defined('DOTCLEAR_AUTH_SESS_ID') && defined('DOTCLEAR_AUTH_SESS_UID')) {
            # We have session information in constants
            $_COOKIE[DOTCLEAR_SESSION_NAME] = DOTCLEAR_AUTH_SESS_ID;

            if (!$this->auth->checkSession(DOTCLEAR_AUTH_SESS_UID)) {
                throw new AdminException('Invalid session data.');
            }

            # Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->checkNonce($_POST['xd_check'])) {
                    throw new AdminException('Precondition Failed.');
                }
            }

            if (empty($_SESSION['sess_blog_id'])) {
                throw new AdminException('Permission denied.');
            }

            # Loading locales
            $this->adminLoadLocales();

            $this->setBlog($_SESSION['sess_blog_id']);
            if (!$this->blog->id) {
                throw new AdminException('Permission denied.');
            }
        } elseif ($this->auth->sessionExists()) {
            # If we have a session we launch it now
            try {
                if (!$this->auth->checkSession()) {
                    # Avoid loop caused by old cookie
                    $p    = $this->session->getCookieParameters(false, -600);
                    $p[3] = '/';var_dump($p);
                    call_user_func_array('setcookie', $p);

                    $this->adminurl->redirect('admin.auth');
                    exit;
                }
            } catch (Exception $e) { //DatabaseException?
                static::error(__('Database error'), __('There seems to be no Session table in your database. Is Dotclear completly installed?'), 20);
            }

            # Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->checkNonce($_POST['xd_check'])) {
                    Http::head(412);
                    header('Content-Type: text/plain');
                    echo 'Precondition Failed';
                    exit;
                }
            }

            if (!empty($_REQUEST['switchblog'])
                && $this->auth->getPermissions($_REQUEST['switchblog']) !== false) {
                $_SESSION['sess_blog_id'] = $_REQUEST['switchblog'];
                if (isset($_SESSION['media_manager_dir'])) {
                    unset($_SESSION['media_manager_dir']);
                }
                if (isset($_SESSION['media_manager_page'])) {
                    unset($_SESSION['media_manager_page']);
                }

                if (!empty($_REQUEST['redir'])) {
                    # Keep context as far as possible
                    $redir = $_REQUEST['redir'];
                } else {
                    # Removing switchblog from URL
                    $redir = $_SERVER['REQUEST_URI'];
                    $redir = preg_replace('/switchblog=(.*?)(&|$)/', '', $redir);
                    $redir = preg_replace('/\?$/', '', $redir);
                }
                Http::redirect($redir);
                exit;
            }

            # Check blog to use and log out if no result
            if (isset($_SESSION['sess_blog_id'])) {
                if ($this->auth->getPermissions($_SESSION['sess_blog_id']) === false) {
                    unset($_SESSION['sess_blog_id']);
                }
            } else {
                if (($b = $this->auth->findUserBlog($this->auth->getInfo('user_default_blog'))) !== false) {
                    $_SESSION['sess_blog_id'] = $b;
                    unset($b);
                }
            }

            # Loading locales
            $this->adminLoadLocales();

            if (isset($_SESSION['sess_blog_id'])) {
                $this->setBlog($_SESSION['sess_blog_id']);
            } else {
                $this->session->destroy();
                $this->adminurl->redirect('admin.auth');
                exit;
            }
        }

        return true;
    }

    private function adminLoadLocales(): void
    {
        $this->adminGetLang();

        l10n::lang($this->_lang);
        if (l10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'date')) === false && $this->_lang != 'en') {
            l10n::set(static::path(DOTCLEAR_L10N_DIR, 'en', 'date'));
        }
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'main'));
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'public'));
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'plugins'));

        // Set lexical lang
        Utils::setlexicalLang('admin', $this->_lang);
    }

    private function adminLoadRessources(): void
    {
        $this->adminGetLang();

        /* for now keep old ressources files "as is" */
        $_lang        = $this->_lang;
        $__resources = $this->_resources;

        require static::path(DOTCLEAR_L10N_DIR, 'en', 'resources.php');
        if (($f = L10n::getFilePath(DOTCLEAR_L10N_DIR, 'resources.php', $_lang))) {
            require $f;
        }
        unset($f);

        if (($hfiles = @scandir(static::path(DOTCLEAR_L10N_DIR, $_lang, 'help'))) !== false) {
            foreach ($hfiles as $hfile) {
                if (preg_match('/^(.*)\.html$/', $hfile, $m)) {
                    $__resources['help'][$m[1]] = static::path(DOTCLEAR_L10N_DIR, $_lang, 'help', $hfile);
                }
            }
        }
        unset($hfiles);

        // Contextual help flag
        $__resources['ctxhelp'] = false;

        $this->_resources = $__resources;
    }

    private function adminGetLang(): void
    {
        $_lang = $this->auth->getInfo('user_lang') ?? 'en';
        $_lang = preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_lang) ? $_lang : 'en';

        $this->_lang = $_lang;
    }

    private function adminLoadMenu(): void
    {
        $this->auth->user_prefs->addWorkspace('interface');
        Menu::$iconset = @$this->auth->user_prefs->interface->iconset;

        $this->auth->user_prefs->addWorkspace('interface');
        $user_ui_nofavmenu = $this->auth->user_prefs->interface->nofavmenu;

        $this->notices = new Notices($this);
        $this->favs    = new Favorites($this);

        # Menus creation
        $_menu              = new \ArrayObject();
        $_menu['Dashboard'] = new Menu('dashboard-menu', '');
        if (!$user_ui_nofavmenu) {
            $this->favs->appendMenuTitle($_menu);
        }
        $_menu['Blog']    = new Menu('blog-menu', 'Blog');
        $_menu['System']  = new Menu('system-menu', 'System');
        $_menu['Plugins'] = new Menu('plugins-menu', 'Plugins');
        //$this->plugins->loadModules(DC_PLUGINS_ROOT, 'admin', $_lang);
        $this->favs->setup();

        if (!$user_ui_nofavmenu) {
            $this->favs->appendMenu($_menu);
        }

        # Set menu titles
        $_menu['System']->title  = __('System settings');
        $_menu['Blog']->title    = __('Blog');
        $_menu['Plugins']->title = __('Plugins');

        $this->addMenuItem($_menu, 'Blog', __('Blog appearance'), 'admin.blog.theme', 'images/menu/themes.png',
            $this->auth->check('admin', $this->blog->id));
        $this->addMenuItem($_menu, 'Blog', __('Blog settings'), 'admin.blog.pref', 'images/menu/blog-pref.png',
            $this->auth->check('admin', $this->blog->id));
        $this->addMenuItem($_menu, 'Blog', __('Media manager'), 'admin.media', 'images/menu/media.png',
            $this->auth->check('media,media_admin', $this->blog->id));
        $this->addMenuItem($_menu, 'Blog', __('Categories'), 'admin.categories', 'images/menu/categories.png',
            $this->auth->check('categories', $this->blog->id));
        $this->addMenuItem($_menu, 'Blog', __('Search'), 'admin.search', 'images/menu/search.png',
            $this->auth->check('usage,contentadmin', $this->blog->id));
        $this->addMenuItem($_menu, 'Blog', __('Comments'), 'admin.comments', 'images/menu/comments.png',
            $this->auth->check('usage,contentadmin', $this->blog->id));
        $this->addMenuItem($_menu, 'Blog', __('Posts'), 'admin.posts', 'images/menu/entries.png',
            $this->auth->check('usage,contentadmin', $this->blog->id));
        $this->addMenuItem($_menu, 'Blog', __('New post'), 'admin.post', 'images/menu/edit.png',
            $this->auth->check('usage,contentadmin', $this->blog->id), true, true);

        $this->addMenuItem($_menu, 'System', __('Update'), 'admin.update', 'images/menu/update.png',
            $this->auth->isSuperAdmin() && is_readable(DOTCLEAR_DIGESTS_DIR));
        $this->addMenuItem($_menu, 'System', __('Languages'), 'admin.langs', 'images/menu/langs.png',
            $this->auth->isSuperAdmin());
        $this->addMenuItem($_menu, 'System', __('Plugins management'), 'admin.plugins', 'images/menu/plugins.png',
            $this->auth->isSuperAdmin());
        $this->addMenuItem($_menu, 'System', __('Users'), 'admin.users', 'images/menu/users.png',
            $this->auth->isSuperAdmin());
        $this->addMenuItem($_menu, 'System', __('Blogs'), 'admin.blogs', 'images/menu/blogs.png',
            $this->auth->isSuperAdmin() || $this->auth->check('usage,contentadmin', $this->blog->id) && $this->auth->getBlogCount() > 1);

        $this->_menu = $_menu;
    }

    private function addMenuItem($_menu, $section, $desc, $adminurl, $icon, $perm, $pinned = false, $strict = false): void
    {
        $url     = $this->adminurl->get($adminurl);
        $pattern = '@' . preg_quote($url) . ($strict ? '' : '(\?.*)?') . '$@';
        $_menu[$section]->prependItem($desc, $url, $icon,
            preg_match($pattern, $_SERVER['REQUEST_URI']), $perm, null, null, $pinned);
    }

    private function adminLoadPage(?string $page = null): void
    {
        AdminNotices::$core = $this;

        if ($page === null) {
            $page = $_REQUEST['handler'] ?? 'admin.home';
        }

        try {
            $class = $this->adminurl->getBase($page);

            if (class_exists($class)) {
                new $class($this);
                exit;
            } else {
                throw new AdminException(sprintf(__('<p>Failed to load URL for handler %s.</p>'), $page));
            }
        } catch (AdminException $e) {
            static::error(
                __('Unknow URL'),
                $e->getMessage(),
                20
            );
        }
    }
}
