<?php
/**
 * @package     Dotclear
 * @subpackage  Upgrade
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

/**
 * @namespace   Dotclear.Core.Upgrade
 * @brief       Dotclear application upgrade utilities.
 */

namespace Dotclear\Core\Upgrade;

use Dotclear\App;
use Dotclear\Core\Backend\Resources;
use Dotclear\Core\Process;
use Dotclear\Exception\ContextException;
use Dotclear\Exception\PreconditionException;
use Dotclear\Exception\SessionException;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Network\Http;
use Dotclear\Process\Upgrade\Cli;
use Throwable;

/**
 * @brief   Utility class for upgrade context.
 *
 * All upgrade process MUST be executed with safe mode.
 * Behaviors are prohibited.
 *
 * @since   2.29
 */
class Utility extends Process
{
    /**
     * Upgrade login cookie name.
     *
     * Need to use same cookie as Backend Utility.
     *
     * @var     string  COOKIE_NAME
     */
    public const COOKIE_NAME = 'dc_admin';

    /**
     * Upgrade Url handler instance.
     *
     * @var     Url     $url
     */
    private Url $url;

    /**
     * Upgrade Menus handler instance.
     *
     * @var     Menus   $menus
     */
    private Menus $menus;

    /**
     * Upgrade help resources instance.
     *
     * @var     Resources   $resources
     */
    private Resources $resources;

    /**
     * Constructs a new instance.
     *
     * @throws     ContextException  (if not upgrade context)
     */
    public function __construct()
    {
        if (!App::task()->checkContext('UPGRADE')) {
            throw new ContextException('Application is not in upgrade context.');
        }

        // HTTP/1.1
        header('Expires: Mon, 13 Aug 2003 07:48:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    }

    public static function init(): bool
    {
        // We need to pass CLI argument to App::task()->run()
        if (isset($_SERVER['argv'][1])) {
            $_SERVER['DC_RC_PATH'] = $_SERVER['argv'][1];
        }

        return true;
    }

    public static function process(): bool
    {
        if (App::config()->cliMode()) {
            // In CLI mode process does the job
            App::task()->loadProcess(Cli::class);

            return true;
        }

        if (App::auth()->sessionExists()) {
            // If we have a session we launch it now
            try {
                if (!App::auth()->checkSession()) {
                    // Avoid loop caused by old cookie
                    $p    = App::session()->getCookieParameters(false, -600);
                    $p[3] = '/';
                    setcookie(...$p);   // @phpstan-ignore-line

                    App::upgrade()->url()->redirect('upgrade.auth');
                }
            } catch (Throwable) {
                throw new SessionException(__('There seems to be no Session table in your database. Is Dotclear completly installed?'));
            }

            // Fake process to logout (kill session) and return to auth page.
            if (!empty($_REQUEST['process']) && $_REQUEST['process'] == 'Logout'
                || !App::auth()->isSuperAdmin()
            ) {
                // Enable REST service if disabled, for next requests
                if (!App::rest()->serveRestRequests()) {
                    App::rest()->enableRestServer(true);
                }
                // Kill admin session
                App::upgrade()->killAdminSession();
                // Logout
                App::upgrade()->url()->redirect('upgrade.auth');
                exit;
            }

            // Check nonce from POST requests
            if (!empty($_POST) && (empty($_POST['xd_check']) || !App::nonce()->checkNonce($_POST['xd_check']))) {
                throw new PreconditionException();
            }

            // Load locales
            self::loadLocales();
        }

        // Set default menu
        App::upgrade()->menus()->setDefaultItems();

        return true;
    }

    /**
     * Loads user locales (English if not defined).
     */
    public static function loadLocales(): void
    {
        App::lang()->setLang((string) App::auth()->getInfo('user_lang'));

        if (L10n::set(App::config()->l10nRoot() . '/' . App::lang()->getLang() . '/date') === false && App::lang()->getLang() != 'en') {
            L10n::set(App::config()->l10nRoot() . '/en/date');
        }
        L10n::set(App::config()->l10nRoot() . '/' . App::lang()->getLang() . '/main');
        L10n::set(App::config()->l10nRoot() . '/' . App::lang()->getLang() . '/public');
        L10n::set(App::config()->l10nRoot() . '/' . App::lang()->getLang() . '/plugins');

        // Set lexical lang
        App::lexical()->setLexicalLang('admin', App::lang()->getLang());

        // Get en help resources
        $helps = [];
        if (($hfiles = @scandir(implode(DIRECTORY_SEPARATOR, [App::config()->l10nRoot(), 'en', 'help']))) !== false) {
            foreach ($hfiles as $hfile) {
                if (preg_match('/^(.*)\.html$/', $hfile, $m)) {
                    $helps[$m[1]] = implode(DIRECTORY_SEPARATOR, [App::config()->l10nRoot(), 'en', 'help', $hfile]);
                }
            }
        }
        unset($hfiles);

        // Get user lang help resources
        if (App::lang()->getLang() !== 'en' && ($hfiles = @scandir(implode(DIRECTORY_SEPARATOR, [App::config()->l10nRoot(), App::lang()->getLang(), 'help']))) !== false) {
            foreach ($hfiles as $hfile) {
                if (preg_match('/^(.*)\.html$/', $hfile, $m)) {
                    $helps[$m[1]] = implode(DIRECTORY_SEPARATOR, [App::config()->l10nRoot(), App::lang()->getLang(), 'help', $hfile]);
                }
            }
        }
        unset($hfiles);

        // Set help resources
        foreach ($helps as $key => $file) {
            App::upgrade()->resources()->set('help', $key, $file);
        }
        unset($helps);

        // Contextual help flag
        App::upgrade()->resources()->context(false);
    }

    /**
     * Get upgrade Url instance.
     *
     * @return  Url     The upgrade URL handler
     */
    public function url(): Url
    {
        if (!isset($this->url)) {
            $this->url = new Url();
        }

        return $this->url;
    }

    /**
     * Get upgrade menus instance.
     *
     * @return  Menus   The menu
     */
    public function menus(): Menus
    {
        if (!isset($this->menus)) {
            $this->menus = new Menus();
        }

        return $this->menus;
    }

    /**
     * Get upgrade resources instance.
     *
     * @return  Resources   The menu
     */
    public function resources(): Resources
    {
        if (!isset($this->resources)) {
            $this->resources = new Resources();
        }

        return $this->resources;
    }

    /**
     * Kill admin session helper
     */
    public function killAdminSession(): void
    {
        // Kill session
        App::session()->destroy();

        // Unset cookie if necessary
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            unset($_COOKIE[self::COOKIE_NAME]);
            setcookie(self::COOKIE_NAME, '', -600, '', '', App::config()->adminSsl());
        }
    }
}
