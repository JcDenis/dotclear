<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

// Dotclear\Core\Core
use Dotclear\App;
use Dotclear\Core\Blog\Blog;
use Dotclear\Core\Blogs\Blogs;
use Dotclear\Core\Formater\Formater;
use Dotclear\Core\Log\Log;
use Dotclear\Core\Media\Media;
use Dotclear\Core\Meta\Meta;
use Dotclear\Core\Nonce\Nonce;
use Dotclear\Core\Session\Session;
use Dotclear\Core\Permissions\Permissions;
use Dotclear\Core\PostType\PostType;
use Dotclear\Core\PostType\PostTypeDescriptor;
use Dotclear\Core\Url\Url;
use Dotclear\Core\User\User;
use Dotclear\Core\Users\Users;
use Dotclear\Core\Version\Version;
use Dotclear\Core\Wiki\Wiki;
use Dotclear\Database\AbstractConnection;
use Dotclear\Exception\InvalidConfiguration;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Autoload;
use Dotclear\Helper\Behavior;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Configuration;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\ErrorTrait;
use Dotclear\Helper\L10n;
use Dotclear\Helper\MagicTrait;
use Dotclear\Helper\RestServer;
use Dotclear\Helper\Statistic;
use Dotclear\Helper\File\Path;
use Error;
use Exception;

/**
 * Core for process.
 *
 * @ingroup Process Core
 */
class Core
{
    use ErrorTrait;
    use MagicTrait;

    /**
     * @var Autoload $autoload
     *               Autoload instance
     */
    private $autoload;

    /**
     * @var Behavior $behavior
     *               Behavior instance
     */
    private $behavior;

    /**
     * @var null|Blog $blog
     *                Blog instance
     */
    private $blog;

    /**
     * @var Blogs $blogs
     *            Blogs instance
     */
    private $blogs;

    /**
     * @var AbstractConnection $con
     *                         AbstractConnection instance
     */
    private $con;

    /**
     * @var Configuration $config
     *                    Configuration instance
     */
    private $config;

    /**
     * @var Formater $formater
     *               Formater instance
     */
    private $formater;

    /**
     * @var Log $log
     *          Log instance
     */
    private $log;

    /**
     * @var null|Media $media
     *                 Media instance
     */
    private $media;

    /**
     * @var Meta $meta
     *           Meta instance
     */
    private $meta;

    /**
     * @var Nonce $nonce
     *            Nonce instance
     */
    private $nonce;

    /**
     * @var Permissions $permissions
     *                  Permissions instance
     */
    private $permissions;

    /**
     * @var PostType $posttype
     *               PostType instance
     */
    private $posttype;

    /**
     * @var string $prefix
     *             Database table prefix
     */
    private $prefix = '';

    /**
     * @var RestServer $rest
     *                 RestServer instance
     */
    private $rest;

    /**
     * @var Session $session
     *              Session instance
     */
    private $session;

    /**
     * @var Url $url
     *          Url instance
     */
    private $url;

    /**
     * @var User $user
     *           User instance
     */
    private $user;

    /**
     * @var Users $users
     *            Users instance
     */
    private $users;

    /**
     * @var Version $version
     *              Version instance
     */
    private $version;

    /**
     * @var Wiki $wiki
     *           Wiki instance
     */
    private $wiki;

    /**
     * @var array<int,array> $top_behaviors
     *                       top behaviors
     */
    private static $top_behaviors = [];

    /**
     * @var null|string $config_path
     *                  Configuration file path
     */
    protected $config_path;

    /**
     * @var string $process
     *             Current Process
     */
    protected $process;

    // / @name Core instance methods and magic
    // @{
    /**
     * Consructor.
     *
     * This method is mark as <b>final</b>
     * to cope with singleton instance.
     *
     * Set up some (no config) static features.
     */
    final public function __construct()
    {
        // Start time and memory statistics (dev)
        Statistic::start();

        // Set default encoding to UTF-8
        mb_internal_encoding('UTF-8');

        // Set default timezone to UTC
        Clock::setTZ('UTC');

        // Disallow every special wrapper
        Http::unregisterWrapper();

        // Add custom regs
        Html::$absolute_regs[] = '/(<param\s+name="movie"\s+value=")(.*?)(")/msu';
        Html::$absolute_regs[] = '/(<param\s+name="FlashVars"\s+value=".*?(?:mp3|flv)=)(.*?)(&|")/msu';
    }
    // @}

    // / @name Core others instances methods
    // @{
    /**
     * Get autoload instance.
     *
     * Instanciate a core autoloader for custom
     * third party (plugins/themes) specifics needs
     *
     * Autoload methods are accesible App::core()->autoload()
     *
     * @return Autoload The autoload instance
     */
    final public function autoload(): Autoload
    {
        if (!($this->autoload instanceof Autoload)) {
            $this->autoload = new Autoload(prepend: true);
        }

        return $this->autoload;
    }

    /**
     * Get behavior instance.
     *
     * Behavior methods are accesible from App::core()->behavior()
     *
     * @return Behavior The behaviors instance
     */
    final public function behavior(): Behavior
    {
        if (!($this->behavior instanceof Behavior)) {
            $this->behavior = new Behavior();
        }

        return $this->behavior;
    }

    /**
     * Get blog instance.
     *
     * Blog methods are accesible from App::core()->blog()
     *
     * @return null|Blog The blog instance
     */
    final public function blog(): ?Blog
    {
        return $this->blog;
    }

    /**
     * Get blogs instance.
     *
     * Blogs methods are accesible from App::core()->blogs()
     *
     * @return Blogs The blogs instance
     */
    final public function blogs(): Blogs
    {
        if (!($this->blogs instanceof Blogs)) {
            $this->blogs = new Blogs();
        }

        return $this->blogs;
    }

    /**
     * Get database connection instance.
     *
     * Database connection methods are accesible from App::core()->con()
     *
     * @return AbstractConnection The connection instance
     */
    final public function con(): AbstractConnection
    {
        if (!($this->con instanceof AbstractConnection)) {
            try {
                $prefix = $this->config()->get('database_prefix');
                $driver = $this->config()->get('database_driver');

                // Create connection instance
                $con = AbstractConnection::init(
                    $driver,
                    $this->config()->get('database_host'),
                    $this->config()->get('database_name'),
                    $this->config()->get('database_user'),
                    $this->config()->get('database_password'),
                    $this->config()->get('database_persist')
                );

                // Define weak_locks for mysql
                if (in_array($driver, ['mysqli', 'mysqlimb4'])) {
                    $con::$weak_locks = true;
                }

                // Define searchpath for postgresql
                if ('pgsql' == $driver) {
                    $searchpath = explode('.', $prefix, 2);
                    if (count($searchpath) > 1) {
                        $prefix = $searchpath[1];
                        $sql    = 'SET search_path TO ' . $searchpath[0] . ',public;';
                        $con->execute($sql);
                    }
                }

                // Set table prefix in core
                $this->prefix = $prefix;

                $this->con = $con;
            } catch (Exception $e) {
                $msg = sprintf(
                    __('<p>This either means that the username and password information in ' .
                    'your <strong>dotclear.conf.php</strong> file is incorrect or we can\'t contact ' .
                    'the database server at "<em>%s</em>". This could mean your ' .
                    'host\'s database server is down.</p> ' .
                    '<ul><li>Are you sure you have the correct username and password?</li>' .
                    '<li>Are you sure that you have typed the correct hostname?</li>' .
                    '<li>Are you sure that the database server is running?</li></ul>' .
                    '<p>If you\'re unsure what these terms mean you should probably contact ' .
                    'your host. If you still need help you can always visit the ' .
                    '<a href="https://forum.dotclear.net/">Dotclear Support Forums</a>.</p>'),
                    ('' != $this->config()->get('database_host') ? $this->config()->get('database_host') : 'localhost')
                );

                throw new InvalidConfiguration(
                    false === $this->production() ?
                        $msg . '<p>' . __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                        $msg,
                    500,
                    $e
                );
            }
        }

        return $this->con;
    }

    /**
     * Get dotclear configuration instance.
     *
     * Configuration methods are accesible from App::core()->config()
     *
     * @return Configuration The configuration instance
     */
    final public function config(): Configuration
    {
        if (!($this->config instanceof Configuration)) {
            $config_file  = (null !== $this->config_path && is_file($this->config_path) ? $this->config_path : []);
            $this->config = new Configuration($this->getDefaultConfig(), $config_file);

            // Alias that could be required before first connection instance
            $this->prefix = $this->config->get('database_prefix');
        }

        return $this->config;
    }

    /**
     * Get formater instance.
     *
     * Formater methods are accesible from App::core()->formater()
     *
     * @return Formater The formater instance
     */
    final public function formater(): Formater
    {
        if (!($this->formater instanceof Formater)) {
            $this->formater = new Formater();
        }

        return $this->formater;
    }

    /**
     * Get log instance.
     *
     * Log methods are accesible from App::core()->log()
     *
     * @return Log The log instance
     */
    final public function log(): Log
    {
        if (!($this->log instanceof Log)) {
            $this->log = new Log();
        }

        return $this->log;
    }

    /**
     * Get media instance.
     *
     * Caller MUST cope with Media instance failure.
     *
     * Media methods are accesible from App::core()->media()
     *
     * @return Media The media instance
     */
    final public function media(): ?Media
    {
        if (!($this->media instanceof Media)) {
            try {
                $this->media = new Media();
            } catch (Exception $e) {
                $this->media = null;
            }
        }

        return $this->media;
    }

    /**
     * Get meta instance.
     *
     * Meta methods are accesible from App::core()->meta()
     *
     * @return Meta The meta instance
     */
    final public function meta(): Meta
    {
        if (!($this->meta instanceof Meta)) {
            $this->meta = new Meta();
        }

        return $this->meta;
    }

    /**
     * Get nonce instance.
     *
     * Nonce methods are accesible from App::core()->nonce()
     *
     * @return Nonce The nonce instance
     */
    final public function nonce(): Nonce
    {
        if (!($this->nonce instanceof Nonce)) {
            $this->nonce = new Nonce();
        }

        return $this->nonce;
    }

    /**
     * Get permissions instance.
     *
     * Permissions methods are accesible from App::core()->permissions()
     *
     * @return Permissions The permission types instance
     */
    final public function permissions(): Permissions
    {
        if (!($this->permissions instanceof Permissions)) {
            $this->permissions = new Permissions();
        }

        return $this->permissions;
    }

    /**
     * Get posttype instance.
     *
     * PostType methods are accesible from App::core()->posttype()
     *
     * @return PostType The post types instance
     */
    final public function posttype(): PostType
    {
        if (!($this->posttype instanceof PostType)) {
            $this->posttype = new PostType();
        }

        return $this->posttype;
    }

    /**
     * Get REST server instance.
     *
     * RestServer methods are accesible from App::core()->rest()
     *
     * @return RestServer The REST server instance
     */
    final public function rest(): RestServer
    {
        if (!($this->rest instanceof RestServer)) {
            $this->rest = new RestServer();
        }

        return $this->rest;
    }

    /**
     * Get session instance.
     *
     * Session methods are accesible from App::core()->session()
     *
     * @return Session The session instance
     */
    final public function session(): Session
    {
        if (!($this->session instanceof Session)) {
            $id            = defined('DOTCLEAR_AUTH_SESS_ID') ? DOTCLEAR_AUTH_SESS_ID : null;
            $this->session = new Session(external_session_id: $id);
        }

        return $this->session;
    }

    /**
     * Get url (public) instance.
     *
     * Public URL methods are accesible from App::core()->url()
     *
     * @return Url The public URL instance
     */
    final public function url(): Url
    {
        if (!($this->url instanceof Url)) {
            $this->url = new Url();
        }

        return $this->url;
    }

    /**
     * Get user (auth) instance.
     *
     * To use a custom user authentication class,
     * you can set constant DOTCLEAR_USER_CLASS to whatever you want.
     * Your new class *should* inherits Dotclear\Core\User\User class.
     *
     * User methods are accesible from App::core()->user()
     *
     * @return User The user instance
     */
    final public function user(): User
    {
        if (!($this->user instanceof User)) {
            try {
                $dc_user_class = __NAMESPACE__ . '\\User\\User';
                $class         = defined('DOTCLEAR_USER_CLASS') ? \DOTCLEAR_USER_CLASS : $dc_user_class;

                // Check if auth class exists
                if (!class_exists($class)) {
                    throw new Exception(sprintf('Authentication class %s does not exist.', $class));
                }

                // Check if auth class inherit Dotclear auth class
                if ($class != $dc_user_class && !is_subclass_of($class, $dc_user_class)) {
                    throw new Exception(sprintf('Authentication class %s does not inherit %s.', $class, $dc_user_class));
                }

                $this->user = new $class();
            } catch (Exception $e) {
                throw new InvalidConfiguration(
                    false === $this->production() ?
                        sprintf(__('Something went wrong while trying to load authentication class: %s'), $e->getMessage()) :
                        __('Unable to do authentication')
                );
            }
        }

        return $this->user;
    }

    /**
     * Get users instance.
     *
     * Users methods are accesible from App::core()->users()
     *
     * @return Users The users instance
     */
    final public function users(): Users
    {
        if (!($this->users instanceof Users)) {
            $this->users = new Users();
        }

        return $this->users;
    }

    /**
     * Get version instance.
     *
     * Version methods are accesible from App::core()->version()
     *
     * @return Version The version instance
     */
    final public function version(): Version
    {
        if (!($this->version instanceof Version)) {
            $this->version = new Version();
        }

        return $this->version;
    }

    /**
     * Get wiki (wiki2xhtml) instance.
     *
     * Wiki synthax methods are accesible from App::core()->wiki()
     *
     * @return Wiki The wiki synthax instance
     */
    final public function wiki(): Wiki
    {
        if (!($this->wiki instanceof Wiki)) {
            $this->wiki = new Wiki();
        }

        return $this->wiki;
    }
    // @}

    // / @name Core methods
    // @{
    /**
     * Start Dotclear Core process.
     */
    public function process(string $_ = null): void
    {
        // Find configuration file
        if (null === $this->config_path) {
            if (defined('DOTCLEAR_CONFIG_PATH')) {
                $this->config_path = DOTCLEAR_CONFIG_PATH;
            } elseif (isset($_SERVER['DOTCLEAR_CONFIG_PATH'])) {
                $this->config_path = $_SERVER['DOTCLEAR_CONFIG_PATH'];
            } elseif (isset($_SERVER['REDIRECT_DOTCLEAR_CONFIG_PATH'])) {
                $this->config_path = $_SERVER['REDIRECT_DOTCLEAR_CONFIG_PATH'];
            } else {
                $this->config_path = Path::implodeBase('dotclear.conf.php');
            }
        }

        // No configuration ?
        if (!is_file($this->config_path)) {
            // Stop core process here in installalation process
            if ('Install' == $this->process) {
                return;
            }

            throw new Exception('Application is not installed.');
        }

        // In non production environment, display all errors
        if ($this->production()) {
            ini_set('display_errors', '0');
        } else {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        }

        // Start l10n
        L10n::init();

        // Find a default appropriate language (used by Exceptions)
        foreach (Http::getAcceptLanguages() as $lang) {
            if ('en' == $lang || false !== L10n::set(Path::implode($this->config()->get('l10n_dir'), $lang, 'main'))) {
                L10n::lang($lang);

                break;
            }
        }

        // Set some Http stuff
        Http::$https_scheme_on_443 = $this->config()->get('force_scheme_443');
        Http::$reverse_proxy       = $this->config()->get('reverse_proxy');

        // Check master key
        if (32 > strlen($this->config()->get('master_key'))) {
            throw new InvalidConfiguration(
                false === $this->production() ?
                    __('Master key is not strong enough, please change it.') :
                    __('Unsufficient master key')
            );
        }

        // Check cryptography algorithm
        if ('sha1' == $this->config()->get('crypt_algo')) {
            // Check length of cryptographic algorithm result and exit if less than 40 characters long
            if (40 > strlen(Crypt::hmac($this->config()->get('master_key'), $this->config()->get('vendor_name'), $this->config()->get('crypt_algo')))) {
                throw new InvalidConfiguration(
                    false === $this->production() ?
                        sprintf(__('%s cryptographic algorithm configured is not strong enough, please change it.'), $this->config()->get('crypt_algo')) :
                        __('Cryptographic error')
                );
            }
        }

        // Check existence of digests directory
        if (!is_dir($this->config()->get('digests_dir'))) {
            // Try to create it
            @Files::makeDir($this->config()->get('digests_dir'));
        }

        // Check existence of cache directory
        if (!is_dir($this->config()->get('cache_dir'))) {
            // Try to create it
            @Files::makeDir($this->config()->get('cache_dir'));
            if (!is_dir($this->config()->get('cache_dir'))) {
                throw new InvalidConfiguration(
                    false === $this->production() ?
                        sprintf(__('%s directory does not exist. Please create it.'), $this->config()->get('cache_dir')) :
                        __('Unable to find cache directory')
                );
            }
        }

        // Check existence of var directory
        if (!is_dir($this->config()->get('var_dir'))) {
            // Try to create it
            @Files::makeDir($this->config()->get('var_dir'));
            if (!is_dir($this->config()->get('var_dir'))) {
                throw new InvalidConfiguration(
                    false === $this->production() ?
                    sprintf('%s directory does not exist. Please create it.', $this->config()->get('var_dir')) :
                    __('Unable to find var directory')
                );
            }
        }

        // Check configuration required values
        if ($this->config()->error()->flag()) {
            throw new InvalidConfiguration(
                false === $this->production() ?
                    implode("\n", $this->config()->error()->dump()) :
                    __('Configuration file is not complete.')
            );
        }

        // Add top behaviors
        foreach (self::$top_behaviors as $behavior) {
            $this->behavior()->add($behavior[0], $behavior[1]);
        }

        // Register Core post types
        $this->posttype()->setPostType(new PostTypeDescriptor(
            type: 'post',
            admin: '?handler=admin.post&id=%d',
            public: $this->url()->getURLFor('post', '%s'),
            label: __('Posts')
        ));

        // Register shutdown function
        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Check current process.
     *
     * @param null|string $process Process name to check, or null to get its name
     *
     * @return bool|string True this is the process, or the process name
     */
    final public function processed(?string $process = null): string|bool
    {
        return null === $process ? $this->process : strtolower($this->process) == strtolower($process);
    }

    /**
     * Get database table prefix.
     *
     * @return string The database table prefix
     */
    final public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Check production environment.
     *
     * In production, errors are not fully explain,
     * repositories check Dotclear minimum version,
     * Core statisics are not available, etc...
     *
     * production mode is set in dotclear configuration file.
     *
     * @return bool True for production env
     */
    final public function production(): bool
    {
        return false !== $this->config()->get('production');
    }

    /**
     * Check rescue mode.
     *
     * In rescue mode, modules are read but not loaded.
     *
     * rescue mode is set from admin logging page.
     *
     * @return bool True for rescue mode
     */
    final public function rescue()
    {
        return isset($_SESSION['sess_safe_mode']) && $_SESSION['sess_safe_mode'];
    }

    /**
     * Shutdown method.
     *
     * Close properly session and connection.
     */
    final public function shutdown(): void
    {
        if (session_id()) {
            session_write_close();
        }

        try {
            $this->con->close();
        } catch (Exception|Error) {
        }
    }

    /**
     * Return default datetime display timezone.
     *
     * Child Process should implement this method
     * according to its specific default datetime display timezone.
     *
     * @return string The default datetime display timezone
     */
    public function timezone(): string
    {
        return Clock::getTZ();
    }

    /**
     * Add Top Behavior statically before class instanciate.
     *
     * Core::addTopBehavior('MyBehavior', 'MyFunction');
     * also work from other child class.
     *
     * @param string   $behavior The behavior
     * @param callable $callback The function
     */
    final public static function addTopBehavior(string $behavior, callable $callback): void
    {
        array_push(self::$top_behaviors, [$behavior, $callback]);
    }

    /**
     * Sets the blog to use.
     *
     * @param string $blog_id The blog ID
     */
    final public function setBlog(string $blog_id): void
    {
        try {
            $this->blog = new Blog($blog_id);
        } catch (Exception $e) {
            throw new InvalidConfiguration(
                false === $this->production() ?
                    sprintf(__('Something went wrong while trying to load blog: %s'), $e->getMessage()) :
                    __('Unable to load blog')
            );
        }
    }

    /**
     * Unsets blog property.
     */
    final public function unsetBlog(): void
    {
        $this->blog = null;
    }

    /**
     * Empty templates cache directory.
     */
    final public function emptyTemplatesCache(): void // ! move this
    {
        if (is_dir(Path::implode(App::core()->config()->get('cache_dir'), 'cbtpl'))) {
            Files::deltree(Path::implode(App::core()->config()->get('cache_dir'), 'cbtpl'));
        }
    }
    // @}

    /**
     * Default Dotclear configuration.
     *
     * This configuration must be completed by
     * the dotclear.conf.php file.
     *
     * @return array Initial configuation
     */
    private function getDefaultConfig(): array
    {
        return [
            'admin_adblocker_check' => [null, false],
            'admin_mailform'        => [null, ''],
            'admin_ssl'             => [null, true],
            'admin_url'             => [null, ''],
            'backup_dir'            => [null, Path::implodeBase()],
            'base_dir'              => [null, Path::implodeBase()],
            'cache_dir'             => [null, Path::implodeBase('cache')],
            'core_update_channel'   => [null, 'stable'],
            'core_update_noauto'    => [null, false],
            'core_update_url'       => [null, 'https://download.dotclear.org/versions.xml'],
            'core_version'          => [false, trim(file_get_contents(Path::implodeSrc('version')))],
            'core_version_break'    => [false, '3.0'],
            'crypt_algo'            => [null, 'sha1'],
            'database_driver'       => [true, ''],
            'database_host'         => [true, ''],
            'database_name'         => [true, ''],
            'database_password'     => [true, ''],
            'database_persist'      => [null, true],
            'database_prefix'       => [null, 'dc_'],
            'database_user'         => [true, ''],
            'digests_dir'           => [null, Path::implodeBase('digests')],
            'file_serve_type'       => [null, ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'css', 'js', 'swf', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'html', 'xml', 'json', 'txt', 'zip']],
            'force_scheme_443'      => [null, true],
            'jquery_default'        => [null, '3.6.0'],
            'l10n_dir'              => [null, Path::implodeSrc('locales')],
            'l10n_update_url'       => [null, 'https://services.dotclear.net/dc2.l10n/?version=%s'],
            'media_dir_showhidden'  => [null, false],
            'media_upload_maxsize'  => [false, Files::getMaxUploadFilesize()],
            'master_key'            => [true, ''],
            'module_allow_multi'    => [null, false],
            'php_next_required'     => [false, '8.1'],
            'plugin_dirs'           => [null, [Path::implodeSrc('Plugin')]],
            'plugin_official'       => [false, ['AboutConfig', 'Akismet', 'Antispam', 'Attachments', 'Blogroll', 'Dclegacy', 'FairTrackbacks', 'ImportExport', 'Maintenance', 'Pages', 'Pings', 'SimpleMenu', 'Tags', 'ThemeEditor', 'UserPref', 'Widgets', 'LegacyEditor', 'CKEditor', 'Breadcrumb']],
            'plugin_update_url'     => [null,  'https://update.dotaddict.org/dc2/plugins.xml'],
            'production'            => [null, true],
            'query_timeout'         => [null, 4],
            'reverse_proxy'         => [null, true],
            'session_name'          => [null, 'dcxd'],
            'session_ttl'           => [null, '-120 minutes'],
            'sqlite_dir'            => [null, Path::implodeBase('db')],
            'store_allow_repo'      => [null, true],
            'store_update_noauto'   => [null, false],
            'template_default'      => [null, 'mustek'],
            'theme_default'         => [null, 'Berlin'],
            'theme_dirs'            => [null, [Path::implodeSrc('Theme')]],
            'theme_official'        => [false, ['Berlin', 'BlueSilence', 'Blowup', 'CustomCSS', 'Ductile']],
            'theme_update_url'      => [null, 'https://update.dotaddict.org/dc2/themes.xml'],
            'var_dir'               => [null, Path::implodeBase('var')],
            'vendor_name'           => [null, 'Dotclear'],
            'xmlrpc_url'            => [null, '%1$sxmlrpc/%2$s'],
        ];
    }
}
