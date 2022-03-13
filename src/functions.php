<?php
/**
 * @brief Dotclear root functions
 *
 * @package Dotclear
 * @subpackage Process
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

if (!function_exists('dotclear_run')) {

    /**
     * Run process
     *
     * @param  string   $process    The process (admin,install,public...)
     * @param  string   $blog_id    The blog id for public process
     */
    function dotclear_run(string $process, ?string $blog_id = null): void
    {
        set_error_handler('dotclear_error_handler');

        # Define Dotclear root directory
        if (!defined('DOTCLEAR_ROOT_DIR')) {
            define('DOTCLEAR_ROOT_DIR', __DIR__);
        }

        # Third party autoload (PSR-4 compliant)
        $file = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($file)) {
            require $file;
        # Dotclear autoload
        } else {
            require_once root_path('Helper', 'Autoload.php');
            $autoload = new Dotclear\Helper\Autoload();
            $autoload->addNamespace('Dotclear', DOTCLEAR_ROOT_DIR);
        }

        # Find process (Admin|Public|Install|...)
        $class = root_ns('Process', ucfirst(strtolower($process)), 'Prepend');
        if (!is_subclass_of($class, 'Dotclear\\Core\\Core')) {
            dotclear_error('No process found', 'Something went wrong while trying to start process.');
        }

        # Execute Process
        try {
            ob_start();
            $class::singleton($blog_id);
            ob_end_flush();

        # Try to display unexpected Exceptions as much cleaned as we can
        } catch (\Exception $e) {
            ob_end_clean();

            try {
                if (dotclear() && dotclear()->config()) {
                    if (dotclear()->production() === false) {
                        dotclear_error(get_class($e), $e->getMessage() . dotclear_error_trace($e->getTrace()), $e->getCode());
                    } else {
                        dotclear_error(get_class($e), $e->getMessage(), $e->getCode());
                    }
                }
            } catch (\Exception) {
            }
            dotclear_error('Unexpected error', 'Sorry, execution of the script is halted.', $e->getCode());
        }
    }
}

if (!function_exists('dotclear')) {

    /**
     * Singleton Dotclear Core
     *
     * @return  Core|null   Singleton core instance
     */
    function dotclear(): ?Dotclear\Core\Core
    {
        if (class_exists('Dotclear\\Core\\Core')) {
            return Dotclear\Core\Core::singleton();
        }

        dotclear_error('Runtime error', 'Core instance can not be called directly.', 601);
    }
}

if (!function_exists('dotclear_error')) {

    /**
     * Error page
     *
     * @param   string  $message    The message
     * @param   string  $detail     The detail
     * @param   int     $code       The code
     */
    function dotclear_error(string $message, string $detail = '', int $code = 0): void
    {
        @ob_clean();

        # Display message only in CLI mode
        if (PHP_SAPI == 'cli') {
            trigger_error($message, E_USER_ERROR);

        # Display error through a plateform custom error page
        } elseif (defined('DOTCLEAR_ERROR_FILE') && is_file(DOTCLEAR_ERROR_FILE)) {
            include DOTCLEAR_ERROR_FILE;

        # Display error through an internal error page
        } else {
            $detail = str_replace("\n", '<br />', $detail);

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
<h1>Dotclear</h1>
<h2><?php echo $message; ?></h2>
<?php echo $detail; ?></div>
</body>
</html>
<?php
            exit(0);
        }
    }
}

if (!function_exists('dotclear_error_trace')) {

    function dotclear_error_trace(array $traces): string
    {
        $res = '';
        foreach($traces as $i => $line) {
            $res .=
                '#' . $i .' ' .
                (!empty($line['class']) ? $line['class'] .'::' : '') .
                (!empty($line['function']) ? $line['function'] . ' -- ' : '') .
                (!empty($line['file']) ? $line['file'] . ":" : '') .
                (!empty($line['line']) ? $line['line'] : '') .
                "\n";
        }
        return sprintf("\n<pre>Traces : \n%s</pre>", $res);
    }
}

if (!function_exists('dotclear_error_handler')) {

    function dotclear_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (PHP_SAPI == 'cli' || !(error_reporting() & $errno)) {
            return false;
        }
        dotclear_error('Unexpected error', $errstr . "\n" . $errfile . '::' . $errline, $errno);
    }
}

if (!function_exists('root_path')) {

    function root_path(string ...$args): string
    {
        if (defined('DOTCLEAR_ROOT_DIR')) {
            array_unshift($args, DOTCLEAR_ROOT_DIR);
        }

        return implode(DIRECTORY_SEPARATOR, $args);
    }
}

if (!function_exists('implode_path')) {

    function implode_path(string ...$args): string
    {
        return implode(DIRECTORY_SEPARATOR, $args);
    }
}

if (!function_exists('root_ns')) {

    function root_ns(string ...$args): string
    {
        array_unshift($args, 'Dotclear');

        return implode('\\', $args);
    }
}

if (!function_exists('implode_ns')) {

    function implode_ns(string ...$args): string
    {
        return implode('\\', $args);
    }
}
