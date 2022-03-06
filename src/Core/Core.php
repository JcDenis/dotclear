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

use Closure;

use Dotclear\Core\Blog\Blog;
use Dotclear\Core\Blogs\Blogs;
use Dotclear\Core\Formater\Formater;
use Dotclear\Core\Log\Log;
use Dotclear\Core\Media\Media;
use Dotclear\Core\Meta\Meta;
use Dotclear\Core\Nonce\Nonce;
use Dotclear\Core\Session\Session;
use Dotclear\Core\PostType\PostType;
use Dotclear\Core\Url\Url;
use Dotclear\Core\User\User;
use Dotclear\Core\Users\Users;
use Dotclear\Core\Version\Version;
use Dotclear\Core\Wiki\Wiki;
use Dotclear\Database\Connection;
use Dotclear\Exception\PrependException;
use Dotclear\File\Files;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Utils\Autoload;
use Dotclear\Utils\Behavior;
use Dotclear\Utils\Configuration;
use Dotclear\Utils\Crypt;
use Dotclear\Utils\Dt;
use Dotclear\Utils\Error;
use Dotclear\Utils\L10n;
use Dotclear\Utils\RestServer;
use Dotclear\Utils\Statistic;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Core
{
    /** @var    Autoload   Autoload instance */
    private $autoload;

    /** @var    Behavior    Behavior instance */
    private $behavior;

    /** @var    Blog    Blog instance */
    private $blog = null;

    /** @var    Blogs   Blogs instance */
    private $blogs;

    /** @var    Connection   Connection instance */
    private $con;

    /** @var    Configuration   Configuration instance */
    private $config;

    /** @var    Error   Error instance */
    private $error;

    /** @var    Formater   Formater instance */
    private $formater;

    /** @var    Log   Log instance */
    private $log;

    /** @var    Media|null  Media instance */
    private $media;

    /** @var    Meta   Meta instance */
    private $meta;

    /** @var    Nonce   Nonce instance */
    private $nonce;

    /** @var    PostType   PostType instance */
    private $posttype;

    /** @var    Rest   Rest instance */
    private $rest;

    /** @var    Session   Session instance */
    private $session;

    /** @var    Url     Url instance */
    private $url;

    /** @var    Auth    Auth instance */
    private $user;

    /** @var    Users   Users instance */
    private $users;

    /** @var    Verion  Version instance */
    private $version;

    /** @var    Wiki    Wiki instance */
    private $wiki;

    /** @var    Core    Core singleton instance */
    private static $instance;

    /** @var    string  Current Process */
    protected $process;

    /** @var    array   top behaviors */
    protected static $top_behaviors = [];

    /** @var    string  Database table prefix */
    public $prefix = '';

    /// @name Core instance methods
    //@{
    /**
     * Disabled children constructor and direct instance
     */
    final protected function __construct()
    {
    }

    /*
     * Disabled clone method
     */
    final public function __clone()
    {
        trigger_error('Core instance can not be cloned.', E_USER_ERROR);
        exit(1);
    }

    /**
     * Disable sleep method
     */
    final public function __sleep()
    {
        trigger_error('Core instance can not be serialized.', E_USER_ERROR);
        exit(1);
    }

    /**
     * Disable wakeup method
     */
    final public function __wakeup()
    {
        trigger_error('Core instance can not be deserialized.', E_USER_ERROR);
        exit(1);
    }

    /**
     * Get core unique instance
     *
     * @param   string|null     $blog_id    Blog ID on first public process call
     *
     * @return  Core|null                   Core (Process) instance
     */
    final public static function singleton(?string $blog_id = null): ?Core
    {
        if (!static::$instance && static::class != self::class) {
            Statistic::start();

            # Two stage instanciation (construct then process)
            static::$instance = new static();
            static::$instance->process($blog_id);
        }

        return static::$instance;
    }
    //@}

    /// @name Core others instances methods
    //@{
    /**
     * Get autoload instance
     *
     * Instanciate a core autoloader for custom
     * third party (plugins/themes) specifics needs
     *
     * @return  Autoload   Autoload instance
     */
    public function autoload(): Autoload
    {
        if (!($this->autoload instanceof Autoload)) {
            $this->autoload = new Autoload('', '', true);
        }

        return $this->autoload;
    }

    /**
     * Get behavior instance
     *
     * @return  Behavior    Behavior instance
     */
    public function behavior(): Behavior
    {
        if (!($this->behavior instanceof Behavior)) {
            $this->behavior = new Behavior();
        }

        return $this->behavior;
    }

    /**
     * Get blog instance
     *
     * @return  Blog|null   Blog instance
     */
    public function blog(): ?Blog
    {
        return $this->blog;
    }

    /**
     * Get blogs instance
     *
     * @return  Blogs   Blogs instance
     */
    public function blogs(): Blogs
    {
        if (!($this->blogs instanceof Blogs)) {
            $this->blogs = new Blogs();
        }

        return $this->blogs;
    }

    /**
     * Get database connection instance
     *
     * @return  Connection  Connection instance
     */
    public function con(): Connection
    {
        if (!($this->con instanceof Connection)) {
            try {
                $prefix        = $this->config()->database_prefix;
                $driver        = $this->config()->database_driver;
                $default_class = 'Dotclear\\Database\\Connection';

                # You can set DOTCLEAR_CON_CLASS to whatever you want.
                # Your new class *should* inherits Dotclear\Database\Connection class.
                $class = defined('DOTCLEAR_CON_CLASS') ? DOTCLEAR_CON_CLASS : $default_class ;

                if (!class_exists($class)) {
                    throw new \Exception(sprintf('Database connection class %s does not exist.', $class));
                }

                if ($class != $default_class && !is_subclass_of($class, $default_class)) {
                    throw new \Exception(sprintf('Database connection class %s does not inherit %s', $class, $default_class));
                }

                # PHP 7.0 mysql driver is obsolete, map to mysqli
                if ($driver === 'mysql') {
                    $driver = 'mysqli';
                }

                # Set full namespace of distributed database driver
                if (in_array($driver, ['mysqli', 'mysqlimb4', 'pgsql', 'sqlite'])) {
                    $class = 'Dotclear\\Database\\Driver\\' . ucfirst($driver) . '\\Connection';
                }

                # Check if database connection class exists
                if (!class_exists($class)) {
                    throw new \Exception(sprintf('Unable to load DB layer for %s', $driver));
                }

                # Create connection instance
                $con = new $class(
                    $this->config()->database_host,
                    $this->config()->database_name,
                    $this->config()->database_user,
                    $this->config()->database_password,
                    $this->config()->database_persist
                );

                # Define weak_locks for mysql
                if (in_array($driver, ['mysqli', 'mysqlimb4'])) {
                    $con::$weak_locks = true;
                }

                # Define searchpath for postgresql
                if ($driver == 'pgsql') {
                    $searchpath = explode('.', $prefix, 2);
                    if (count($searchpath) > 1) {
                        $prefix = $searchpath[1];
                        $sql    = 'SET search_path TO ' . $searchpath[0] . ',public;';
                        $con->execute($sql);
                    }
                }

                # Set table prefix in core
                $this->prefix = $prefix;

                $this->con =  $con;
            } catch (\Exception $e) {
                $this->getExceptionLang();
                $msg = sprintf(
                    __('<p>This either means that the username and password information in ' .
                    'your <strong>config.php</strong> file is incorrect or we can\'t contact ' .
                    'the database server at "<em>%s</em>". This could mean your ' .
                    'host\'s database server is down.</p> ' .
                    '<ul><li>Are you sure you have the correct username and password?</li>' .
                    '<li>Are you sure that you have typed the correct hostname?</li>' .
                    '<li>Are you sure that the database server is running?</li></ul>' .
                    '<p>If you\'re unsure what these terms mean you should probably contact ' .
                    'your host. If you still need help you can always visit the ' .
                    '<a href="https://forum.dotclear.net/">Dotclear Support Forums</a>.</p>'),
                    ($this->config()->database_host != '' ? $this->config()->database_host : 'localhost')
                );
                $this->throwException(
                    $msg,
                    $msg . '<p>' . __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>',
                    620
                );
            }
        }

        return $this->con;
    }

    /**
     * Get dotclear configuration instance
     *
     * @return  Configuration  Configuration instance
     */
    public function config(): Configuration
    {
        if (!($this->config instanceof Configuration)) {
            $config_file = defined('DOTCLEAR_CONFIG_PATH') && is_file(DOTCLEAR_CONFIG_PATH) ? DOTCLEAR_CONFIG_PATH : [];
            $this->config = new Configuration(self::getDefaultConfig(), $config_file);

            # Alias that could be required before first connection instance
            $this->prefix = $this->config->database_prefix;
        }

        return $this->config;
    }

    /**
     * Get error instance
     *
     * @return  Error   Error instance
     */
    public function error(): Error
    {
        if (!($this->error instanceof Error)) {
            $this->error = new Error();
        }

        return $this->error;
    }

    /**
     * Get formater instance
     *
     * @return  Formater   Formater instance
     */
    public function formater(): Formater
    {
        if (!($this->formater instanceof Formater)) {
            $this->formater = new Formater();
        }

        return $this->formater;
    }

    /**
     * Get log instance
     *
     * @return  Log   Log instance
     */
    public function log(): Log
    {
        if (!($this->log instanceof Log)) {
            $this->log = new Log();
        }

        return $this->log;
    }

    /**
     * Get media instance
     *
     * Caller MUST cope with Media instance failure.
     *
     * @param   bool    $reload     Force reload of Media instance
     * @param   bool    $throw      Throw Exception on instance failure
     *
     * @return  Media   Media instance
     */
    public function media(bool $reload = false, bool $throw = false): ?Media
    {
        if (!($this->media instanceof Media) || $reload) {
            try {
                $this->media = new Media();
            } catch (\Exception $e) {
                $this->media = null;
                if ($throw) {
                    throw $e;
                }
            }
        }

        return $this->media;
    }

    /**
     * Get meta instance
     *
     * @return  Meta   Meta instance
     */
    public function meta(): Meta
    {
        if (!($this->meta instanceof Meta)) {
            $this->meta = new Meta();
        }

        return $this->meta;
    }

    /**
     * Get nonce instance
     *
     * @return  Nonce   Nonce instance
     */
    public function nonce(): Nonce
    {
        if (!($this->nonce instanceof Nonce)) {
            $this->nonce = new Nonce();
        }

        return $this->nonce;
    }

    /**
     * Get posttype instance
     *
     * @return  PostType   PostType instance
     */
    public function posttype(): PostType
    {
        if (!($this->posttype instanceof PostType)) {
            $this->posttype = new PostType();
        }

        return $this->posttype;
    }

    /**
     * Get reser server instance
     *
     * @return  RestServer   RestServer instance
     */
    public function rest(): RestServer
    {
        if (!($this->rest instanceof RestServer)) {
            $this->rest = new RestServer();
        }

        return $this->rest;
    }

    /**
     * Get session instance
     *
     * @return  Session   Session instance
     */
    public function session(): Session
    {
        if (!($this->session instanceof Session)) {
            $this->session = new Session();
        }

        return $this->session;
    }

    /**
     * Get url (public) instance
     *
     * @return  Url   Url instance
     */
    public function url(): Url
    {
        if (!($this->url instanceof Url)) {
            $this->url = new Url();
        }

        return $this->url;
    }

    /**
     * Get user (auth) instance
     *
     * You can set DOTCLEAR_USER_CLASS to whatever you want.
     * Your new class *should* inherits Dotclear\Core\User\User class.
     *
     * @return  User  User instance
     */
    public function user(): User
    {
        if (!($this->user instanceof User)) {
            try {
                $dc_user_class = __NAMESPACE__ . '\\User\\User';
                $class = defined('DOTCLEAR_USER_CLASS') ? DOTCLEAR_USER_CLASS : $dc_user_class;

                # Check if auth class exists
                if (!class_exists($class)) {
                    throw new \Exception(sprintf('Authentication class %s does not exist.', $class));
                }

                # Check if auth class inherit Dotclear auth class
                if ($class != $dc_user_class && !is_subclass_of($class, $dc_user_class)) {
                    throw new \Exception(sprintf('Authentication class %s does not inherit %s.', $class, $dc_user_class));
                }

                $this->user = new $class();
            } catch (\Exception $e) {
                $this->getExceptionLang();
                $this->throwException(
                    __('Unable to do authentication'),
                    sprtinf(__('Something went wrong while trying to load authentication class: %s'), $e->getMessage()),
                    611
                );
            }
        }

        return $this->user;
    }

    /**
     * Get users instance
     *
     * @return  Users   Users instance
     */
    public function users(): Users
    {
        if (!($this->users instanceof Users)) {
            $this->users = new Users();
        }

        return $this->users;
    }

    /**
     * Get version instance
     *
     * @return  Version   Version instance
     */
    public function version(): Version
    {
        if (!($this->version instanceof Version)) {
            $this->version = new Version();
        }

        return $this->version;
    }

    /**
     * Get wkik (wki2xhtml) instance
     *
     * @return  Wiki   Wiki instance
     */
    public function wiki(): Wiki
    {
        if (!($this->wiki instanceof Wiki)) {
            $this->wiki = new Wiki();
        }

        return $this->wiki;
    }
    //@}

    /// @name Core methods
    //@{
    /**
     * Start Dotclear Core process
     */
    protected function process(): void
    {
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
            # Stop core process here in installalation process
            if ($this->process == 'Install') {

                return;
            }
            # Redirect to installation process
            Http::redirect(preg_replace(
                ['%admin/.*?$%', '%index.php.*?$%', '%/$%'],
                '',
                filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            ) . '/admin/install/index.php');

            exit;
        }

        # In non production environment, display all errors
        if (!$this->production()) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL | E_STRICT);
        }

        # Set some Http stuff
        Http::$https_scheme_on_443 = $this->config()->force_scheme_443;
        Http::$reverse_proxy = $this->config()->reverse_proxy;
        Http::trimRequest();

        # Check master key
        if (32 > strlen($this->config()->master_key)) {
                $this->getExceptionLang();
                $this->throwException(
                    __('Unsufficient master key'),
                    __('Master key is not strong enough, please change it.'),
                    611
                );
        }

        # Check cryptography algorithm
        if ($this->config()->crypt_algo != 'sha1') {
            # Check length of cryptographic algorithm result and exit if less than 40 characters long
            if (strlen(Crypt::hmac($this->config()->master_key, $this->config()->vendor_name, $this->config()->crypt_algo)) < 40) {
                $this->getExceptionLang();
                $this->throwException(
                    __('Cryptographic error'),
                    sprintf(__('%s cryptographic algorithm configured is not strong enough, please change it.'), $this->config()->crypt_algo),
                    611
                );
            }
        }

        # Check existence of digests directory
        if (!is_dir($this->config()->digests_dir)) {
            /* Try to create it */
            @Files::makeDir($this->config()->digests_dir);
        }

        # Check existence of cache directory
        if (!is_dir($this->config()->cache_dir)) {
            /* Try to create it */
            @Files::makeDir($this->config()->cache_dir);
            if (!is_dir($this->config()->cache_dir)) {
                $this->getExceptionLang();
                $this->throwException(
                    __('Unable to find cache directory'),
                    sprintf(__('%s directory does not exist. Please create it.'), $this->config()->cache_dir),
                    611
                );
            }
        }

        # Check existence of var directory
        if (!is_dir($this->config()->var_dir)) {
            // Try to create it
            @Files::makeDir($this->config()->var_dir);
            if (!is_dir($this->config()->var_dir)) {
                $this->getExceptionLang();
                $this->throwException(
                    __('Unable to find var directory'),
                    sprintf(
                    '%s directory does not exist. Please create it.', $this->config()->var_dir),
                    611
                );
            }
        }

        # Start l10n
        L10n::init();

        # Define current process for files check
        define('DOTCLEAR_PROCESS', $this->process);

        # Add top behaviors
        $this->registerTopBehaviors();

        # Register Core post types
        $this->posttype()->setPostType('post', '?handler=admin.post&id=%d', $this->url()->getURLFor('post', '%s'), 'Posts');

        # Register shutdown function
        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Check production environment
     *
     * @return  bool    True for production env
     */
    public function production(): bool
    {
        return !($this->config() && $this->config()->production === false);
    }

    /**
     * Shutdown method
     *
     * Close properly session and connection.
     */
    public function shutdown(): void
    {
        try {
            if (session_id()) {
                session_write_close();
            }
        } catch (\Exception $e) {
        }
        try {
            if ($this->con) {
                $this->con->close();
            }
        } catch (\Exception $e) {
        }
    }

    /// @name Core top behaviors methods
    //@{
    /**
     * Add Top Behavior statically before class instanciate
     *
     * ::addTopBehavior('MyBehavior', 'MyFunction');
     * also work from other child class.
     *
     * @param  string                   $behavior   The behavior
     * @param  string|array|Closure     $callback   The function
     */
    public static function addTopBehavior(string $behavior, string|array|Closure $callback): void
    {
        array_push(self::$top_behaviors, [$behavior, $callback]);
    }

    /**
     * Register Top Behaviors into class instance behaviors
     */
    protected function registerTopBehaviors(): void
    {
        foreach (self::$top_behaviors as $behavior) {
            $this->behavior()->add($behavior[0], $behavior[1]);
        }
    }
    //@}

    /// @name Core blog methods
    //@{
    /**
     * Sets the blog to use.
     *
     * @param   string  $blog_id    The blog ID
     */
    public function setBlog(string $blog_id): void
    {
        try {
            $this->blog = new Blog($blog_id);
        } catch (\Exception $e) {
            $this->getExceptionLang();
            $this->throwException(
                __('Unable to load blog'),
                sprintf(__('Something went wrong while trying to load blog: %s'), $e->getMessage()),
                620
            );
        }
    }

    /**
     * Unsets blog property
     */
    public function unsetBlog(): void
    {
        $this->blog = null;
    }
    //@}

    /// @name Core exception methods
    //@{
    /**
     * display default error message
     *
     * @throws  PrependException
     *
     * @param   string  $message    The short message
     * @param   string  $detail     The detailed message
     * @param   int     $code       The code
     */
    protected function throwException(string $message, string $detail, int $code): void
    {
        $title = self::getExceptionTitle($code);

        # If in non production env and there are some details
        if (!$this->production() && !empty($detail)) {
            $message = $detail;
        # If error code is higher than 630 and in plublic, then show a standard message
        } elseif (630 <= $code && !in_array(DOTCLEAR_PROCESS, ['Admin', 'Install'])) {
            $title = __('Site temporarily unavailable');
            $message = __('<p>We apologize for this temporary unavailability.<br />Thank you for your understanding.</p>');
        }

        # Use an Exception handler to get trace for non production env
        throw new PrependException($title, $message, $code, !$this->production());

        exit(1);
    }

    /**
     * Load locales for detected language
     */
    protected function getExceptionLang(): void
    {
        $dlang = Http::getAcceptLanguages();
        foreach ($dlang as $l) {
            if ($l == 'en' || $this->config() && L10n::set(implode_path($this->config()->l10n_dir, $l, 'main')) !== false) {
                L10n::lang($l);

                break;
            }
        }
    }

    /**
     * Get Exception title according to code
     *
     * @param   int     $code   The code
     * @return  string          The title
     */
    protected static function getExceptionTitle(int $code): string
    {
        $errors = [
            605 => __('no process found'),
            610 => __('no config file'),
            611 => __('bad configuration'),
            620 => __('database issue'),
            625 => __('user permission issue'),
            628 => __('file handler not found'),
            630 => __('blog is not defined'),
            640 => __('template files creation'),
            650 => __('no default theme'),
            660 => __('template processing error'),
            670 => __('blog is offline'),
        ];

        return $errors[$code] ?? __('Dotclear error');
    }
    //@}

    /**
     * Empty templates cache directory
     */
    public static function emptyTemplatesCache(): void //! move this
    {
        if (is_dir(implode_path(dotclear()->config()->cache_dir, 'cbtpl'))) {
            Files::deltree(implode_path(dotclear()->config()->cache_dir, 'cbtpl'));
        }
    }

    /**
     * Default Dotclear configuration
     *
     * This configuration must be completed by the config.php file.
     *
     * @see     Dotclear\Utils\Configuration
     *
     * @return  array   Initial configuation
     */
    private static function getDefaultConfig(): array
    {
        return [
            'admin_adblocker_check' => [null, false],
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
            'file_serve_type'       => [null, ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'css', 'js', 'swf', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'html', 'xml', 'json', 'txt', 'zip']],
            'force_scheme_443'      => [null, true],
            'iconset_dir'           => [null, ''], //[null, root_path('Iconset')],
            'iconset_official'      => [false, ['Legacy', 'ThomasDaveluy']],
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
            'plugin_official'       => [false, ['AboutConfig', 'Akismet', 'Antispam', 'Attachments', 'Blogroll', 'Dclegacy', 'FairTrackbacks', 'ImportExport', 'Maintenance', 'Pages', 'Pings', 'SimpleMenu', 'Tags', 'ThemeEditor', 'UserPref', 'Widgets', 'LegacyEditor', 'CKEditor', 'Breadcrumb']],
            'plugin_update_url'     => [null,  'https://update.dotaddict.org/dc2/plugins.xml'],
            'production'            => [null, true],
            'query_timeout'         => [null, 4],
            'reverse_proxy'         => [null, true],
            'root_dir'              => [false, root_path()], //Alias for DOTCLEAR_ROOT_DIR
            'session_name'          => [null, 'dcxd'],
            'session_ttl'           => [null, '-120 minutes'],
            'store_allow_repo'      => [null, true],
            'store_update_noauto'   => [null, false],
            'template_default'      => [null, 'mustek'],
            'theme_dir'             => [null, root_path('Theme')],
            'theme_official'        => [false, ['Berlin', 'BlueSilence', 'Blowup', 'CustomCSS', 'Ductile']],
            'theme_update_url'      => [null, 'https://update.dotaddict.org/dc2/themes.xml'],
            'var_dir'               => [null, root_path('..', 'var')],
            'vendor_name'           => [null, 'Dotclear'],
            'xmlrpc_url'            => [null, '%1$sxmlrpc/%2$s'],
        ];
    }
}
