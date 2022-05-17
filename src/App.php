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
class App
{
    /**
     * Run process.
     *
     * @param string      $process The process (admin,install,public...)
     * @param null|string $blog_id The blog id for public process
     */
    final public static function run(string $process, ?string $blog_id = null): void
    {
        // Third party autoload (PSR-4 compliant)
        $file = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'vendor', 'autoload.php']);
        if (file_exists($file)) {
            require_once $file;
        }

        // Dotclear autoload (used first)
        require_once implode(DIRECTORY_SEPARATOR, [__DIR__, 'Helper', 'Autoload.php']);
        $autoload = new \Dotclear\Helper\Autoload(prepend: true);
        $autoload->addNamespace('Dotclear', __DIR__);

        // Find process (Admin|Public|Install|...)
        $class = 'Dotclear\\Process\\' . ucfirst(strtolower($process)) . '\\Prepend';
        if (!is_subclass_of($class, 'Dotclear\\Core\\Core')) {
            self::stop(new Exception('Something went wrong while trying to start process.', 605));
        }

        // Execute Process
        try {
            ob_start();
            $class::singleton($blog_id);
            ob_end_flush();
        } catch (Exception|Error $e) {
            ob_end_clean();

            // Try to display unexpected Exceptions as much cleaned as we can
            if (false === self::core()?->production()) {
                self::stop(new Exception($e->getMessage(), $e->getCode(), $e));
            } else {
                $msg = '<p>We apologize for this temporary unavailability.<br />Thank you for your understanding.</p>';
                // If we crash before L10n loaded, there's a big issue and reason must be displayed.
                self::stop(new Exception(function_exists('__') ? __($msg) : $msg . "\n" . $e->getMessage(), 503, $e));
            }
        }
    }

    /**
     * Call singleton core.
     *
     * App:core() is callable from everywhere in code.
     *
     * @return null|object Singleton core instance
     */
    final public static function core(): ?object
    {
        if (class_exists('Dotclear\\Core\\Core')) {
            return \Dotclear\Core\Core::singleton();
        }

        return null;
    }

    /**
     * Stop process and display errors.
     *
     * @param Error|Exception $e The Exception
     */
    final public static function stop(Exception|Error $e): void
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
            $trace   = false === self::core()?->production() ? self::trace($e) : '';
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
        $lines = $e->getTrace();
        if (null != ($previous = $e->getPrevious())) {
            array_unshift($lines, ['function' => 'Thrown in', 'file' => $previous->getFile(), 'line' => $previous->getLine()]);
        }
        array_unshift($lines, ['function' => 'Caught in', 'file' => $e->getFile(), 'line' => $e->getLine()]);

        $traces = '';
        $span   = '<span class="%s">%s</span>';
        foreach ($lines as $i => $line) {
            $traces .=
                '<li>' .
                (!empty($line['class']) ? sprintf($span, 'tc', $line['class']) . '::' : '') .
                (!empty($line['function']) ? sprintf($span, 'tf', $line['function']) . ' ' : '') .
                (!empty($line['file']) ? sprintf($span, 'tp', $line['file']) . ':' : '') .
                (!empty($line['line']) ? sprintf($span, 'tl', $line['line']) : '') .
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
            605     => 'No process found',
            610     => 'No config file',
            611     => 'Bad configuration',
            620     => 'Database issue',
            625     => 'User permission issue',
            628     => 'File handler not found',
            630     => 'Blog is not defined',
            640     => 'Template files creation',
            650     => 'No default theme',
            660     => 'Template processing error',
            670     => 'Blog is offline',
            default => 'Site temporarily unavailable',
        };
    }
}
