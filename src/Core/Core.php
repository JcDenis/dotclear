<?php
/**
 * @class Dotclear\Core\Core
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

use Dotclear\Exception\PrependException;
use Dotclear\File\Files;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Utils\Crypt;
use Dotclear\Utils\Dt;
use Dotclear\Utils\L10n;
use Dotclear\Utils\Statistic;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Core
{
    # Traits
    use \Dotclear\Core\Blog\TraitBlog;
    use \Dotclear\Core\Instance\TraitAuth;
    use \Dotclear\Core\Instance\TraitAutoload;
    use \Dotclear\Core\Instance\TraitBehavior;
    use \Dotclear\Core\Instance\TraitBlogs;
    use \Dotclear\Core\Instance\TraitConfiguration;
    use \Dotclear\Core\Instance\TraitConnection;
    use \Dotclear\Core\Instance\TraitError;
    use \Dotclear\Core\Instance\TraitFormater;
    use \Dotclear\Core\Instance\TraitLog;
    use \Dotclear\Core\Instance\TraitMedia;
    use \Dotclear\Core\Instance\TraitMeta;
    use \Dotclear\Core\Instance\TraitNonce;
    use \Dotclear\Core\Instance\TraitPostType;
    use \Dotclear\Core\Instance\TraitRest;
    use \Dotclear\Core\Instance\TraitSession;
    use \Dotclear\Core\Instance\TraitUsers;
    use \Dotclear\Core\Instance\TraitUrl;
    use \Dotclear\Core\Instance\TraitVersion;
    use \Dotclear\Core\Instance\TraitWiki2xhtml;

    /** @var string             Current Process */
    protected $process;

    /** @var Core               Core singleton instance */
    private static $instance;

    /// @name Core instance methods
    //@{
    /**
     * Disabled children constructor and direct instance
     */
    final protected function __construct()
    {
    }

    /*
     * @throws CoreException
     */
    final public function __clone()
    {
        throw new PrependException('Core instance can not be cloned.', 6);
    }

    /**
     * @throws CoreException
     */
    final public function __sleep()
    {
        throw new PrependException('Core instance can not be serialized.', 6);
    }

    /**
     * @throws CoreException
     */
    final public function __wakeup()
    {
        throw new PrependException('Core instance can not be deserialized.', 6);
    }

    /**
     * Get core unique instance
     *
     * @param   string|null     $blog_id    Blog ID on first public process call
     * @return  Core                        Core (Process) instance
     */
    final public static function coreInstance(?string $blog_id = null): Core
    {
        if (!static::$instance) {
            Statistic::start();

            # Two stage instanciation (construct then process)
            static::$instance = new static();
            static::$instance->process($blog_id);
        }

        return static::$instance;
    }
    //@}

    /**
     * Start Dotclear process
     *
     * @param   string  $process    public/admin/install/...
     */
    public function process()
    {
        # Avoid direct call to Core
        if (get_class() == get_class($this)) {
            throw new PrependException('Server error', 'Direct call to core before process starts.', 6);
        }

        # Add custom regs
        Html::$absolute_regs[] = '/(<param\s+name="movie"\s+value=")(.*?)(")/msu';
        Html::$absolute_regs[] = '/(<param\s+name="FlashVars"\s+value=".*?(?:mp3|flv)=)(.*?)(&|")/msu';

        # Encoding
        mb_internal_encoding('UTF-8');

        # Timezone
        Dt::setTZ('UTC');

        # Disallow every special wrapper
        Http::unregisterWrapper();

        # Find configuration file
        if (!defined('DOTCLEAR_CONFIG_PATH')) {
            if (isset($_SERVER['DOTCLEAR_CONFIG_PATH'])) {
                define('DOTCLEAR_CONFIG_PATH', $_SERVER['DOTCLEAR_CONFIG_PATH']);
            } elseif (isset($_SERVER['REDIRECT_DOTCLEAR_CONFIG_PATH'])) {
                define('DOTCLEAR_CONFIG_PATH', $_SERVER['REDIRECT_DOTCLEAR_CONFIG_PATH']);
            } else {
                define('DOTCLEAR_CONFIG_PATH', root_path('config.php'));
            }
        }

        # No configuration ? start installalation process
        if (!is_file(DOTCLEAR_CONFIG_PATH)) {
            # Set Dotclear configuration constants for installation process
            if ($this->process == 'Install') {
                $this->initConfiguration(self::getDefaultConfig());

                # Stop core process here in Install process
                return;
            }
            # Redirect to installation process
            Http::redirect(preg_replace(
                ['%admin/index.php$%', '%admin/$%', '%index.php$%', '%/$%'],
                '',
                filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            ) . '/admin/install.php');

            exit;
        }

        # Set plateform (user) configuration constants
        $this->initConfiguration(self::getDefaultConfig(), DOTCLEAR_CONFIG_PATH);

        # Starting from debug mode, display all errors
        if ($this->config()->run_level >= DOTCLEAR_RUN_DEBUG) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL | E_STRICT);
        }

        # Set some Http stuff
        Http::$https_scheme_on_443 = $this->config()->force_scheme_443;
        Http::$reverse_proxy = $this->config()->reverse_proxy;
        Http::trimRequest();

        # Check cryptography algorithm
        if ($this->config()->crypt_algo != 'sha1') {
            # Check length of cryptographic algorithm result and exit if less than 40 characters long
            if (strlen(Crypt::hmac($this->config()->master_key, $this->config()->vendor_name, $this->config()->crypt_algo)) < 40) {
                if ($this->process != 'Admin') {
                    throw new PrependException('Server error', 'Site temporarily unavailable');
                } else {
                    throw new PrependException('Dotclear error', $this->config()->crypt_algo . ' cryptographic algorithm configured is not strong enough, please change it.');
                }
                exit;
            }
        }

        # Check existence of cache directory
        if (!is_dir($this->config()->cache_dir)) {
            /* Try to create it */
            @Files::makeDir($this->config()->cache_dir);
            if (!is_dir($this->config()->cache_dir)) {
                /* Admin must create it */
                if (!in_array($this->process, ['Admin', 'Install'])) {
                    throw new PrependException('Server error', 'Site temporarily unavailable');
                } else {
                    throw new PrependException('Dotclear error', $this->config()->cache_dir . ' directory does not exist. Please create it.');
                }
                exit;
            }
        }

        # Check existence of var directory
        if (!is_dir($this->config()->var_dir)) {
            // Try to create it
            @Files::makeDir($this->config()->var_dir);
            if (!is_dir($this->config()->var_dir)) {
                // Admin must create it
                if (!in_array($this->process, ['Admin', 'Install'])) {
                    throw new PrependException('Server error', 'Site temporarily unavailable');
                } else {
                    throw new PrependException('Dotclear error', $this->config()->var_dir . ' directory does not exist. Please create it.');
                }
                exit;
            }
        }

        # Start l10n
        L10n::init();

        # Define current process for files check
        define('DOTCLEAR_PROCESS', $this->process);

        ##
        # No call to trait methods before here.
        ##

        # Force database connection instanciation
        dotclear()->con();

        # Add top behaviors
        $this->registerTopBehaviors();

        # Register Core post types
        $this->posttype()->setPostType('post', '?handler=admin.post&id=%d', $this->url()->getURLFor('post', '%s'), 'Posts');

        # Register shutdown function
        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Shutdown function
     *
     * Close properly session and connection.
     */
    public function shutdown(): void
    {
        # Explicitly close session before DB connection
        try {
            if (session_id()) {
                session_write_close();
            }
        } catch (\Exception $e) {    // @phpstan-ignore-line
        }
        $this->con()->close();
    }

    /**
     * Empty templates cache directory
     */
    public static function emptyTemplatesCache(): void //! move this
    {
        if (is_dir(implode_path(dotclear()->config()->cache_dir, 'cbtpl'))) {
            Files::deltree(implode_path(dotclear()->config()->cache_dir, 'cbtpl'));
        }
    }

    private static function getDefaultConfig(): array
    {
        return [
            'admin_mailform'        => [null, ''],
            'admin_ssl'             => [null, true],
            'admin_url'             => [null, ''],
            'backup_dir'            => [null, root_path()],
            'base_dir'              => [null, root_path('..')],
            'cache_dir'             => [null, root_path('..', 'cache')],
            'core_update_channel'   => [null, 'stable'],
            'core_update_noauto'    => [null, false],
            'core_update_url'       => [null, 'https://download.dotclear.org/versions.xml'],
            'core_version'          => [false, trim(file_get_contents(root_path('version')))],
            'core_version_break'    => [false, '3.0'],
            'crypt_algo'            => [null, 'sha1'],
            'database_driver'       => [true, ''],
            'database_host'         => [true, ''],
            'database_name'         => [true, ''],
            'database_password'     => [true, ''],
            'database_persist'      => [null, true],
            'database_prefix'       => [null, 'dc_'],
            'database_user'         => [true, ''],
            'digests_dir'           => [null, root_path('..', 'digests')],
            'force_scheme_443'      => [null, true],
            'iconset_dir'           => [null, root_path('Iconset')],
            'iconset_official'      => [false, 'Legacy,ThomasDaveluy'],
            'iconset_update_url'    => [null, ''],
            'jquery_default'        => [null, '3.6.0'],
            'l10n_dir'              => [null, root_path('locales')],
            'l10n_update_url'       => [null, 'https://services.dotclear.net/dc2.l10n/?version=%s'],
            'media_dir_showhidden'  => [null, false],
            'media_upload_maxsize'  => [false, Files::getMaxUploadFilesize()],
            'master_key'            => [true, ''],
            'module_allow_multi'    => [null, false],
            'php_next_required'     => [false, '7.4'],
            'plugin_dir'            => [null, root_path('Plugin')],
            'plugin_official'       => [false, 'AboutConfig,Akismet,Antispam,Attachments,Blogroll,Dclegacy,FairTrackbacks,ImportExport,Maintenance,Pages,Pings,SimpleMenu,Tags,ThemeEditor,UserPref,Widgets,LegacyEditor,CKEditor,Breadcrumb'],
            'plugin_update_url'     => [null,  'https://update.dotaddict.org/dc2/plugins.xml'],
            'query_timeout'         => [null, 4],
            'reverse_proxy'         => [null, true],
            'run_level'             => [null, 0],
            'root_dir'              => [false, root_path()], //Alias for DOTCLEAR_ROOT_DIR
            'session_name'          => [null, 'dcxd'],
            'session_ttl'           => [null, '-120 minutes'],
            'store_allow_repo'      => [null, true],
            'store_update_noauto'   => [null, false],
            'template_default'      => [null, 'Mustek'],
            'theme_dir'             => [null, root_path('Theme')],
            'theme_official'        => [false, 'Berlin,BlueSilence,Blowup,CustomCSS,Ductile'],
            'theme_update_url'      => [null, 'https://update.dotaddict.org/dc2/themes.xml'],
            'var_dir'               => [null, root_path('..', 'var')],
            'vendor_name'           => [null, 'Dotclear'],
            'xmlrpc_url'            => [null, '%1$sxmlrpc/%2$s'],
        ];
    }
}
