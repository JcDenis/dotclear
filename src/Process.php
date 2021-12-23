<?php
/**
 * @class Dotclear\Process
 * @brief Dotclear process launcher class
 *
 * Call new Dotclear\Process('admin'); to load admin pages
 * could be called also by Dotclear\Process::admin();
 *
 * @package Dotclear
 * @subpackage Process
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear;

class Process
{
    /** @var Autoloader Dotclear custom autoloader */
    public static $autoloader;

    /**
     * Start Dotclear process
     *
     * @param  string $process public/admin/install/...
     */
    public function __construct(string $process = 'public')
    {
        /* Timer and memory usage for stats and dev */
        if (!defined('DOTCLEAR_START_TIME')) {
            define('DOTCLEAR_START_TIME', microtime(true));
        }
        if (!defined('DOTCLEAR_START_MEMORY')) {
            define('DOTCLEAR_START_MEMORY', memory_get_usage(false));
        }

        /* Define Dotclear root directory */
        if (!defined('DOTCLEAR_ROOT_DIR')) {
            define('DOTCLEAR_ROOT_DIR', dirname(__FILE__));
        }

        /* Dotclear autoloader (once) */
        if (!static::$autoloader) {
            require_once implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), 'Utils', 'Autoloader.php']);
            static::$autoloader = new Utils\Autoloader();
            static::$autoloader->addNamespace(__NAMESPACE__, DOTCLEAR_ROOT_DIR);
        }

        /* Find process (Admin|Public|Install|...) */
        $class = implode('\\', [__NAMESPACE__, ucfirst($process), 'Prepend']);
        if (!is_subclass_of($class, 'Dotclear\\Core\\Core')) {
            exit('No process');
        }

        ob_end_clean();
        ob_start();
        new $class();
        ob_end_flush();
    }

    public static function __callStatic(string $process, array $_): void
    {
        new Process($process);
    }
}
