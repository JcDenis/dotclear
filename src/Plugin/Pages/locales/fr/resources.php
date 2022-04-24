<?php
/**
 * @ingroup  PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('pages', __DIR__ . '/help/pages.html');
dotclear()->help()->context('page', __DIR__ . '/help/page.html');
