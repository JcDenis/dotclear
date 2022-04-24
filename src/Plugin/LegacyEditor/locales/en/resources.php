<?php
/**
 * @ingroup  PluginLegacyEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('LegacyEditor', __DIR__ . '/help/legacy_editor.html');
