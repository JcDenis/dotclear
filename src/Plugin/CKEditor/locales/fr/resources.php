<?php
/**
 * @ingroup  PluginCKEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('dcCKEditor', __DIR__ . '/help/config_help.html');
