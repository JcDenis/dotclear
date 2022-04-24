<?php
/**
 * @ingroup  PluginPings
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('pings', __DIR__ . '/help/pings.html');
dotclear()->help()->context('pings_post', __DIR__ . '/help/pings_post.html');
