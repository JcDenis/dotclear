<?php
/**
 * @ingroup  PluginThemeEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('themeEditor', dirname(__FILE__) . '/help/help.html');
