<?php
/**
 * @class Dotclear\Core\SingleTon
 * @brief Dotclear core unique instance provider
 *
 * Use dcCore() function to call core instance from everywhere
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

class SingleTon
{
    private static $instance;

    public static function coreInstance(?string $blog_id = null): SingleTon
    {
        if (null === self::$instance) {
            # Two stage instanciation (construct then process)
            self::$instance = new static();
            self::$instance->process($blog_id);
        }

        return self::$instance;
    }

    protected function __construct()
    {
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }

    /**
     * Join folder function
     *
     * Starting from Dotclear root directory
     *
     * @param  string   $args   One argument per folder
     *
     * @return string   Directory
     */
    public static function root(string ...$args): string
    {
        if (!defined('DOTCLEAR_ROOT_DIR')) {
            define('DOTCLEAR_ROOT_DIR', __DIR__);
        }

        return implode(DIRECTORY_SEPARATOR, array_merge([DOTCLEAR_ROOT_DIR], $args));
    }

    /**
     * Join folder function
     *
     * @param  string   $args   One argument per folder
     *
     * @return string   Directory
     */
    public static function path(string ...$args): string
    {
        return implode(DIRECTORY_SEPARATOR, $args);
    }

    /**
     * Join sub namespace function
     *
     * @param  string   $args   One argument per sub namespace
     *
     * @return string   Namespace
     */
    public static function ns(string ...$args): string
    {
        return implode('\\', $args);
    }
}
