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
use Dotclear\Core\Configuration\Configuration;
use Dotclear\Core\Formater\Formater;
use Dotclear\Core\Log\Log;
use Dotclear\Core\Media\Media;
use Dotclear\Core\Meta\Meta;
use Dotclear\Core\Nonce\Nonce;
use Dotclear\Core\Session\Session;
use Dotclear\Core\Permission\Permission;
use Dotclear\Core\PostType\PostType;
use Dotclear\Core\Url\Url;
use Dotclear\Core\User\User;
use Dotclear\Core\Users\Users;
use Dotclear\Core\Version\Version;
use Dotclear\Core\Wiki\Wiki;
use Dotclear\Database\AbstractConnection;
use Dotclear\Exception\InvalidConfiguration;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Mapper\Callables;
use Dotclear\Helper\Clock;
use Dotclear\Helper\ErrorTrait;
use Dotclear\Helper\L10n;
use Dotclear\Helper\MagicTrait;
use Dotclear\Helper\RestServer;
use Dotclear\Helper\Statistic;
use Error;
use Exception;

/**
 * Core for process.
 *
 * Core process starts in two steps,
 * first construct process instance,
 * then start process.
 *
 * @ingroup Process Core
 */
abstract class Core
{
    use ErrorTrait;
    use MagicTrait;

    /**
     * @var array<string,Callables> $behavior
     *                              Behaviors instances
     */
    private $behavior = [];

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
     * @var null|AbstractConnection $con
     *                              AbstractConnection instance
     */
    private $con;

    /**
     * @var null|Configuration $config
     *                         Configuration instance
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
     * @var Permission $permission
     *                 Permission instance
     */
    private $permission;

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

    // / @name Core common instances methods
    // @{
    /**
     * Get a behaviors group instance.
     *
     * Behavior methods are accesible from App::core()->behavior('a_group')
     *
     * @return Callables The behaviors group instance
     */
    final public function behavior(string $group): Callables
    {
        if (!isset($this->behavior[$group])) {
            $this->behavior[$group] = new Callables();
        }

        return $this->behavior[$group];
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
                    false === $this->isProductionMode() ?
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
            $this->config = new Configuration($this->getConfigurationPath());

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
     * Permission methods are accesible from App::core()->permission()
     *
     * @return Permission The permission types instance
     */
    final public function permission(): Permission
    {
        if (!($this->permission instanceof Permission)) {
            $this->permission = new Permission();
        }

        return $this->permission;
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
            $this->session = new Session();
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
                $parent = __NAMESPACE__ . '\\User\\User';
                $class  = defined('DOTCLEAR_USER_CLASS') ? \DOTCLEAR_USER_CLASS : $parent;

                // Check if auth class exists
                if (!class_exists($class)) {
                    throw new Exception(sprintf('Authentication class %s does not exist.', $class));
                }

                // Check if auth class inherit Dotclear auth class
                if ($class != $parent && !is_subclass_of($class, $parent)) {
                    throw new Exception(sprintf('Authentication class %s does not inherit %s.', $class, $parent));
                }

                $this->user = new $class();
            } catch (Exception $e) {
                throw new InvalidConfiguration(
                    false === $this->isProductionMode() ?
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
     * Consructor.
     *
     * Set up some (no config) static features.
     *
     * @param string $process The process name
     */
    final public function __construct(public readonly string $process)
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

        // Start l10n
        L10n::init();

        // Register shutdown function
        register_shutdown_function(function () {
            if (session_id()) {
                session_write_close();
            }

            try {
                $this->con?->close();
            } catch (Exception|Error) {
            }
        });
    }

    /**
     * Start Dotclear process.
     *
     * @param null|string $blog The blog id
     */
    abstract public function startProcess(string $blog = null): void;

    /**
     * Get configuration path.
     *
     * Check if constant exists else compose standard path to config file.
     *
     * @return string The configuration file path
     */
    final protected function getConfigurationPath(): string
    {
        if (defined('DOTCLEAR_CONFIG_PATH')) {
            $path = DOTCLEAR_CONFIG_PATH;
        } elseif (isset($_SERVER['DOTCLEAR_CONFIG_PATH'])) {
            $path = $_SERVER['DOTCLEAR_CONFIG_PATH'];
        } elseif (isset($_SERVER['REDIRECT_DOTCLEAR_CONFIG_PATH'])) {
            $path = $_SERVER['REDIRECT_DOTCLEAR_CONFIG_PATH'];
        } else {
            $path = Path::implodeBase('dotclear.conf.php');
        }

        return $path;
    }

    /**
     * Check current process.
     *
     * @param string $process Process name to check
     *
     * @return bool True this is the process
     */
    final public function isProcess(string $process): bool
    {
        return strtolower($this->process) == strtolower($process);
    }

    /**
     * Get database table prefix.
     *
     * @return string The database table prefix
     */
    final public function getPrefix(): string
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
    final public function isProductionMode(): bool
    {
        return false !== $this->config?->get('production');
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
    final public function isRescueMode()
    {
        return isset($_SESSION['sess_safe_mode']) && $_SESSION['sess_safe_mode'];
    }

    /**
     * Return default datetime display timezone.
     *
     * Child Process should implement this method
     * according to its specific default datetime display timezone.
     *
     * @return string The default datetime display timezone
     */
    public function getTimezone(): string
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
     * Set top behavior.
     *
     * Take added top behaviors and set it into core behavior instance.
     * Should be called be core child class.
     */
    final protected function setTopBehaviors(): void
    {
        foreach (self::$top_behaviors as $behavior) {
            $this->behavior($behavior[0])->add($behavior[1]);
        }
    }

    /**
     * Get all behaviors groups.
     *
     * @return array<string,Callables> The behaviors
     */
    final public function getBehaviors(): array
    {
        return $this->behavior;
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
                false === $this->isProductionMode() ?
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
}
