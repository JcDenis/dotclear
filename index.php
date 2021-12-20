<?php
/**
 * @brief Dotclear process example
 *
 * This file is the public (frontend) acces point
 * of the default blog.
 *
 * @package Dotclear
 * @subpackage Process
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (isset($_SERVER['DC_BLOG_ID'])) {
    define('DC_BLOG_ID', $_SERVER['DC_BLOG_ID']);
} elseif (isset($_SERVER['REDIRECT_DC_BLOG_ID'])) {
    define('DC_BLOG_ID', $_SERVER['REDIRECT_DC_BLOG_ID']);
} else {
    # Define your blog here
    define('DC_BLOG_ID', 'default');
}

require implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), 'src', 'Process.php']);

new Dotclear\Process('public');
