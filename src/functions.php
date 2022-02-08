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

if (function_exists('dotclear')) {
    App::error('Fatal error', 'Function "dotclear" already exists', 5);
} else {
    function dotclear()
    {
        return \Dotclear\App::core();
    }
}
