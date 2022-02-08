<?php
/**
 * @brief Dotclear process example
 *
 * This file is the public (frontend) access point
 * of the default blog.
 *
 * If you move your public access point,
 * you must edit Blog URL in your blog settings.
 *
 * @package Dotclear
 * @subpackage Process
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */

/** For old compatibility only */
if (isset($_SERVER['DC_BLOG_ID'])) {
    $blog = $_SERVER['DC_BLOG_ID'];
} elseif (isset($_SERVER['REDIRECT_DC_BLOG_ID'])) {
    $blog = $_SERVER['REDIRECT_DC_BLOG_ID'];
} else {
    # Define your blog here
    $blog = 'default';
}

require implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), 'src', 'App.php']);

new \Dotclear\App('public', $blog);
