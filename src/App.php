<?php
/**
 * @class Dotclear
 * @brief Dotclear process launcher
 *
 * Call Dotclear::Admin(); to load admin pages
 *
 * For a public blog, use Dotclear::Public('myblogid');
 *
 * Process is not case sensitive here, whereas blog id is.
 *
 * @package Dotclear
 * @subpackage Process
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear;

# This is more a mode level rather than an error level !
# Define one of this level in DOTCLEAR_RUN_LEVEL
define('DOTCLEAR_RUN_PRODUCTION', 0);
define('DOTCLEAR_RUN_DEVELOPMENT', 256);
define('DOTCLEAR_RUN_DEPRECATED', 512);
define('DOTCLEAR_RUN_DEBUG', 1024);
define('DOTCLEAR_RUN_VERBOSE', 2048);

Class App
{
    /** @var    Autoloader  Dotclear custom autoloader */
    public static $autoloader;

    /// @name Core instance methods
    //@{
    /**
     * Call statically Dotclear process
     *
     * @param  string $process The process (admin,install,public...)
     * @param  string  $blog_id    The blog id for public process
     */
    public function __construct(string $process, ?string $blog_id = null)
    {

        # Timer and memory usage for stats and dev
        if (!defined('DOTCLEAR_START_TIME')) {
            define('DOTCLEAR_START_TIME', microtime(true));
        }
        if (!defined('DOTCLEAR_START_MEMORY')) {
            define('DOTCLEAR_START_MEMORY', memory_get_usage(false));
        }

        # Define Dotclear root directory
        if (!defined('DOTCLEAR_ROOT_DIR')) {
            define('DOTCLEAR_ROOT_DIR', __DIR__);
        }

        # Dotclear autoloader (once)
        if (!static::$autoloader) {
            require_once implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'Utils', 'Autoloader.php']);
            static::$autoloader = new Utils\Autoloader();
            static::$autoloader->addNamespace(__NAMESPACE__, DOTCLEAR_ROOT_DIR);
        }

        # Find process (Admin|Public|Install|...)
        $class = implode('\\', [__NAMESPACE__, ucfirst(strtolower($process)), 'Prepend']);
        if (!is_subclass_of($class, __NAMESPACE__ . '\\Core\\Core')) {
            static::error('No process', 'Something went wrong while trying to start process.', 5);
        }

        # Execute Process
        ob_end_clean();
        try {
            ob_start();
            $class::coreInstance($blog_id);
            ob_end_flush();
        # Catch all errors and display or not them
        } catch(Exception $e) {
            ob_end_clean();

            $detail = defined('DOTCLEAR_RUN_LEVEL') && DOTCLEAR_RUN_LEVEL > DOTCLEAR_RUN_PRODUCTION ?
                ' The following error was encountered: ' . $e->getMessage() : '';

            static::error('Unexpected error', 'Sorry, execution of the script is halted.' . $detail, $e->getCode());
        }
    }

    /**
     * Singleton Dotclear Core
     *
     * @return  Singleton   The core instance
     */
    public static function core()
    {
        $class = __NAMESPACE__ . '\\Core\\Core';
        return $class::coreInstance();
    }

    public static function error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        static::error('Fatal error: ' . $errno, $errstr . ' in ' . $errfile . ':' . $errline);
        return false;
    }

    /**
     * Error page
     *
     * Some of Dotclear error codes
     * -  5 : no process found
     * - 10 : no config file
     * - 20 : database issue
     * - 30 : blog is not defined
     * - 40 : template files creation
     * - 50 : no default theme
     * - 60 : template processing error
     * - 70 : blog is offline
     *
     * @param   string  $message    The message
     * @param   string  $detail     The detail
     * @param   int     $code       The code
     */
    public static function error(string $message, string $detail = '', int $code = 0): void
    {
        # Display message only in CLI mode
        if (PHP_SAPI == 'cli') {
            trigger_error($message, E_USER_ERROR);

        # Display error through a plateform custom error page
        } elseif (defined('DOTCLEAR_ERROR_FILE') && is_file(DOTCLEAR_ERROR_FILE)) {
            include DOTCLEAR_ERROR_FILE;

        # Display error through an internal error page
        } else {
            header('Content-Type: text/html; charset=utf-8');
            header('HTTP/1.0 ' . $code . ' ' . $message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="ROBOTS" content="NOARCHIVE,NOINDEX,NOFOLLOW" />
  <meta name="GOOGLEBOT" content="NOSNIPPET" />
  <title>Dotclear - Error</title>
  <style media="screen" type="text/css">
  <!--
  body {
    font: 62.5%/1.5em "DejaVu Sans","Lucida Grande","Lucida Sans Unicode",Arial,sans-serif;
    color : #DCDEE0;
    background : #565A60;
    margin : 0;
    padding : 0;
  }
  #content {
      margin: 1em 20%;
      padding: 1px 1em 2em;
      background: #272b30;
      font-size: 1.3em;
      border: 1px solid #DADBDE;
      border-radius: 0.75em;
  }
  a, a:link, a:visited {
    color : #76C2F1;
    text-decoration : none;
    border-bottom : 1px dotted #82878F;
  }
  h1 {
    color: #F3F4F5;
    font-size: 2.5em;
    font-weight: normal;
  }

  h2 {
    color: #FF6E3A;
    font-size: 1.4em;
  }
  -->
</style>
</head>

<body>
<div id="content">
<h1><?php echo defined('DOTCLEAR_VENDOR_NAME') ? htmlspecialchars(DOTCLEAR_VENDOR_NAME, ENT_COMPAT, "UTF-8") : 'Dotclear' ?></h1>
<h2><?php echo $message; ?></h2>
<?php echo $detail; ?></div>
</body>
</html>
<?php
            exit(0);
        }
    }
    //@}

}

/** @see Dotclear::core() */
function core()
{
    return App::core();
}
