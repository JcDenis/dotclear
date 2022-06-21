<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear;

use Error;
use Exception;

/**
 * Application.
 *
 * Run process from this class.
 * 
 * @ingroup Process Core
 */
final class App
{
    private static $autoload;
    private static $class;

    /**
     * Use composer autoload.
     * 
     * If you need to use composer autoloader, 
     * call this method to instanciate it.
     * If Dotclear runs as composer package, you don't need this.
     */
    public static function useComposerAutoload(): void
    {
        $file = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'vendor', 'autoload.php']);
        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Run process.
     *
     * @param string $process The process (admin,install,public...)
     * @param string $blog_id The blog id for public process
     */
    public static function run(string $process, string $blog_id = null): void
    {
        try {
            if (self::$class) {
                throw new Exception('Application can not be started twice.', 500);
            }

            $process = ucfirst(strtolower($process));
            if (!in_array($process, ['Public', 'Admin', 'Install', 'Distrib'])) {
                throw new Exception(sprintf('Application can not run process %s.', $process), 500);
            }
            $class = '\\Dotclear\\Process\\' . $process . '\\Prepend';

            // Dotclear autoload (used first)
            self::autoload()->addNamespace('Dotclear', __DIR__);

            // Execute Process
            ob_start();
            self::$class = new $class(process: $process);
            self::$class->startProcess(blog: $blog_id);
            ob_end_flush();
        } catch (Exception|Error $e) {
            ob_end_clean();

            // Try to display unexpected Exceptions as much cleaned as we can
            if (false === self::core()?->isProductionMode()) {
                self::stop(new Exception($e->getMessage(), $e->getCode(), $e), false);
            } else {
                $msg = '<p>We apologize for this temporary unavailability.<br />Thank you for your understanding.</p>';
                // If we crash before L10n loaded, there's a big issue and reason must be displayed.
                self::stop(new Exception(function_exists('__') ? __($msg) : $msg . "\n" . $e->getMessage(), 503, $e));
            }
        }
    }

    /**
     * Call core (child process).
     *
     * App:core() is callable from everywhere in code.
     *
     * @return null|object The core child instance
     */
    public static function core(): ?object
    {
        return self::$class;
    }

    /**
     * Call Dotclear autoloader.
     *
     * @return Autoload $autoload The autoload instance
     */
    public static function autoload(): Autoload
    {
        if (!self::$autoload) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'Autoload.php';
            self::$autoload = new Autoload(prepend: true);
        }

        return self::$autoload;
    }

    /**
     * Stop process and display errors.
     *
     * @param Error|Exception $e          The Exception
     * @param bool            $production The production mode
     */
    public static function stop(Exception|Error $e, $production = true): void
    {
        @ob_clean();

        // Display message only in CLI mode
        if (PHP_SAPI == 'cli') {
            trigger_error($e->getMessage(), E_USER_ERROR);

        // Display error through a plateform custom error page
        } elseif (defined('DOTCLEAR_ERROR_FILE') && is_file(\DOTCLEAR_ERROR_FILE)) {
            include \DOTCLEAR_ERROR_FILE;

        // Display error through an internal error page
        } else {
            $title   = self::code($e->getCode());
            $trans   = function_exists('__') ? __($title) : $title;
            $trace   = $production ? '' : self::trace($e);
            $message = str_replace("\n", '<br />', $e->getMessage() . $trace);

            header('Content-Type: text/html; charset=utf-8');
            header('HTTP/1.0 ' . $e->getCode() . ' ' . $title); ?>
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

  .tf, .tl {
    color: #FF6E3A;
  }
  .tc {
    color: #82878F;
  }
  -->
</style>
</head>

<body>
<div id="content">
<h1>Dotclear</h1>
<h2><?php echo $trans; ?></h2>
<?php echo $message; ?></div>
</body>
</html>
<?php
            exit(0);
        }
    }

    /**
     * Get trace of Exception.
     *
     * This include current exception in trace
     * and arrange them in a nice display.
     *
     * @param Error|Exception $e The exception
     *
     * @return string The trace
     */
    private static function trace(Exception|Error $e): string
    {
        $dt = debug_backtrace(options: 0);
        if (!($lines = $dt[0]['args'][0]->getPrevious()?->getTrace())) {
            $lines = [];
        }

        array_unshift($lines, ['class' => $e::class, 'function' => ' caught in', 'file' => $e->getFile(), 'line' => $e->getLine()]);
        if (null != ($previous = $e->getPrevious())) {
            array_unshift($lines, ['class' => $previous::class, 'function' => ' thrown in', 'file' => $previous->getFile(), 'line' => $previous->getLine()]);
        }

        $traces = '';
        $span   = '<span class="%s">%s%s</span>';
        foreach ($lines as $i => $line) {
            $traces .=
                '<li>' .
                (!empty($line['class']) ? sprintf($span, 'tc', $line['class'], '::') : '') .
                (!empty($line['function']) ? sprintf($span, 'tf', $line['function'], ' ') : '') .
                (!empty($line['file']) ? sprintf($span, 'tp', $line['file'], ':') : '') .
                (!empty($line['line']) ? sprintf($span, 'tl', $line['line'], '') : '') .
                '</li>';
        }

        return sprintf('<h2>Traces</h3><ul>%s</ul>', $traces);
    }

    /**
     * Get Exception title according to code.
     *
     * @param int $code The code
     *
     * @return string The title
     */
    private static function code(int $code): string
    {
        return match ($code) {
            401     => 'Unauthorized',
            412     => 'Precondition failed',
            500     => 'Invalid configuration.',
            default => 'Site temporarily unavailable', // 503
        };
    }
}
