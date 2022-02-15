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

use Dotclear\Core\Core;
Use Dotclear\Core\Utils;
use Dotclear\Exception\PrependException;
use Dotclear\File\Files;
use Dotclear\Module\AbstractModules;
use Dotclear\Module\Plugin\Admin\ModulesPlugin;
use Dotclear\Module\Iconset\Admin\ModulesIconset;
use Dotclear\Module\Theme\Admin\ModulesTheme;
use Dotclear\Network\Http;
use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends Core
{
    use \Dotclear\Admin\AdminUrl\TraitAdminUrl;
    use \Dotclear\Admin\Combo\TraitCombo;
    use \Dotclear\Admin\Favorite\TraitFavorite;
    use \Dotclear\Admin\Menu\TraitSummary;
    use \Dotclear\Admin\Notice\TraitNotice;
    use \Dotclear\Admin\Preference\TraitPreference;

    protected $process = 'Admin';

    /** @var ModulesPlugin|null ModulesPlugin instance */
    public $plugins = null;

    /** @var ModulesIconset|null ModulesIconset instance */
    public $iconsets = null;

    /** @var ModulesTheme|null ModulesTheme instance */
    public $themes = null;

    /** @var string     user lang */
    public $_lang = 'en';

    /** @var array      help resources container */
    public $resources = [];

    public function process()
    {
        # Load core prepend and so on
        parent::process();

        # Set header without cache for admin pages
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0'); # HTTP/1.1
        header('Pragma: no-cache'); # HTTP/1.0

        # Register default admin URLs
        $this->adminurl()->setup();

        # csp report do not need extra stuff
        if ($this->adminurl()->called() == 'admin.cspreport') {
            $this->adminLoadPage();
            exit;
        }

        # Check user session
        if (defined('DOTCLEAR_AUTH_SESS_ID') && defined('DOTCLEAR_AUTH_SESS_UID')) {
            # We have session information in constants
            $_COOKIE[dotclear()->config()->session_name] = DOTCLEAR_AUTH_SESS_ID;

            if (!$this->auth()->checkSession(DOTCLEAR_AUTH_SESS_UID)) {
                throw new PrependException('Invalid session data.');
            }

            # Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->nonce()->check($_POST['xd_check'])) {
                    throw new PrependException('Precondition Failed.');
                }
            }

            if (empty($_SESSION['sess_blog_id'])) {
                throw new PrependException('Permission denied.');
            }

            # Loading locales
            $this->adminLoadLocales();

            $this->setBlog($_SESSION['sess_blog_id']);
            if (!$this->blog()->id) {
                throw new PrependException('Permission denied.');
            }
        } elseif ($this->auth()->sessionExists()) {
            # If we have a session we launch it now
            try {
                if (!$this->auth()->checkSession()) {
                    # Avoid loop caused by old cookie
                    $p    = $this->session()->getCookieParameters(false, -600);
                    $p[3] = '/';
                    call_user_func_array('setcookie', $p);

                    $this->adminurl()->redirect('admin.auth');
                    exit;
                }
            } catch (\Exception $e) { #DatabaseException?
                throw new PrependException(__('Database error'), __('There seems to be no Session table in your database. Is Dotclear completly installed?'), 20);
            }

            # Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->nonce()->check($_POST['xd_check'])) {
                    Http::head(412);
                    header('Content-Type: text/plain');
                    echo 'Precondition Failed';
                    exit;
                }
            }

            if (!empty($_REQUEST['switchblog'])
                && $this->auth()->getPermissions($_REQUEST['switchblog']) !== false) {
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
                if ($this->auth()->getPermissions($_SESSION['sess_blog_id']) === false) {
                    unset($_SESSION['sess_blog_id']);
                }
            } else {
                if (($b = $this->auth()->findUserBlog($this->auth()->getInfo('user_default_blog'))) !== false) {
                    $_SESSION['sess_blog_id'] = $b;
                    unset($b);
                }
            }

            # Loading locales
            $this->adminLoadLocales();

            if (isset($_SESSION['sess_blog_id'])) {
                $this->setBlog($_SESSION['sess_blog_id']);
            } else {
                $this->session()->destroy();
                $this->adminurl()->redirect('admin.auth');
                exit;
            }
        }

        # Serve modules file (mf)
        $this->adminServeFile();

        # User session exists
        if (!empty($this->auth()->userID()) && $this->blog() !== null) {

            $this->auth()->user_prefs->addWorkspace('interface');

            # Load resources
            $this->adminLoadResources(dotclear()->config()->l10n_dir);

            # Load Modules Iconsets
            if ('' != $this->config()->iconset_dir) {
                $this->iconsets = new ModulesIconset();
                $this->iconsets->loadModules();
            }

            # Load Modules Plugins
            if ('' != $this->config()->plugin_dir) {
                $this->plugins = new ModulesPlugin();
                $this->adminLoadModules($this->plugins);
            }

            # Load Modules Themes
            $this->themes = new ModulesTheme();
            $this->adminLoadModules($this->themes);

            # Add default top menus
            $this->favorite()->setup();
            if (!$this->auth()->user_prefs->interface->nofavmenu) {
                $this->favorite()->appendMenu($this->summary());
            }
            $this->summary()->setup();

            # Set jquery stuff
            if (empty($this->blog()->settings->system->jquery_migrate_mute)) {
                $this->blog()->settings->system->put('jquery_migrate_mute', true, 'boolean', 'Mute warnings for jquery migrate plugin ?', false);
            }
            if (empty($this->blog()->settings->system->jquery_allow_old_version)) {
                $this->blog()->settings->system->put('jquery_allow_old_version', false, 'boolean', 'Allow older version of jQuery', false, true);
            }

            # Ensure theme's settings namespace exists
            $this->blog()->settings->addNamespace('themes');

        # No user session and not on auth page, go on
        } elseif ($this->adminurl()->called() != 'admin.auth') {
            $this->adminurl()->redirect('admin.auth');
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
            Files::serveFile([root_path('Admin', 'files')], 'df');
            exit;
        }

        # Serve var file
        if (!empty($_GET['vf'])) {
            Files::serveFile([dotclear()->config()->var_dir], 'vf');
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
        $class = root_ns('Module', $type, 'Admin', 'Modules' . $type);
        if (!is_subclass_of($class, 'Dotclear\\Module\\AbstractModules')) {
            throw new PrependException(__('Failed to load file'), __('File handler not found'), 20);
        }

        # Get paths and serve file
        $modules = new $class($this);
        $paths   = $modules->getModulesPath();
        $paths[] = root_path('Core', 'files', 'js');
        $paths[] = root_path('Core', 'files', 'css');
        Files::serveFile($paths, 'mf');
        exit;
    }

    private function adminLoadLocales(): void
    {
        $this->adminGetLang();

        L10n::lang($this->_lang);
        if (L10n::set(implode_path(dotclear()->config()->l10n_dir, $this->_lang, 'date')) === false && $this->_lang != 'en') {
            L10n::set(implode_path(dotclear()->config()->l10n_dir, 'en', 'date'));
        }
        L10n::set(implode_path(dotclear()->config()->l10n_dir, $this->_lang, 'main'));
        L10n::set(implode_path(dotclear()->config()->l10n_dir, $this->_lang, 'public'));
        L10n::set(implode_path(dotclear()->config()->l10n_dir, $this->_lang, 'plugins'));

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
            require implode_path($dir, 'en', 'resources.php');
        }
        if (($f = L10n::getFilePath($dir, 'resources.php', $_lang))) {
            require $f;
        }
        unset($f);

        if (($hfiles = @scandir(implode_path($dir, $_lang, 'help'))) !== false) {
            foreach ($hfiles as $hfile) {
                if (preg_match('/^(.*)\.html$/', $hfile, $m)) {
                    $__resources['help'][$m[1]] = implode_path($dir, $_lang, 'help', $hfile);
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
        $_lang       = $this->auth()->getInfo('user_lang') ?? 'en';
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
            $class = $this->adminurl()->getBase($handler);
            if (!is_subclass_of($class, 'Dotclear\\Admin\\Page\\Page')) {
                throw new PrependException(__('Unknow URL'), sprintf(__('<p>Failed to load URL for handler %s.</p>'), $handler), 404);
            }
            $page = new $class($handler);
        } catch (\Exception $e) {
            throw new PrependException('Dotclear error', $e->getMessage(), 20);
        }

        # Process page
        try {
            ob_start();
            $page->pageProcess();
            ob_end_flush();
        } catch (\Exception $e) {
            ob_end_clean();

            throw new PrependException(__('Failed to load page'), $e->getMessage(), 20);
        }
    }
}
