<?php

/**
 * @package         Dotclear
 * @subpackage      Upgrade
 *
 * @defsubpackage   Upgrade        Application upgrade services
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

/**
 * @namespace   Dotclear.Core.Upgrade
 * @brief       Dotclear application upgrade utilities.
 */

namespace Dotclear\Core\Upgrade;

use Dotclear\App;
use Dotclear\Core\Backend\Resources;
use Dotclear\Core\Utility as AbstractUtility;
use Dotclear\Exception\ContextException;
use Dotclear\Exception\PreconditionException;
use Dotclear\Helper\L10n;

/**
 * @brief   Utility class for upgrade context.
 *
 * All upgrade process MUST be executed with safe mode.
 * Behaviors are prohibited.
 *
 * @since   2.29
 */
class Utility extends AbstractUtility
{
    public const CONTAINER_ID = 'Upgrade';

    public const UTILITY_PROCESS = [
        \Dotclear\Process\Upgrade\Attic::class,
        \Dotclear\Process\Upgrade\Auth::class,
        \Dotclear\Process\Upgrade\Backup::class,
        \Dotclear\Process\Upgrade\Cache::class,
        \Dotclear\Process\Upgrade\Cli::class,
        \Dotclear\Process\Upgrade\Digests::class,
        \Dotclear\Process\Upgrade\Home::class,
        \Dotclear\Process\Upgrade\Langs::class,
        \Dotclear\Process\Upgrade\Logout::class,
        \Dotclear\Process\Upgrade\Plugins::class,
        \Dotclear\Process\Upgrade\Replay::class,
        \Dotclear\Process\Upgrade\Upgrade::class,
    ];

    /**
     * Upgrade login cookie name.
     *
     * Need to use same cookie as Backend Utility.
     *
     * @var     string  COOKIE_NAME
     */
    public const COOKIE_NAME = 'dc_admin';

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

        // configure upgrade session
        App::session()->configure(
            cookie_name: App::config()->sessionName(),
            cookie_secure: App::config()->adminSsl(),
            ttl: App::config()->sessionTtl()
        );

        // HTTP/1.1
        header('Expires: Mon, 13 Aug 2003 07:48:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

        // Load utility container
        parent::__construct();
    }

    public function getDefaultServices(): array
    {
        return [
            Menus::class     => Menus::class,
            Resources::class => Resources::class,
            Url::class       => Url::class,
        ];
    }

    /**
     * Get upgrade Url instance.
     *
     * @return  Url     The upgrade URL handler
     */
    public function url(): Url
    {
        return $this->get(Url::class);
    }

    /**
     * Get upgrade menus instance.
     *
     * @return  Menus   The menu
     */
    public function menus(): Menus
    {
        return $this->get(Menus::class);
    }

    /**
     * Get upgrade resources instance.
     *
     * @return  Resources   The menu
     */
    public function resources(): Resources
    {
        return $this->get(Resources::class);
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
            App::task()->loadProcess('Cli');

            return true;
        }

        // Instanciate Upgrade instance
        App::upgrade();

        // Always start a session
        App::session()->start();

        // If we have a session we launch it now
        if (App::auth()->checkSession()) {
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
                dotclear_exit();
            }

            // Check nonce from POST requests
            if ($_POST !== [] && (empty($_POST['xd_check']) || !App::nonce()->checkNonce($_POST['xd_check']))) {
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

        if (L10n::set(App::config()->l10nRoot() . '/' . App::lang()->getLang() . '/date') === false && App::lang()->getLang() !== 'en') {
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
     * Kill admin session helper
     */
    public function killAdminSession(): void
    {
        // Kill session
        App::session()->destroy();

        // Unset cookie if necessary
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            unset($_COOKIE[self::COOKIE_NAME]);
            setcookie(self::COOKIE_NAME, '', [
                'expires' => -600,
                'path'    => '',
                'domain'  => '',
                'secure'  => App::config()->adminSsl(),
            ]);
        }
    }

    /**
     * Get menus description.
     *
     * Used by sidebar menu, home dashboard and url handler.
     *
     * @return  list<Icon>
     */
    public function getIcons(): array
    {
        return [
            new Icon(
                id: 'Upgrade',
                name: __('Update'),
                url: 'upgrade.upgrade',
                icon: 'images/menu/update.svg',
                dark: 'images/menu/update-dark.svg',
                perm: App::auth()->isSuperAdmin() && is_readable(App::config()->digestsRoot()),
                descr: __('On this page you can update dotclear to the latest release.')
            ),
            new Icon(
                id: 'Attic',
                name: __('Attic'),
                url: 'upgrade.attic',
                icon: 'images/menu/attic.svg',
                dark: 'images/menu/attic-dark.svg',
                perm: App::auth()->isSuperAdmin() && is_readable(App::config()->digestsRoot()),
                descr: __('On this page you can update dotclear to a release between yours and latest.')
            ),
            new Icon(
                id: 'Backup',
                name: __('Backups'),
                url: 'upgrade.backup',
                icon: 'images/menu/backup.svg',
                dark: 'images/menu/backup-dark.svg',
                perm: App::auth()->isSuperAdmin(),
                descr: __('On this page you can revert your previous installation or delete theses files.')
            ),
            new Icon(
                id: 'Langs',
                name: __('Languages'),
                url: 'upgrade.langs',
                icon: 'images/menu/langs.svg',
                dark: 'images/menu/langs-dark.svg',
                perm: App::auth()->isSuperAdmin(),
                descr: __('Here you can install, upgrade or remove languages for your Dotclear installation.')
            ),
            new Icon(
                id: 'Plugins',
                name: __('Plugins'),
                url: 'upgrade.plugins',
                icon: 'images/menu/plugins.svg',
                dark: 'images/menu/plugins-dark.svg',
                perm: App::auth()->isSuperAdmin(),
                descr: __('On this page you will manage plugins.')
            ),
            new Icon(
                id: 'Cache',
                name: __('Cache'),
                url: 'upgrade.cache',
                icon: 'images/menu/clear-cache.svg',
                dark: 'images/menu/clear-cache-dark.svg',
                perm: App::auth()->isSuperAdmin(),
                descr: __('On this page, you can clear templates and repositories cache.')
            ),
            new Icon(
                id: 'Digests',
                name: __('Digests'),
                url: 'upgrade.digests',
                icon: 'images/menu/digests.svg',
                dark: 'images/menu/digests-dark.svg',
                perm: App::auth()->isSuperAdmin() && is_readable(App::config()->digestsRoot()),
                descr: __('On this page, you can bypass corrupted files or modified files in order to perform update.')
            ),
            new Icon(
                id: 'Replay',
                name: __('Replay'),
                url: 'upgrade.replay',
                icon: 'images/menu/replay.svg',
                dark: 'images/menu/replay-dark.svg',
                perm: App::auth()->isSuperAdmin(),
                descr: __('On this page, you can try to replay update action from a given version if some files remain from last update.')
            ),
        ];
    }
}
