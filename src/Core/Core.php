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
use Closure;
use Throwable;
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
use Dotclear\Database\AbstractConnection;
use Dotclear\Exception\PrependException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Autoload;
use Dotclear\Helper\Behavior;
use Dotclear\Helper\Configuration;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\Dt;
use Dotclear\Helper\ErrorTrait;
use Dotclear\Helper\L10n;
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
    use Errortrait;

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
     * @var PostType $posttype
     *               PostType instance
     */
    private $posttype;

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
     * @var null|static $instance
     *                  Core singleton instance
     */
    private static $instance;

    /**
     * @var string $process
     *             Current Process
     */
    protected $process;

    /**
     * @var string $lang
     *             Current lang
     */
    protected $lang                 = 'en';

    /**
     * @var array<int,array> $top_behaviors
     *                       top behaviors
     */
    protected static $top_behaviors = [];

    /**
     * @var string $prefix
     *             Database table prefix
     */
    public $prefix                  = '';

    // / @name Core instance methods
    // @{
    /**
     * Consructor.
     *
     * This method is mark as <b>final</b>
     * to cope with singleton instance.
     *
     * Set up some (no config) static features.
     */
    final protected function __construct()
    {
        // Statistic (dev)
        Statistic::start();

        // Encoding
        mb_internal_encoding('UTF-8');

        // Timezone
        Dt::setTZ('UTC');

        // Disallow every special wrapper
        Http::unregisterWrapper();

        // Add custom regs
        Html::$absolute_regs[] = '/(<param\s+name="movie"\s+value=")(.*?)(")/msu';
        Html::$absolute_regs[] = '/(<param\s+name="FlashVars"\s+value=".*?(?:mp3|flv)=)(.*?)(&|")/msu';
    }

    /**
     * Disable clone method.
     *
     * This method is mark as <b>final</b>
     * to cope with singleton instance.
     */
    final public function __clone()
    {
        trigger_error('Core instance can not be cloned.', E_USER_ERROR);
    }

    /**
     * Disable sleep method.
     *
     * This method is mark as <b>final</b>
     * to cope with singleton instance.
     */
    final public function __sleep()
    {
        trigger_error('Core instance can not be serialized.', E_USER_ERROR);
    }

    /**
     * Disable wakeup method.
     *
     * This method is mark as <b>final</b>
     * to cope with singleton instance.
     */
    final public function __wakeup()
    {
        trigger_error('Core instance can not be deserialized.', E_USER_ERROR);
    }

    /**
     * Get core unique instance.
     *
     * Use a two stage instanciation (construct then process).
     *
     * This method is mark as <b>final</b>
     * to cope with singleton instance.
     *
     * Singleton Core is accessible from function dotclear()
     *
     * @param null|string $blog_id Blog ID on first public process call
     *
     * @return null|static Core (Process) instance
     */
    final public static function singleton(?string $blog_id = null): ?static
    {
        if (null == self::$instance && self::class != static::class) {
            self::$instance = new static();
            self::$instance->process($blog_id);
        }

        return self::$instance;
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
     * Autoload methods are accesible from dotclear()->autoload()
     *
     * @return Autoload The autoload instance
     */
    public function autoload(): Autoload
    {
        if (!($this->autoload instanceof Autoload)) {
            $this->autoload = new Autoload('', '', true);
        }

        return $this->autoload;
    }

    /**
     * Get behavior instance.
     *
     * Behavior methods are accesible from dotclear()->behavior()
     *
     * @return Behavior The behaviors instance
     */
    public function behavior(): Behavior
    {
        if (!($this->behavior instanceof Behavior)) {
            $this->behavior = new Behavior();
        }

        return $this->behavior;
    }

    /**
     * Get blog instance.
     *
     * Blog methods are accesible from dotclear()->blog()
     *
     * @return null|Blog The blog instance
     */
    public function blog(): ?Blog
    {
        return $this->blog;
    }

    /**
     * Get blogs instance.
     *
     * Blogs methods are accesible from dotclear()->blogs()
     *
     * @return Blogs The blogs instance
     */
    public function blogs(): Blogs
    {
        if (!($this->blogs instanceof Blogs)) {
            $this->blogs = new Blogs();
        }

        return $this->blogs;
    }

    /**
     * Get database connection instance.
     *
     * Database connection methods are accesible from dotclear()->con()
     *
     * @return AbstractConnection The connection instance
     */
    public function con(): AbstractConnection
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
                    'your <strong>config.php</strong> file is incorrect or we can\'t contact ' .
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
                $this->throwException(
                    $msg,
                    $msg . '<p>' . __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>',
                    620,
                    $e
                );
            }
        }

        return $this->con;
    }

    /**
     * Get dotclear configuration instance.
     *
     * Configuration methods are accesible from dotclear()->config()
     *
     * @return Configuration The configuration instance
     */
    public function config(): Configuration
    {
        if (!($this->config instanceof Configuration)) {
            $config_file  = defined('DOTCLEAR_CONFIG_PATH') && is_file(\DOTCLEAR_CONFIG_PATH) ? \DOTCLEAR_CONFIG_PATH : [];
            $this->config = new Configuration($this->getDefaultConfig(), $config_file);

            // Alias that could be required before first connection instance
            $this->prefix = $this->config->get('database_prefix');
        }

        return $this->config;
    }

    /**
     * Get formater instance.
     *
     * Formater methods are accesible from dotclear()->formater()
     *
     * @return Formater The formater instance
     */
    public function formater(): Formater
    {
        if (!($this->formater instanceof Formater)) {
            $this->formater = new Formater();
        }

        return $this->formater;
    }

    /**
     * Get log instance.
     *
     * Log methods are accesible from dotclear()->log()
     *
     * @return Log The log instance
     */
    public function log(): Log
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
     * Media methods are accesible from dotclear()->media()
     *
     * @param bool $reload Force reload of Media instance
     * @param bool $throw  Throw Exception on instance failure
     *
     * @return Media The media instance
     */
    public function media(bool $reload = false, bool $throw = false): ?Media
    {
        if ($reload || !($this->media instanceof Media)) {
            try {
                $this->media = new Media();
            } catch (Exception $e) {
                $this->media = null;
                if ($throw) {
                    throw $e;
                }
            }
        }

        return $this->media;
    }

    /**
     * Get meta instance.
     *
     * Meta methods are accesible from dotclear()->meta()
     *
     * @return Meta The meta instance
     */
    public function meta(): Meta
    {
        if (!($this->meta instanceof Meta)) {
            $this->meta = new Meta();
        }

        return $this->meta;
    }

    /**
     * Get nonce instance.
     *
     * Nonce methods are accesible from dotclear()->nonce()
     *
     * @return Nonce The nonce instance
     */
    public function nonce(): Nonce
    {
        if (!($this->nonce instanceof Nonce)) {
            $this->nonce = new Nonce();
        }

        return $this->nonce;
    }

    /**
     * Get posttype instance.
     *
     * PostType methods are accesible from dotclear()->posttype()
     *
     * @return PostType The post type instance
     */
    public function posttype(): PostType
    {
        if (!($this->posttype instanceof PostType)) {
            $this->posttype = new PostType();
        }

        return $this->posttype;
    }

    /**
     * Get reser server instance.
     *
     * RestServer methods are accesible from dotclear()->rest()
     *
     * @return RestServer The REST server instance
     */
    public function rest(): RestServer
    {
        if (!($this->rest instanceof RestServer)) {
            $this->rest = new RestServer();
        }

        return $this->rest;
    }

    /**
     * Get session instance.
     *
     * Session methods are accesible from dotclear()->session()
     *
     * @return Session The session instance
     */
    public function session(): Session
    {
        if (!($this->session instanceof Session)) {
            $this->session = new Session();
        }

        return $this->session;
    }

    /**
     * Get url (public) instance.
     *
     * Public URL methods are accesible from dotclear()->url()
     *
     * @return Url The public URL instance
     */
    public function url(): Url
    {
        if (!($this->url instanceof Url)) {
            $this->url = new Url();
        }

        return $this->url;
    }

    /**
     * Get user (auth) instance.
     *
     * You can set constant DOTCLEAR_USER_CLASS to whatever you want.
     * Your new class *should* inherits Core User class.
     *
     * User methods are accesible from dotclear()->user()
     *
     * @return User The user instance
     */
    public function user(): User
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
                $this->throwException(
                    __('Unable to do authentication'),
                    sprintf(__('Something went wrong while trying to load authentication class: %s'), $e->getMessage()),
                    611
                );
            }
        }

        return $this->user;
    }

    /**
     * Get users instance.
     *
     * Users methods are accesible from dotclear()->users()
     *
     * @return Users The users instance
     */
    public function users(): Users
    {
        if (!($this->users instanceof Users)) {
            $this->users = new Users();
        }

        return $this->users;
    }

    /**
     * Get version instance.
     *
     * Version methods are accesible from dotclear()->version()
     *
     * @return Version The version instance
     */
    public function version(): Version
    {
        if (!($this->version instanceof Version)) {
            $this->version = new Version();
        }

        return $this->version;
    }

    /**
     * Get wiki (wiki2xhtml) instance.
     *
     * Wiki synthax methods are accesible from dotclear()->wiki()
     *
     * @return Wiki The wiki synthax instance
     */
    public function wiki(): Wiki
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
    protected function process(string $_ = null): void
    {
        // Find configuration file
        if (!defined('DOTCLEAR_CONFIG_PATH')) {
            if (isset($_SERVER['DOTCLEAR_CONFIG_PATH'])) {
                define('DOTCLEAR_CONFIG_PATH', $_SERVER['DOTCLEAR_CONFIG_PATH']);
            } elseif (isset($_SERVER['REDIRECT_\DOTCLEAR_CONFIG_PATH'])) {
                define('DOTCLEAR_CONFIG_PATH', $_SERVER['REDIRECT_\DOTCLEAR_CONFIG_PATH']);
            } else {
                define('DOTCLEAR_CONFIG_PATH', Path::implodeRoot('config.php'));
            }
        }

        // No configuration ? start installalation process
        if (!is_file(\DOTCLEAR_CONFIG_PATH)) {
            // Stop core process here in installalation process
            if ('Install' == $this->process) {
                return;
            }
            // Redirect to installation process
            Http::redirect(preg_replace(
                ['%admin/.*?$%', '%index.php.*?$%', '%/$%'],
                '',
                filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            ) . '/admin/install/index.php');

            exit;
        }

        // In non production environment, display all errors
        if (!$this->production()) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL | E_STRICT);
        }

        // Start l10n
        L10n::init();

        // Find a default appropriate language (used by Exceptions)
        foreach (Http::getAcceptLanguages() as $lang) {
            if ('en' == $lang || false !== L10n::set(Path::implode($this->config()->get('l10n_dir'), $lang, 'main'))) {
                $this->lang($lang);

                break;
            }
        }

        // Set some Http stuff
        Http::$https_scheme_on_443 = $this->config()->get('force_scheme_443');
        Http::$reverse_proxy       = $this->config()->get('reverse_proxy');
        Http::trimRequest();

        // Check master key
        if (32 > strlen($this->config()->get('master_key'))) {
            $this->throwException(
                __('Unsufficient master key'),
                __('Master key is not strong enough, please change it.'),
                611
            );
        }

        // Check cryptography algorithm
        if ('sha1' == $this->config()->get('crypt_algo')) {
            // Check length of cryptographic algorithm result and exit if less than 40 characters long
            if (40 > strlen(Crypt::hmac($this->config()->get('master_key'), $this->config()->get('vendor_name'), $this->config()->get('crypt_algo')))) {
                $this->throwException(
                    __('Cryptographic error'),
                    sprintf(__('%s cryptographic algorithm configured is not strong enough, please change it.'), $this->config()->get('crypt_algo')),
                    611
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
                $this->throwException(
                    __('Unable to find cache directory'),
                    sprintf(__('%s directory does not exist. Please create it.'), $this->config()->get('cache_dir')),
                    611
                );
            }
        }

        // Check existence of var directory
        if (!is_dir($this->config()->get('var_dir'))) {
            // Try to create it
            @Files::makeDir($this->config()->get('var_dir'));
            if (!is_dir($this->config()->get('var_dir'))) {
                $this->throwException(
                    __('Unable to find var directory'),
                    sprintf('%s directory does not exist. Please create it.', $this->config()->get('var_dir')),
                    611
                );
            }
        }

        // Check configuration required values
        if ($this->config()->error()->flag()) {
            $this->throwException(
                __('Configuration file is not complete.'),
                implode("\n", $this->config()->error()->dump()),
                611
            );
        }

        // Add top behaviors
        $this->registerTopBehaviors();

        // Register Core post types
        $this->posttype()->setPostType('post', '?handler=admin.post&id=%d', $this->url()->getURLFor('post', '%s'), 'Posts');

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
    public function processed(?string $process = null): string|bool
    {
        return null === $process ? $this->process : strtolower($this->process) == strtolower($process);
    }

    /**
     * Get current lang.
     *
     * @param string $lang Lang to switch on
     *
     * @return string The lang
     */
    public function lang(string $lang = null): string
    {
        if (null !== $lang) {
            $this->lang = L10n::lang($lang);
        }

        return $this->lang;
    }

    /**
     * Check production environment.
     *
     * @return bool True for production env
     */
    public function production(): bool
    {
        return false !== $this->config()->get('production');
    }

    /**
     * Check rescue mode.
     *
     * @return bool True for rescue mode
     */
    public function rescue()
    {
        return isset($_SESSION['sess_safe_mode']) && $_SESSION['sess_safe_mode'];
    }

    /**
     * Shutdown method.
     *
     * Close properly session and connection.
     */
    public function shutdown(): void
    {
        if (session_id()) {
            session_write_close();
        }

        try {
            $this->con->close();
        } catch (Exception|Error) {
        }
    }

    // / @name Core top behaviors methods
    // @{
    /**
     * Add Top Behavior statically before class instanciate.
     *
     * Core::addTopBehavior('MyBehavior', 'MyFunction');
     * also work from other child class.
     *
     * @param string               $behavior The behavior
     * @param array|Closure|string $callback The function
     */
    public static function addTopBehavior(string $behavior, string|array|Closure $callback): void
    {
        array_push(self::$top_behaviors, [$behavior, $callback]);
    }

    /**
     * Register Top Behaviors into class instance behaviors.
     */
    protected function registerTopBehaviors(): void
    {
        foreach (self::$top_behaviors as $behavior) {
            $this->behavior()->add($behavior[0], $behavior[1]);
        }
    }
    // @}

    // / @name Core blog methods
    // @{
    /**
     * Sets the blog to use.
     *
     * @param string $blog_id The blog ID
     */
    public function setBlog(string $blog_id): void
    {
        try {
            $this->blog = new Blog($blog_id);
        } catch (Exception $e) {
            $this->throwException(
                __('Unable to load blog'),
                sprintf(__('Something went wrong while trying to load blog: %s'), $e->getMessage()),
                620
            );
        }
    }

    /**
     * Unsets blog property.
     */
    public function unsetBlog(): void
    {
        $this->blog = null;
    }
    // @}

    // / @name Core exception methods
    // @{
    /**
     * Display default error message.
     *
     * @param string    $message  The short message
     * @param string    $detail   The detailed message
     * @param int       $code     The code
     * @param Throwable $previous The preious Exception
     *
     * @throws PrependException
     */
    protected function throwException(string $message, string $detail, int $code, Throwable $previous = null): void
    {
        $title = $this->getExceptionTitle($code);

        // If in non production env and there are some details
        if (!$this->production() && !empty($detail)) {
            $message = $detail;
        // If error code is higher than 630 and in plublic, then show a standard message
        } elseif (630 <= $code && !in_array(dotclear()->processed(), ['Admin', 'Install'])) {
            $title   = __('Site temporarily unavailable');
            $message = __('<p>We apologize for this temporary unavailability.<br />Thank you for your understanding.</p>');
        }

        // Use an Exception handler to get trace for non production env
        throw new PrependException($title, $message, $code, !$this->production(), $previous);
    }

    /**
     * Get Exception title according to code.
     *
     * @param int $code The code
     *
     * @return string The title
     */
    protected function getExceptionTitle(int $code): string
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
    // @}

    /**
     * Empty templates cache directory.
     */
    public function emptyTemplatesCache(): void // ! move this
    {
        if (is_dir(Path::implode(dotclear()->config()->get('cache_dir'), 'cbtpl'))) {
            Files::deltree(Path::implode(dotclear()->config()->get('cache_dir'), 'cbtpl'));
        }
    }

    /**
     * Default Dotclear configuration.
     *
     * This configuration must be completed by the config.php file.
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
            'backup_dir'            => [null, Path::implodeRoot()],
            'base_dir'              => [null, Path::implodeRoot('..')],
            'cache_dir'             => [null, Path::implodeRoot('..', 'cache')],
            'core_update_channel'   => [null, 'stable'],
            'core_update_noauto'    => [null, false],
            'core_update_url'       => [null, 'https://download.dotclear.org/versions.xml'],
            'core_version'          => [false, trim(file_get_contents(Path::implodeRoot('version')))],
            'core_version_break'    => [false, '3.0'],
            'crypt_algo'            => [null, 'sha1'],
            'database_driver'       => [true, ''],
            'database_host'         => [true, ''],
            'database_name'         => [true, ''],
            'database_password'     => [true, ''],
            'database_persist'      => [null, true],
            'database_prefix'       => [null, 'dc_'],
            'database_user'         => [true, ''],
            'digests_dir'           => [null, Path::implodeRoot('..', 'digests')],
            'file_serve_type'       => [null, ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'css', 'js', 'swf', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'html', 'xml', 'json', 'txt', 'zip']],
            'force_scheme_443'      => [null, true],
            'iconset_dirs'          => [null, []], // [null, [Path::implodeRoot('Iconset')],
            'iconset_official'      => [false, ['Legacy', 'ThomasDaveluy']],
            'iconset_update_url'    => [null, ''],
            'jquery_default'        => [null, '3.6.0'],
            'l10n_dir'              => [null, Path::implodeRoot('locales')],
            'l10n_update_url'       => [null, 'https://services.dotclear.net/dc2.l10n/?version=%s'],
            'media_dir_showhidden'  => [null, false],
            'media_upload_maxsize'  => [false, Files::getMaxUploadFilesize()],
            'master_key'            => [true, ''],
            'module_allow_multi'    => [null, false],
            'php_next_required'     => [false, '7.4'],
            'plugin_dirs'           => [null, [Path::implodeRoot('Plugin')]],
            'plugin_official'       => [false, ['AboutConfig', 'Akismet', 'Antispam', 'Attachments', 'Blogroll', 'Dclegacy', 'FairTrackbacks', 'ImportExport', 'Maintenance', 'Pages', 'Pings', 'SimpleMenu', 'Tags', 'ThemeEditor', 'UserPref', 'Widgets', 'LegacyEditor', 'CKEditor', 'Breadcrumb']],
            'plugin_update_url'     => [null,  'https://update.dotaddict.org/dc2/plugins.xml'],
            'production'            => [null, true],
            'query_timeout'         => [null, 4],
            'reverse_proxy'         => [null, true],
            'root_dir'              => [false, Path::implodeRoot()], // Alias for \DOTCLEAR_ROOT_DIR
            'session_name'          => [null, 'dcxd'],
            'session_ttl'           => [null, '-120 minutes'],
            'store_allow_repo'      => [null, true],
            'store_update_noauto'   => [null, false],
            'template_default'      => [null, 'mustek'],
            'theme_dirs'            => [null, [Path::implodeRoot('Theme')]],
            'theme_official'        => [false, ['Berlin', 'BlueSilence', 'Blowup', 'CustomCSS', 'Ductile']],
            'theme_update_url'      => [null, 'https://update.dotaddict.org/dc2/themes.xml'],
            'var_dir'               => [null, Path::implodeRoot('..', 'var')],
            'vendor_name'           => [null, 'Dotclear'],
            'xmlrpc_url'            => [null, '%1$sxmlrpc/%2$s'],
        ];
    }
}
