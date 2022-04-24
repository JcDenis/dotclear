<?php
/**
 * @ingroup  PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('import', __DIR__ . '/help/import.html');
