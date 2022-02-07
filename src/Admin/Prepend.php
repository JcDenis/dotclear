<?php
/**
 * @class Dotclear\Admin\Prepend
 * @brief Dotclear admin prepend
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use ArrayObject;

use Dotclear\Exception;
use Dotclear\Exception\PrependException;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Prepend as BasePrepend;
Use Dotclear\Core\Utils;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\Plugin\Admin\ModulesPlugin;
use Dotclear\Module\Iconset\Admin\ModulesIconset;
use Dotclear\Module\Theme\Admin\ModulesTheme;

use Dotclear\Admin\UrlHandler;
Use Dotclear\Admin\Favorites;
Use Dotclear\Admin\Menus;
Use Dotclear\Admin\Notices;
Use Dotclear\Admin\Combos;
Use Dotclear\Admin\UserPref;

use Dotclear\Utils\L10n;
use Dotclear\File\Path;
use Dotclear\File\Files;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends BasePrepend
{
    protected $process = 'Admin';

    /** @var Notices            Notices instance */
    public $notices;

    /** @var UserPref           UserPref instance */
    public $userpref;

    /** @var Combos             Combos instance */
    public $combos;

    /** @var ModulesPlugin|null ModulesPlugin instance */
    public $plugins = null;

    /** @var ModulesIconset|null ModulesIconset instance */
    public $iconsets = null;

    /** @var ModulesTheme|null ModulesTheme instance */
    public $themes = null;

    /** @var UrlHandler UrlHandler instance */
    public $adminurl;

    /** @var Favorites  Favorites instance */
    public $favs;

    /** @var ArrayObject sidebar menu */
    public $menu;

    /** @var string     user lang */
    public $_lang = 'en';

    /** @var array      help resources container */
    public $resources = [];

    public function process()
    {
        # Load core prepend and so on
        parent::process();

        $this->notices  = new Notices();
        $this->combos   = new Combos();
        $this->userpref = new UserPref();

        # Serve modules file (mf)
        $this->adminServeFile();

        # Set header without cache for admin pages
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0'); # HTTP/1.1
        header('Pragma: no-cache'); # HTTP/1.0

        # Register default admin URLs
        $this->adminurl = new UrlHandler();
        $this->adminurl->setup();

        # csp report do not need extra stuff
        if ($this->adminurl->called() == 'admin.cspreport') {
            $this->adminLoadPage();
            exit;
        }

        # Check user session
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
                    $p[3] = '/';
                    call_user_func_array('setcookie', $p);

                    $this->adminurl->redirect('admin.auth');
                    exit;
                }
            } catch (Exception $e) { #DatabaseException?
                throw new PrependException(__('Database error'), __('There seems to be no Session table in your database. Is Dotclear completly installed?'), 20);
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

        # User session exists
        if (!empty($this->auth->userID()) && $this->blog !== null) {

            $this->auth->user_prefs->addWorkspace('interface');

            # Load resources
            $this->adminLoadResources(DOTCLEAR_L10N_DIR);

            # Load sidebar menu
            $this->favs = new Favorites();
            $this->menu = new Menus();

            # Load Modules Iconsets
            if ('' != DOTCLEAR_ICONSET_DIR) {
                $this->iconsets = new ModulesIconset();
                $this->iconsets->loadModules();
            }

            # Load Modules Plugins
            if ('' != DOTCLEAR_PLUGIN_DIR) {
                $this->plugins = new ModulesPlugin();
                $this->adminLoadModules($this->plugins);
            }

            # Load Modules Themes
            $this->themes = new ModulesTheme();
            $this->adminLoadModules($this->themes);

            # Add default top menus
            $this->favs->setup();
            if (!$this->auth->user_prefs->interface->nofavmenu) {
                $this->favs->appendMenu($this->menu);
            }
            $this->menu->setup();

            # Set jquery stuff
            if (empty($this->blog->settings->system->jquery_migrate_mute)) {
                $this->blog->settings->system->put('jquery_migrate_mute', true, 'boolean', 'Mute warnings for jquery migrate plugin ?', false);
            }
            if (empty($this->blog->settings->system->jquery_allow_old_version)) {
                $this->blog->settings->system->put('jquery_allow_old_version', false, 'boolean', 'Allow older version of jQuery', false, true);
            }

            # Ensure theme's settings namespace exists
            $this->blog->settings->addNamespace('themes');

            # add some behaviors
            $this->behaviors->add('adminPopupPosts', ['Dotclear\\Admin\\BlogPref', 'adminPopupPosts']);

        # No user session and not on auth page, go on
        } elseif ($this->adminurl->called() != 'admin.auth') {
            $this->adminurl->redirect('admin.auth');
            exit;
        }

        # Load requested admin page
        $this->adminLoadPage();
    }

    private function adminLoadModules(AbstractModules $modules): void
    {
        $modules->loadModules($this->_lang);

        # Load lang resources for each module
        foreach($modules->getModules() as $module) {
            $this->adminLoadResources($module->root() . '/locales', false);
            $modules->loadModuleL10N($module->id(), $this->_lang, 'main');
            $modules->loadModuleL10N($module->id(), $this->_lang, 'admin');
        }
    }

    private function adminServeFile(): void
    {
        # Serve admin file (css, png, ...)
        if (!empty($_GET['df'])) {
            Files::serveFile([static::root('Admin', 'files')], 'df');
            exit;
        }

        # Serve var file
        if (!empty($_GET['vf'])) {
            Files::serveFile([DOTCLEAR_VAR_DIR], 'vf');
            exit;
        }

        # Serve modules file
        if (empty($_GET['mf'])) {
            return;
        }

        # Extract modules class name from url
        $pos = strpos($_GET['mf'], '/');
        if (!$pos) {
            throw new PrependException(__('Failed to load file'), __('File handler not found'), 20);
        }

        # Sanitize modules type
        $type = ucfirst(strtolower(substr($_GET['mf'], 0, $pos)));
        $_GET['mf'] = substr($_GET['mf'], $pos, strlen($_GET['mf']));

        # Check class
        $class = dcCore()::ns('Dotclear', 'Module', $type, 'Admin', 'Modules' . $type);
        if (!is_subclass_of($class, 'Dotclear\\Module\\AbstractModules')) {
            throw new PrependException(__('Failed to load file'), __('File handler not found'), 20);
        }

        # Get paths and serve file
        $modules = new $class($this);
        $paths   = $modules->getModulesPath();
        $paths[] = static::root('Core', 'files', 'js');
        $paths[] = static::root('Core', 'files', 'css');
        Files::serveFile($paths, 'mf');
        exit;
    }

    private function adminLoadLocales(): void
    {
        $this->adminGetLang();

        L10n::lang($this->_lang);
        if (L10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'date')) === false && $this->_lang != 'en') {
            L10n::set(static::path(DOTCLEAR_L10N_DIR, 'en', 'date'));
        }
        L10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'main'));
        L10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'public'));
        L10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'plugins'));

        # Set lexical lang
        Utils::setlexicalLang('admin', $this->_lang);
    }

    private function adminLoadResources(string $dir, $load_default = true): void
    {
        $this->adminGetLang();

        # for now keep old ressources files "as is"
        $_lang        = $this->_lang;
        $__resources = $this->resources;

        if ($load_default) {
            require static::path($dir, 'en', 'resources.php');
        }
        if (($f = L10n::getFilePath($dir, 'resources.php', $_lang))) {
            require $f;
        }
        unset($f);

        if (($hfiles = @scandir(static::path($dir, $_lang, 'help'))) !== false) {
            foreach ($hfiles as $hfile) {
                if (preg_match('/^(.*)\.html$/', $hfile, $m)) {
                    $__resources['help'][$m[1]] = static::path($dir, $_lang, 'help', $hfile);
                }
            }
        }
        unset($hfiles);

        # Contextual help flag
        $__resources['ctxhelp'] = false;

        $this->resources = $__resources;
    }

    private function adminGetLang(): void
    {
        $_lang       = $this->auth->getInfo('user_lang') ?? 'en';
        $this->_lang = preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_lang) ? $_lang : 'en';
    }

    private function adminLoadPage(?string $handler = null): void
    {
        # no handler, go to admin home page
        if ($handler === null) {
            $handler = $_REQUEST['handler'] ?? 'admin.home';
        }

        # Create page instance
        try {
            $class = $this->adminurl->getBase($handler);
            if (!is_subclass_of($class, 'Dotclear\\Admin\\Page')) {
                throw new AdminException(sprintf(__('<p>Failed to load URL for handler %s.</p>'), $handler));
            }
            $page = new $class($handler);
        } catch (AdminException $e) {
            throw new PrependException(__('Unknow URL'), $e->getMessage(), 404);
        } catch (Exception $e) {
            throw new PrependException('Dotclear error', $e->getMessage(), 20);
        }

        # Process page
        try {
            ob_start();
            $page->pageProcess();
            ob_end_flush();
        } catch (Exception $e) {
            ob_end_clean();

            throw new PrependException(__('Failed to load page'), $e->getMessage(), 20);
        }
    }
}