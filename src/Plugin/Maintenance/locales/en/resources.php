<?php
/**
 * @ingroup  PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('maintenance', __DIR__ . '/help/maintenance.html');
