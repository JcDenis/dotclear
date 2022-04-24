<?php
/**
 * @ingroup  PluginBlogroll
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('blogroll', __DIR__ . '/help/blogroll.html');
