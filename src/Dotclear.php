<?php
/**
 * @class Dotclear
 * @brief Dotclear process launcher
 *
 * Call Dotclear('admin'); to load admin pages
 * could be called also by Dotclear::Admin();
 *
 * For a public blog, use Dotclear::Public('myblogid');
 * or Dotclear('public', 'myblogid');
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

Class Dotclear
{
    /** @var    Autoloader  Dotclear custom autoloader */
    public static $autoloader;

    /**
     * Start Dotclear process
     *
     * @param   string  $process    The process to launch public/admin/install/...
     * @param   string  $blog_id    The blog id for Public process
     */
    public function __construct(string $process = 'public', string $blog_id = null)
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
            static::$autoloader = new Dotclear\Utils\Autoloader();
            static::$autoloader->addNamespace(__CLASS__, DOTCLEAR_ROOT_DIR);
        }

        //*
        # Legacy mode temporary fix
        require_once implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'DotclearLegacy.php']);
        //*/

        # Singleton class (must be required here to be know by fonction dcCore())
        require_once implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'Core', 'SingleTon.php']);

        # Find process (Admin|Public|Install|...)
        $class = implode('\\', [__CLASS__, ucfirst(strtolower($process)), 'Prepend']);
        if (!is_subclass_of($class, __CLASS__ . '\\Core\\Core')) {
            exit('No process');
        }

        # Execute Process
        ob_end_clean();
        ob_start();
        $class::coreInstance($blog_id);
        ob_end_flush();
    }

    /**
     * Call statically Dotclear process
     *
     * @param  string $process The process (admin,install,public...)
     * @param  array  $args    The arguments (only args[0] for blog id)
     */
    public static function __callStatic(string $process, array $args): void
    {
        $blog_id = isset($args[0]) && is_string($args[0]) ? $args[0] : null;
        new static($process, $blog_id);
    }
}

/**
 * Singleton Dotclear Core
 *
 * @return  Singleton   The core instance
 */
function dcCore()
{
    return Dotclear\Core\SingleTon::coreInstance();
}
