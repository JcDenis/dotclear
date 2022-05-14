<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin;

// Dotclear\Process\Admin\Prepend
use Dotclear\App;
use Dotclear\Core\Core;
use Dotclear\Helper\Clock;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Lexical;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Network\Http;
use Dotclear\Modules\Modules;
use Dotclear\Process\Admin\AdminUrl\AdminUrl;
use Dotclear\Process\Admin\Combo\Combo;
use Dotclear\Process\Admin\Favorite\Favorite;
use Dotclear\Process\Admin\Help\Help;
use Dotclear\Process\Admin\ListOption\ListOption;
use Dotclear\Process\Admin\Notice\Notice;
use Dotclear\Process\Admin\Menu\Summary;
use Dotclear\Process\Admin\Resource\Resource;
use Exception;

/**
 * Admin process.
 *
 * @ingroup  Admin
 */
class Prepend extends Core
{
    /**
     * @var AdminUrl $adminurl
     *               AdminUrl instance
     */
    private $adminurl;

    /**
     * @var Combo $combo
     *            Combo instance
     */
    private $combo;

    /**
     * @var Favorite $favorite
     *               Favorite instance
     */
    private $favorite;

    /**
     * @var Help $help
     *           Help instance
     */
    public $help;

    /**
     * @var ListOption $listoption
     *                 ListOption instance
     */
    private $listoption;

    /**
     * @var Notice $notice
     *             Notice instance
     */
    private $notice;

    /**
     * @var Summary $summary
     *              Summary instance
     */
    private $summary;

    /**
     * @var resource $resource
     *               Resource instance
     */
    private $resource;

    /**
     * @var Modules $plugins
     *              Plugin Modules instance
     */
    private $plugins;

    /**
     * @var Modules $iconsets
     *              Iconset Modules instance
     */
    private $iconsets;

    /**
     * @var Modules $themes
     *              Theme Modules instance
     */
    private $themes;

    /**
     * @var string $process
     *             Current Process
     */
    protected $process = 'Admin';

    /**
     * Get adminurl instance.
     *
     * @return AdminUrl AdminUrl instance
     */
    public function adminurl(): AdminUrl
    {
        if (!($this->adminurl instanceof AdminUrl)) {
            $this->adminurl = new AdminUrl();
            // Register default admin URLs
            $this->adminurl->setup();
        }

        return $this->adminurl;
    }

    /**
     * Get combo instance.
     *
     * @return Combo Combo instance
     */
    public function combo(): Combo
    {
        if (!($this->combo instanceof Combo)) {
            $this->combo = new Combo();
        }

        return $this->combo;
    }

    /**
     * Get favorite instance.
     *
     * @return Favorite Favorite instance
     */
    public function favorite(): Favorite
    {
        if (!($this->favorite instanceof Favorite)) {
            $this->favorite = new Favorite();
        }

        return $this->favorite;
    }

    /**
     * Get help instance.
     *
     * @return Help Help instance
     */
    public function help(): Help
    {
        if (!($this->help instanceof Help)) {
            $this->help = new Help();
        }

        return $this->help;
    }

    /**
     * Get resource instance.
     *
     * @return resource Resource instance
     */
    public function resource(): Resource
    {
        if (!($this->resource instanceof Resource)) {
            $this->resource = new Resource();
        }

        return $this->resource;
    }

    /**
     * Get summary (menus) instance.
     *
     * @return Summary Summary instance
     */
    public function summary(): Summary
    {
        if (!($this->summary instanceof Summary)) {
            $this->summary = new Summary();
        }

        return $this->summary;
    }

    /**
     * Get notice instance.
     *
     * @return Notice Notice instance
     */
    public function notice(): Notice
    {
        if (!($this->notice instanceof Notice)) {
            $this->notice = new Notice();
        }

        return $this->notice;
    }

    /**
     * Get listoption instance.
     *
     * @return ListOption ListOption instance
     */
    public function listoption(): ListOption
    {
        if (!($this->listoption instanceof ListOption)) {
            $this->listoption = new ListOption();
        }

        return $this->listoption;
    }

    /**
     * Get admin default datetime display timezone.
     *
     * This is the user timezone.
     *
     * @return string The user timezone
     */
    public function timezone(): string
    {
        return $this->user()->getInfo('user_tz') ?? Clock::getTZ();
    }

    /**
     * Get iconsets instance.
     *
     * @return Modules Iconsets instance
     */
    public function iconsets(): Modules
    {
        if (!($this->iconsets instanceof Modules)) {
            $this->iconsets = new Modules(
                type: 'Iconset',
                lang: $this->lang(),
                name: __('Iconsets management')
            );
            $this->adminLoadModulesResources($this->iconsets);
        }

        return $this->iconsets;
    }

    /**
     * Get plguins instance.
     *
     * @return Modules Plugins instance
     */
    public function plugins(): Modules
    {
        if (!($this->plugins instanceof Modules)) {
            $this->plugins = new Modules(admin: true, lang: $this->lang());
            $this->adminLoadModulesResources($this->plugins);
        }

        return $this->plugins;
    }

    /**
     * Get themes instance.
     *
     * @return Modules Themes instance
     */
    public function themes(): Modules
    {
        if (!($this->themes instanceof Modules)) {
            $this->themes = new Modules(
                admin: true,
                type: 'Theme',
                lang: $this->lang(),
                name: __('Blog appearance'),
                group: 'Blog'
            );
            $this->adminLoadModulesResources($this->themes);
        }

        return $this->themes;
    }

    /**
     * Start Dotclear Admin process.
     */
    protected function process(string $_ = null): void
    {
        // Load core prepend and so on
        parent::process();

        // Set header without cache for admin pages
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0'); // HTTP/1.1
        header('Pragma: no-cache'); // HTTP/1.0

        // csp report do not need extra stuff
        if ($this->adminurl()->is('admin.cspreport')) {
            $this->adminLoadPage();

            exit;
        }

        // Check user session
        if (defined('DOTCLEAR_AUTH_SESS_ID') && defined('DOTCLEAR_AUTH_SESS_UID')) {
            // We have session information in constants
            $_COOKIE[$this->config()->get('session_name')] = \DOTCLEAR_AUTH_SESS_ID;

            if (!$this->user()->checkSession(\DOTCLEAR_AUTH_SESS_UID)) {
                App::stop(new Exception(__('Invalid session data.'), 625));
            }

            // Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->nonce()->check($_POST['xd_check'])) {
                    App::stop(new Exception(__('Precondition Failed.'), 625));
                }
            }

            if (empty($_SESSION['sess_blog_id'])) {
                App::stop(new Exception(__('Permission denied.'), 625));
            }

            // Loading locales
            $this->adminLoadLocales();

            $this->setBlog($_SESSION['sess_blog_id']);
            if (!$this->blog()->id) {
                App::stop(new Exception(__('Permission denied.'), 625));
            }
        } elseif ($this->user()->sessionExists()) {
            // If we have a session we launch it now
            try {
                if (!$this->user()->checkSession()) {
                    // Avoid loop caused by old cookie
                    $p    = $this->session()->getCookieParameters(false, -600);
                    $p[3] = '/';
                    call_user_func_array('setcookie', $p);

                    $this->adminurl()->redirect('admin.auth');

                    exit;
                }
            } catch (Exception $e) {
                App::stop(new Exception(__('There seems to be no Session table in your database. Is Dotclear completly installed?'), 620, $e));
            }

            // Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->nonce()->check($_POST['xd_check'])) {
                    App::stop(new Exception(__('Precondition Failed.'), 412));
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
                    // Keep context as far as possible
                    $redir = $_REQUEST['redir'];
                } else {
                    // Removing switchblog from URL
                    $redir = $_SERVER['REQUEST_URI'];
                    $redir = preg_replace('/switchblog=(.*?)(\&|$)/', '', $redir);
                    $redir = preg_replace('/\&$/', '', $redir);
                }
                Http::redirect($redir);

                exit;
            }

            // Check blog to use and log out if no result
            if (isset($_SESSION['sess_blog_id'])) {
                if (false === $this->user()->getPermissions($_SESSION['sess_blog_id'])) {
                    unset($_SESSION['sess_blog_id']);
                }
            } else {
                if (false !== ($b = $this->user()->findUserBlog($this->user()->getInfo('user_default_blog')))) {
                    $_SESSION['sess_blog_id'] = $b;
                    unset($b);
                }
            }

            // Loading locales
            $this->adminLoadLocales();

            if (isset($_SESSION['sess_blog_id'])) {
                $this->setBlog($_SESSION['sess_blog_id']);
            } else {
                $this->session()->destroy();
                $this->adminurl()->redirect('admin.auth');

                exit;
            }
        }

        // Serve modules file (mf)
        $this->resource()->serve();

        // User session exists
        if (!empty($this->user()->userID()) && $this->blog() !== null) {
            // Load resources
            $this->adminLoadResources($this->config()->get('l10n_dir'));

            // Load modules (by calling them a first time)
            try {
                // $this->iconsets();
                $this->plugins();
                $this->themes();
            } catch (Exception $e) {
                // App::stop(new Exception(!$this->production() ? __('Unable to load modules.') : $e->getMessage(), 640, $e));
            }

            // Finalize menu and favorites
            $this->summary()->setup();
            $this->favorite()->setup();

            // Stop if no themes found
            if (!$this->themes()->getPaths()) {
                App::stop(new Exception(__('There seems to be no valid Theme directory set in configuration file.'), 611));
            }
            if (!$this->themes()->hasModules()) {
                App::stop(new Exception(__('There seems to be no valid Theme in your themes directories.'), 611));
            }

            // Add default top menus
            if (!$this->user()->preference()->get('interface')->get('nofavmenu')) {
                $this->favorite()->appendMenu($this->summary());
            }

            // No user session and not on auth page, go on
        } elseif (!$this->adminurl()->is('admin.auth')) {
            $this->adminurl()->redirect('admin.auth');

            exit;
        }

        // Load requested admin page
        $this->adminLoadPage();
    }

    /**
     * Load core locales.
     */
    private function adminLoadLocales(): void
    {
        $this->lang($this->user()->getInfo('user_lang'));

        if (L10n::set(Path::implode($this->config()->get('l10n_dir'), $this->lang(), 'date')) === false && 'en' != $this->lang()) {
            L10n::set(Path::implode($this->config()->get('l10n_dir'), 'en', 'date'));
        }
        L10n::set(Path::implode($this->config()->get('l10n_dir'), $this->lang(), 'main'));
        L10n::set(Path::implode($this->config()->get('l10n_dir'), $this->lang(), 'public'));
        L10n::set(Path::implode($this->config()->get('l10n_dir'), $this->lang(), 'plugins'));

        // Set lexical lang
        Lexical::setLexicalLang('admin', $this->lang());
    }

    /**
     * Load core locales resources.
     *
     * @param string $dir          Locales directory path
     * @param bool   $load_default Load default lang reousources
     */
    private function adminLoadResources(string $dir, bool $load_default = true): void
    {
        $this->lang($this->user()->getInfo('user_lang'));

        if ($load_default) {
            $this->help()->file(Path::implode($dir, 'en', 'resources.php'));
        }
        if (($f = L10n::getFilePath($dir, 'resources.php', $this->lang()))) {
            $this->help()->file($f);
        }
        unset($f);

        $hfiles = Files::scandir(Path::implode($dir, $this->lang(), 'help'), true, false);
        foreach ($hfiles as $hfile) {
            if (preg_match('/^(.*)\.html$/', $hfile, $m)) {
                $this->help()->context($m[1], Path::implode($dir, $this->lang(), 'help', $hfile));
            }
        }
        unset($hfiles);

        // Contextual help flag
        $this->help()->flag(false);
    }

    /**
     * Load modules locales resources.
     *
     * @param Modules $modules Modules instance
     */
    private function adminLoadModulesResources(Modules $modules): void
    {
        // Load lang resources for each module
        foreach ($modules->getModules() as $module) {
            $this->adminLoadResources($module->root() . '/locales', false);
        }
    }

    /**
     * Load admin page.
     *
     * @param null|string $handler Force specific handler
     */
    private function adminLoadPage(?string $handler = null): void
    {
        // no handler, go to admin home page
        if (null === $handler) {
            $handler = $_REQUEST['handler'] ?? 'admin.home';
        }

        // Create page instance
        try {
            // --BEHAVIOR-- adminPrepend
            $this->behavior()->call('adminPrepend');

            $class = $this->adminurl()->getBase($handler);
            if (!is_subclass_of($class, 'Dotclear\\Process\\Admin\\Page\\AbstractPage')) {
                throw new Exception(sprintf(__('URL for handler not found for %s.</p>'), $handler));
            }
            $page = new $class($handler);

            // Process page
            try {
                ob_start();
                $page->pageProcess();
                ob_end_flush();
            } catch (Exception $e) {
                ob_end_clean();

                App::stop(new Exception(
                    !$this->production() ? __('Failed to load page') : sprintf(__('Failed to load page for handler %s: '), $e->getMessage()),
                    601,
                    $e
                ));
            }
        } catch (Exception $e) {
            App::stop(new Exception(
                !$this->production() ? $e->getMessage() : '',
                628,
                $e
            ));
        }
    }
}
