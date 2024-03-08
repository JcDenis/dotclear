<?php
/**
 * @file
 * @brief       The Frontend endpoint for default blog.
 * @ingroup     Endpoint
 * @defgroup    Endpoint    Application endpoints.
 *
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
if (isset($_SERVER['DC_BLOG_ID'])) {
    define('DC_BLOG_ID', $_SERVER['DC_BLOG_ID']);
} elseif (isset($_SERVER['REDIRECT_DC_BLOG_ID'])) {
    define('DC_BLOG_ID', $_SERVER['REDIRECT_DC_BLOG_ID']);
} else {
    # Define your blog here
    define('DC_BLOG_ID', 'default');
}

require_once implode(DIRECTORY_SEPARATOR, [__DIR__, 'src', 'App.php']);

new Dotclear\App('Frontend');
