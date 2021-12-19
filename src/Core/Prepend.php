<?php
/**
 * @brief Dotclear core prepend class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Process;
use Dotclear\Exception as Exception;

use Dotclear\Utils\Dt;
use Dotclear\Utils\Http;
use Dotclear\Utils\Html;
use Dotclear\Utils\Crypt;
use Dotclear\Utils\Files;
use Dotclear\Utils\L10n;

use Dotclear\Database\Schema;

class Prepend extends Core
{
    /** @var string Process */
    protected $process;

    /**
     * Start Dotclear process
     *
     * @param  string $process public/admin/install/...
     */
    public function __construct()
    {
        /* add rcustom regs */
        Html::$absolute_regs[] = '/(<param\s+name="movie"\s+value=")(.*?)(")/msu';
        Html::$absolute_regs[] = '/(<param\s+name="FlashVars"\s+value=".*?(?:mp3|flv)=)(.*?)(&|")/msu';

        /* Encoding */
        mb_internal_encoding('UTF-8');

        /* Timezone */
        Dt::setTZ('UTC');

        /* CLI_MODE, boolean constant that tell if we are in CLI mode */
        if (!defined('CLI_MODE')) {
            define('CLI_MODE', PHP_SAPI == 'cli');
        }

        /* Disallow every special wrapper */
        Http::unregisterWrapper();

        /* Find configuration file */
        if (!defined('DOTCLEAR_CONFIG_PATH')) {
            if (isset($_SERVER['DOTCLEAR_CONFIG_PATH'])) {
                define('DOTCLEAR_CONFIG_PATH', $_SERVER['DOTCLEAR_CONFIG_PATH']);
            } elseif (isset($_SERVER['REDIRECT_DOTCLEAR_CONFIG_PATH'])) {
                define('DOTCLEAR_CONFIG_PATH', $_SERVER['REDIRECT_DOTCLEAR_CONFIG_PATH']);
            } else {
                define('DOTCLEAR_CONFIG_PATH', static::root('config.php'));
            }
        }

        /* No configuration ? start installalation process */
        if (!is_file(DOTCLEAR_CONFIG_PATH)) {
            /* Set Dotclear configuration constants to default values */
            require_once static::root('core_define.php');

            new Process('install');

            exit;
        }
        /* Should not happened but if we're lost, move to the right way */
        if (!empty($_GET['installwizard']) && $this->process != 'Install') {
            new Process('install');

            exit;
        }

        /* Set plateform (user) configuration constants */
        require_once DOTCLEAR_CONFIG_PATH;

        /* Set Dotclear configuration constants */
        require_once static::root('core_define.php');

        /* Set  some Http stuff */
        Http::$https_scheme_on_443 = DOTCLEAR_FORCE_SCHEME_443;
        Http::$reverse_proxy = DOTCLEAR_REVERSE_PROXY;

        /* Check cryptography algorithm */
        if ('DOTCLEAR_CRYPT_ALGO' != 'sha1') {
            /* Check length of cryptographic algorithm result and exit if less than 40 characters long */
            if (strlen(Crypt::hmac(DOTCLEAR_MASTER_KEY, DOTCLEAR_VENDOR_NAME, DOTCLEAR_CRYPT_ALGO)) < 40) {
                if ($this->process != 'Admin') {
                    static::error('Server error', 'Site temporarily unavailable');
                } else {
                    static::error('Dotclear error', DOTCLEAR_CRYPT_ALGO . ' cryptographic algorithm configured is not strong enough, please change it.');
                }
                exit;
            }
        }

        /* Check existence of cache directory */
        if (!is_dir(DOTCLEAR_CACHE_DIR)) {
            /* Try to create it */
            @Files::makeDir(DOTCLEAR_CACHE_DIR);
            if (!is_dir(DOTCLEAR_CACHE_DIR)) {
                /* Admin must create it */
                if (!in_array($this->process, ['Admin', 'Install'])) {
                    static::error('Server error', 'Site temporarily unavailable');
                } else {
                    static::error('Dotclear error', DOTCLEAR_CACHE_DIR . ' directory does not exist. Please create it.');
                }
                exit;
            }
        }

        /* Check existence of var directory */
        if (!is_dir(DOTCLEAR_VAR_DIR)) {
            // Try to create it
            @Files::makeDir(DOTCLEAR_VAR_DIR);
            if (!is_dir(DOTCLEAR_VAR_DIR)) {
                // Admin must create it
                if (!in_array($this->process, ['Admin', 'Install'])) {
                    static::error('Server error', 'Site temporarily unavailable');
                } else {
                    static::error('Dotclear error', DOTCLEAR_VAR_DIR . ' directory does not exist. Please create it.');
                }
                exit;
            }
        }

        /* Start l10n */
        L10n::init();

        /* Define current process for files check */
        define('DOTCLEAR_PROCESS', $this->process);

        try {
            parent::__construct();
            Utils::$core = $this;
        } catch (Exception $e) {
            static::errorL10n();
            if (!in_array($this->process, ['Admin', 'Install'])) {
                static::error(
                    __('Site temporarily unavailable'),
                    __('<p>We apologize for this temporary unavailability.<br />' .
                        'Thank you for your understanding.</p>'),
                    20
                );
            } else {
                static::error(
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
                        (DOTCLEAR_DEBUG ? // @phpstan-ignore-line
                            '<p>' . __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                            ''),
                        (DOTCLEAR_DATABASE_HOST != '' ? DOTCLEAR_DATABASE_HOST : 'localhost')
                    ) :
                    '',
                    20
                );
            }
        }

        /* Register Core behaviors */
//

        /* Clean up Http globals */
        Http::trimRequest();

        try {
            Http::unsetGlobals();
        } catch (Exception $e) {
            header('Content-Type: text/plain');
            echo $e->getMessage();
            exit;
        }

        /* Register Core Urls */
//

        /* Register Core post types */
//

        /* Store upload_max_filesize in bytes */
        $u_max_size = Files::str2bytes(ini_get('upload_max_filesize'));
        $p_max_size = Files::str2bytes(ini_get('post_max_size'));
        if ($p_max_size < $u_max_size) {
            $u_max_size = $p_max_size;
        }
        define('DOTCLEAR_MAX_UPLOAD_SIZE', $u_max_size);
        unset($u_max_size, $p_max_size);

        /* Register supplemental mime types */
        Files::registerMimeTypes([
            // Audio
            'aac'  => 'audio/aac',
            'ogg'  => 'audio/ogg',
            'weba' => 'audio/webm',
            'm4a'  => 'audio/mp4',
            // Video
            'mp4'  => 'video/mp4',
            'm4p'  => 'video/mp4',
            'webm' => 'video/webm',
        ]);

        /* Register shutdown function */
        register_shutdown_function([$this, 'shutdown']);
    }

    public function shutdown()
    {
        global $__shutdown;
        if (is_array($__shutdown)) {
            foreach ($__shutdown as $f) {
                if (is_callable($f)) {
                    call_user_func($f);
                }
            }
        }
        /* Explicitly close session before DB connection */
        try {
            if (session_id()) {
                session_write_close();
            }
        } catch (Exception $e) {    // @phpstan-ignore-line
        }
        $this->con->close();
    }

    protected static function error(string $summary, string $message, int $code = 0): void
    {
        # Error codes
        # 10 : no config file
        # 20 : database issue
        # 30 : blog is not defined
        # 40 : template files creation
        # 50 : no default theme
        # 60 : template processing error
        # 70 : blog is offline

        if (CLI_MODE) {
            trigger_error($summary, E_USER_ERROR);
            exit(1);
        }
        if (defined('DOTCLEAR_ERROR_FILE') && is_file(DOTCLEAR_ERROR_FILE)) {
            include DOTCLEAR_ERROR_FILE;
        } else {
            include static::root('core_error.php');
        }
        exit;
    }

    protected static function errorL10n()
    {
        # Loading locales for detected language
        $dlang = Http::getAcceptLanguages();
        foreach ($dlang as $l) {
            if ($l == 'en' || l10n::set(static::root(DOTCLEAR_L10N_DIR, $l, 'main')) !== false) {
                L10n::lang($l);

                break;
            }
        }
    }

    public static function root(string ...$args): string
    {
        if (!defined('DOTCLEAR_ROOT_DIR')) {
            define('DOTCLEAR_ROOT_DIR', dirname(__FILE__));
        }

        return implode(DIRECTORY_SEPARATOR, array_merge([DOTCLEAR_ROOT_DIR], $args));
    }

    private function authInstance()
    {
        # You can set DC_AUTH_CLASS to whatever you want.
        # Your new class *should* inherits Dotclear\Core\Auth class.
        if (!defined('DOTCLEAR_AUTH_CLASS')) {
            $class = __NAMESPACE__ . '\\Auth';
        } else {
            $class = DOTCLEAR_AUTH_CLASS;
        }

        if (!class_exists($class)) {
            throw new Exception('Authentication class ' . $class . ' does not exist.');
        }

        if ($class != __NAMESPACE__ . '\\Auth' && !is_subclass_of($class, __NAMESPACE__ . '\\Auth')) {
            throw new Exception('Authentication class ' . $class . ' does not inherit ' . __NAMESPACE__ . '\\Auth.');
        }

        return new $class($this);
    }
}
