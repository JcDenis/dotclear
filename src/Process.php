<?php
/**
 * @brief Dotclear process class
 *
 * Dotclear process launcher
 * ex: Call new Dotclear\Process('admin'); to load admin pages
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
        /* Define Dotclear root directory */
        if (!defined('DOTCLEAR_ROOT_DIR')) {
            define('DOTCLEAR_ROOT_DIR', dirname(__FILE__));
        }

        /* Dotclear autoloader (once) */
        if (!static::$autoloader) {
            require_once dirname(__FILE__) . '/Utils/Autoloader.php';
            static::$autoloader = new Utils\Autoloader();
            static::$autoloader->addNamespace(__NAMESPACE__, DOTCLEAR_ROOT_DIR);
        }

        /* Find process (Admin|Public|Instal|...) */
        $class = __NAMESPACE__ . '\\' . ucfirst($process) . '\\Prepend';
        if (!is_subclass_of($class, 'Dotclear\\Core\\Core')) {
            exit('No process');
        }

        ob_end_clean();
        ob_start();
        new $class();
        ob_end_flush();
    }
}
