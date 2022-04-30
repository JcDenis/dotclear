<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear;

/**
 * Application.
 *
 * Run process from this class.
 *
 * @ingroup Process Core
 */
class App
{
    /**
     * Run process.
     *
     * @param string      $process The process (admin,install,public...)
     * @param null|string $blog_id The blog id for public process
     */
    final public static function run(string $process, ?string $blog_id = null)
    {
        // Third party autoload (PSR-4 compliant)
        $file = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'vendor', 'autoload.php']);
        if (file_exists($file)) {
            require_once $file;
        // Dotclear autoload
        } else {
            require_once implode(DIRECTORY_SEPARATOR, [__DIR__, 'Helper', 'Autoload.php']);
            $autoload = new \Dotclear\Helper\Autoload();
            $autoload->addNamespace('Dotclear', __DIR__);
        }

        // Find process (Admin|Public|Install|...)
        $class = 'Dotclear\\Process\\' . ucfirst(strtolower($process)) . '\\Prepend';
        if (!is_subclass_of($class, 'Dotclear\\Core\\Core')) {
            self::stop('No process found', 'Something went wrong while trying to start process.');
        }

        // Execute Process
        try {
            ob_start();
            $class::singleton($blog_id);
            ob_end_flush();

            // Try to display unexpected Exceptions as much cleaned as we can
        } catch (\Exception|\Error $e) {
            ob_end_clean();

            try {
                $traces = null;
                if (false === self::core()?->production()) {
                    $traces = $e->getTrace();
                    array_unshift($traces, ['file' => $e->getFile(), 'line' => $e->getLine()]);
                    if (null != ($previous = $e->getPrevious())) {
                        array_unshift($traces, ['file' => $previous->getFile(), 'line' => $previous->getLine()]);
                    }
                }
                self::stop(get_class($e), $e->getMessage(), $e->getCode(), $traces);
            } catch (\Exception|\Error) {
            }
            self::stop('Unexpected error', 'Sorry, execution of the script is halted.', $e->getCode());
        }
    }

    /**
     * Call singleton core.
     *
     * @return null|object Singleton core instance
     */
    final public static function core()
    {
        if (class_exists('Dotclear\\Core\\Core')) {
            return \Dotclear\Core\Core::singleton();
        }

        self::stop('Runtime error', 'Core instance can not be called directly.', 601);

        return null;
    }

    /**
     * Stop process and display errors.
     *
     * @param string                $message The message
     * @param string                $detail  The detail
     * @param int                   $code    The code
     * @param null|array<int,array> $traces  The traces
     */
    final public static function stop(string $message, string $detail = '', int $code = 0, ?array $traces = null): void
    {
        @ob_clean();

        // Display message only in CLI mode
        if (PHP_SAPI == 'cli') {
            trigger_error($message, E_USER_ERROR);

        // Display error through a plateform custom error page
        } elseif (defined('DOTCLEAR_ERROR_FILE') && is_file(\DOTCLEAR_ERROR_FILE)) {
            include \DOTCLEAR_ERROR_FILE;

        // Display error through an internal error page
        } else {
            if (!empty($traces)) {
                $res = '';
                foreach ($traces as $i => $line) {
                    $res .=
                        '#' . $i . ' ' .
                        (!empty($line['class']) ? $line['class'] . '::' : '') .
                        (!empty($line['function']) ? $line['function'] . ' -- ' : '') .
                        (!empty($line['file']) ? $line['file'] . ':' : '') .
                        (!empty($line['line']) ? $line['line'] : '') .
                        "\n";
                }
                $detail .= sprintf("\n<pre>Traces : \n%s</pre>", $res);
            }

            $detail = str_replace("\n", '<br />', $detail);

            header('Content-Type: text/html; charset=utf-8');
            header('HTTP/1.0 ' . $code . ' ' . $message); ?>
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
      margin: 1em 5%;
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