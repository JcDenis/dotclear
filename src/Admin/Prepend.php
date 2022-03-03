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

use Dotclear\Admin\Filer;
use Dotclear\Admin\AdminUrl\AdminUrl;
use Dotclear\Admin\Combo\Combo;
use Dotclear\Admin\Favorite\Favorite;
use Dotclear\Admin\ListOption\ListOption;
use Dotclear\Admin\Menu\Summary;
use Dotclear\Admin\Notice\Notice;
use Dotclear\Core\Core;
Use Dotclear\Core\Utils;
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
    /** @var    AdminUrl    AdminUrl instance */
    private $adminurl;

    /** @var    Combo   Combo instance */
    private $combo;

    /** @var    Favorite    Favorite instance */
    private $favorite;

    /** @var    Filer   Filer instance */
    private $filer;

    /** @var    Summary     Summary instance */
    private $summary;

    /** @var    Notice   Notice instance */
    private $notice;

    /** @var    ListOption  ListOption instance */
    private $listoption;

    /** @var    string  Current Process */
    protected $process = 'Admin';

    /** @var    ModulesPlugin|null  ModulesPlugin instance */
    public $plugins = null;

    /** @var    ModulesIconset|null ModulesIconset instance */
    public $iconsets = null;

    /** @var    ModulesTheme|null   ModulesTheme instance */
    public $themes = null;

    /** @var    string  user lang */
    public $_lang = 'en';

    /** @var    array   help resources container */
    public $resources = [];

    /**
     * Get adminurl instance
     *
     * @return  AdminUrl   AdminUrl instance
     */
    public function adminurl(): AdminUrl
    {
        if (!($this->adminurl instanceof AdminUrl)) {
            $this->adminurl = new AdminUrl();
            # Register default admin URLs
            $this->adminurl->setup();
        }

        return $this->adminurl;
    }

    /**
     * Get combo instance
     *
     * @return  Combo   Combo instance
     */
    public function combo(): Combo
    {
        if (!($this->combo instanceof Combo)) {
            $this->combo = new Combo();
        }

        return $this->combo;
    }

    /**
     * Get favorite instance
     *
     * @return  Favorite   Favorite instance
     */
    public function favorite(): Favorite
    {
        if (!($this->favorite instanceof Favorite)) {
            $this->favorite = new Favorite();
        }

        return $this->favorite;
    }

    /**
     * Get filer instance
     *
     * @return  Filer   Filer instance
     */
    public function filer(): Filer
    {
        if (!($this->filer instanceof Filer)) {
            $this->filer = new Filer();
        }

        return $this->filer;
    }

    /**
     * Get summary (menus) instance
     *
     * @return  Summary   Summary instance
     */
    public function summary(): Summary
    {
        if (!($this->summary instanceof Summary)) {
            $this->summary = new Summary();
        }

        return $this->summary;
    }

    /**
     * Get notice instance
     *
     * @return  Notice   Notice instance
     */
    public function notice(): Notice
    {
        if (!($this->notice instanceof Notice)) {
            $this->notice = new Notice();
        }

        return $this->notice;
    }

    /**
     * Get listoption instance
     *
     * @return  ListOption   ListOption instance
     */
    public function listoption(): ListOption
    {
        if (!($this->listoption instanceof ListOption)) {
            $this->listoption = new ListOption();
        }

        return $this->listoption;
    }

    /**
     * Start Dotclear Admin process
     */
    protected function process(): void
    {
        # Load core prepend and so on
        parent::process();

        # Set header without cache for admin pages
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0'); # HTTP/1.1
        header('Pragma: no-cache'); # HTTP/1.0

        # csp report do not need extra stuff
        if ($this->adminurl()->called() == 'admin.cspreport') {
            $this->adminLoadPage();
            exit;
        }

        # Check user session
        if (defined('DOTCLEAR_AUTH_SESS_ID') && defined('DOTCLEAR_AUTH_SESS_UID')) {
            # We have session information in constants
            $_COOKIE[$this->config()->session_name] = DOTCLEAR_AUTH_SESS_ID;

            if (!$this->user()->checkSession(DOTCLEAR_AUTH_SESS_UID)) {
                $this->getExceptionLang();
                $this->throwException(__('Invalid session data.'), '', 625);
            }

            # Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->nonce()->check($_POST['xd_check'])) {
                $this->getExceptionLang();
                $this->throwException(__('Precondition Failed.'), '', 625);
                }
            }

            if (empty($_SESSION['sess_blog_id'])) {
                $this->getExceptionLang();
                $this->throwException(__('Permission denied.'), '', 625);
            }

            # Loading locales
            $this->adminLoadLocales();

            $this->setBlog($_SESSION['sess_blog_id']);
            if (!$this->blog()->id) {
                $this->getExceptionLang();
                $this->throwException(__('Permission denied.'), '', 625);
            }
        } elseif ($this->user()->sessionExists()) {
            # If we have a session we launch it now
            try {
                if (!$this->user()->checkSession()) {
                    # Avoid loop caused by old cookie
                    $p    = $this->session()->getCookieParameters(false, -600);
                    $p[3] = '/';
                    call_user_func_array('setcookie', $p);

                    $this->adminurl()->redirect('admin.auth');
                    exit;
                }
            } catch (\Exception $e) {
                $this->getExceptionLang();
                $this->throwException(__('There seems to be no Session table in your database. Is Dotclear completly installed?'), '', 620);
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
                && $this->user()->getPermissions($_REQUEST['switchblog']) !== false) {
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
                    $redir = preg_replace('/switchblog=(.*?)(\&|$)/', '', $redir);
                    $redir = preg_replace('/\&$/', '', $redir);
                }
                Http::redirect($redir);
                exit;
            }

            # Check blog to use and log out if no result
            if (isset($_SESSION['sess_blog_id'])) {
                if ($this->user()->getPermissions($_SESSION['sess_blog_id']) === false) {
                    unset($_SESSION['sess_blog_id']);
                }
            } else {
                if (($b = $this->user()->findUserBlog($this->user()->getInfo('user_default_blog'))) !== false) {
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
        $this->filer()->serve();

        # User session exists
        if (!empty($this->user()->userID()) && $this->blog() !== null) {

            $this->user()->preference()->addWorkspace('interface');

            # Load resources
            $this->adminLoadResources($this->config()->l10n_dir);

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
            if (!$this->user()->preference()->interface->nofavmenu) {
                $this->favorite()->appendMenu($this->summary());
            }
            $this->summary()->setup();

            # Set jquery stuff
            if (empty($this->blog()->settings()->system->jquery_migrate_mute)) {
                $this->blog()->settings()->system->put('jquery_migrate_mute', true, 'boolean', 'Mute warnings for jquery migrate plugin ?', false);
            }
            if (empty($this->blog()->settings()->system->jquery_allow_old_version)) {
                $this->blog()->settings()->system->put('jquery_allow_old_version', false, 'boolean', 'Allow older version of jQuery', false, true);
            }

            # Ensure theme's settings namespace exists
            $this->blog()->settings()->addNamespace('themes');

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

    private function adminLoadLocales(): void
    {
        $this->adminGetLang();

        L10n::lang($this->_lang);
        if (L10n::set(implode_path($this->config()->l10n_dir, $this->_lang, 'date')) === false && $this->_lang != 'en') {
            L10n::set(implode_path($this->config()->l10n_dir, 'en', 'date'));
        }
        L10n::set(implode_path($this->config()->l10n_dir, $this->_lang, 'main'));
        L10n::set(implode_path($this->config()->l10n_dir, $this->_lang, 'public'));
        L10n::set(implode_path($this->config()->l10n_dir, $this->_lang, 'plugins'));

        # Set lexical lang
        Utils::setlexicalLang('admin', $this->_lang);
    }

    private function adminLoadResources(string $dir, bool $load_default = true): void
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
        $_lang       = $this->user()->getInfo('user_lang') ?? 'en';
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
                $this->getExceptionLang();
                throw new \Exception(sprintf(__('URL for handler not found for %s.</p>'), $handler));
            }
            $page = new $class($handler);
        } catch (\Exception $e) {
            $this->throwException(
                $e->getMessage(),
                '',
                628
            );
        }

        # Process page
        try {
            ob_start();
            $page->pageProcess();
            ob_end_flush();
        } catch (\Exception $e) {
            ob_end_clean();

            $this->throwException(
                __('Failed to load page'),
                sprintf(__('Failed to load page for handler %s: '), $e->getMessage()),
                601
            );
        }
    }
}
