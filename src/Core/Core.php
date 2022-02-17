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
    private $autoload = null;

    /** @var    Behavior    Behavior instance */
    private $behavior = null;

    /** @var    Blog    Blog instance */
    private $blog = null;

    /** @var    Blogs   Blogs instance */
    private $blogs = null;

    /** @var    Connection   Connection instance */
    private $con = null;

    /** @var    Configuration   Configuration instance */
    private $config = null;

    /** @var    Error   Error instance */
    private $error = null;

    /** @var    Formater   Formater instance */
    private $formater = null;

    /** @var    Log   Log instance */
    private $log = null;

    /** @var    Media   Media instance */
    private $media = null;

    /** @var    Meta   Meta instance */
    private $meta = null;

    /** @var    Nonce   Nonce instance */
    private $nonce = null;

    /** @var    PostType   PostType instance */
    private $posttype = null;

    /** @var    Rest   Rest instance */
    private $rest = null;

    /** @var    Session   Session instance */
    private $session = null;

    /** @var    Url     Url instance */
    private $url = null;

    /** @var    Auth    Auth instance */
    private $user = null;

    /** @var    Users   Users instance */
    private $users = null;

    /** @var    Verion  Version instance */
    private $version = null;

    /** @var    Wiki    Wiki instance */
    private $wiki = null;

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
                $this->config = new Configuration(self::getDefaultConfig());

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
        $this->config = new Configuration(self::getDefaultConfig(), DOTCLEAR_CONFIG_PATH);

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
                if (!in_array($this->process, ['Admin', 'Install'])) {
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
        $this->initConnection();

        # Add top behaviors
        $this->registerTopBehaviors();

        # Register Core post types
        $this->posttype()->setPostType('post', '?handler=admin.post&id=%d', $this->url()->getURLFor('post', '%s'), 'Posts');

        # Register shutdown function
        register_shutdown_function([$this, 'shutdown']);
    }

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
     * Get Connection instance
     *
     * @return  Connection|null  Connection instance
     */
    public function con(): ?Connection
    {
        return $this->con;
    }

    /**
     * Get Configuration instance or value
     *
     * @return  Configuration|null  Configuration instance or null
     */
    public function config(): ?Configuration
    {
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
     * @return  Media   Media instance
     */
    public function media(bool $reload = null): Media
    {
        if (!($this->media instanceof Media) || $reload) {
            $this->media = new Media();
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
     * @return  User|null  User instance or null
     */
    public function user(): ?User
    {
        if (!($this->user instanceof User)) {
            $dc_user_class = __NAMESPACE__ . '\\User\\User';
            $class = defined('DOTCLEAR_USER_CLASS') ? DOTCLEAR_USER_CLASS : $dc_user_class;

            # Check if auth class exists
            if (!class_exists($class)) {
                // Admin must create it
                if (!in_array($this->process, ['Admin', 'Install'])) {
                    throw new PrependException('Server error', 'Site temporarily unavailable');
                } else {
                    throw new PrependException('Dotclear error', sprintf(
                        'Authentication class %s does not exist.', $class
                    ));
                }
                exit;
            }

            # Check if auth class inherit Dotclear auth class
            if ($class != $dc_user_class && !is_subclass_of($class, $dc_user_class)) {
                // Admin must create it
                if (!in_array($this->process, ['Admin', 'Install'])) {
                    throw new PrependException('Server error', 'Site temporarily unavailable');
                } else {
                    throw new PrependException('Dotclear error', sprintf(
                        'Authentication class %s does not inherit %s.', $class, $dc_user_class
                    ));
                }
                exit;
            }

            $this->user = new $class();
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
        $this->blog = new Blog($blog_id);
    }

    /**
     * Unsets blog property
     */
    public function unsetBlog(): void
    {
        $this->blog = null;
    }
    //@}

    /// @name Core connection methods
    //@{
    /**
     * Instanciate database connection
     *
     * @throws  CoreException
     */
    private function initConnection(): void
    {
        try {
            $prefix        = dotclear()->config()->database_prefix;
            $driver        = dotclear()->config()->database_driver;
            $default_class = 'Dotclear\\Database\\Connection';

            # You can set DOTCLEAR_CON_CLASS to whatever you want.
            # Your new class *should* inherits Dotclear\Database\Connection class.
            $class = defined('DOTCLEAR_CON_CLASS') ? DOTCLEAR_CON_CLASS : $default_class ;

            if (!class_exists($class)) {
                throw new CoreException('Database connection class ' . $class . ' does not exist.');
            }

            if ($class != $default_class && !is_subclass_of($class, $default_class)) {
                throw new CoreException('Database connection class ' . $class . ' does not inherit ' . $default_class);
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
                trigger_error('Unable to load DB layer for ' . $driver, E_USER_ERROR);
                exit(1);
            }

            # Create connection instance
            $con = new $class(
                dotclear()->config()->database_host,
                dotclear()->config()->database_name,
                dotclear()->config()->database_user,
                dotclear()->config()->database_password,
                dotclear()->config()->database_persist
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
            # Loading locales for detected language
            $dlang = Http::getAcceptLanguages();
            foreach ($dlang as $l) {
                if ($l == 'en' || L10n::set(implode_path(dotclear()->config()->l10n_dir, $l, 'main')) !== false) {
                    L10n::lang($l);

                    break;
                }
            }
            if (in_array(DOTCLEAR_PROCESS, ['Admin', 'Install'])) {
                throw new PrependException(
                    __('Unable to connect to database'),
                    $e->getCode() == 0 ?
                    sprintf(
                        __('<p>This either means that the username and password information in ' .
                        'your <strong>config.php</strong> file is incorrect or we can\'t contact ' .
                        'the database server at "<em>%s</em>". This could mean your ' .
                        'host\'s database server is down.</p> ' .
                        '<ul><li>Are you sure you have the correct username and password?</li>' .
                        '<li>Are you sure that you have typed the correct hostname?</li>' .
                        '<li>Are you sure that the database server is running?</li></ul>' .
                        '<p>If you\'re unsure what these terms mean you should probably contact ' .
                        'your host. If you still need help you can always visit the ' .
                        '<a href="https://forum.dotclear.net/">Dotclear Support Forums</a>.</p>') .
                        (dotclear()->config()->run_level >= DOTCLEAR_RUN_DEBUG ? // @phpstan-ignore-line
                            '<p>' . __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                            ''),
                        (dotclear()->config()->database_host != '' ? dotclear()->config()->database_host : 'localhost')
                    ) :
                    '',
                    20
                );
            } else {
                throw new PrependException(
                    __('Site temporarily unavailable'),
                    __('<p>We apologize for this temporary unavailability.<br />' .
                        'Thank you for your understanding.</p>'),
                    20
                );
            }
        }
    }
    //@}

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
