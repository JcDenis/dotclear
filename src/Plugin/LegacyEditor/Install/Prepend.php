<?php
/**
 * @brief LegacyEditor, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

$version = $core->plugins->moduleInfo('LegacyEditor', 'version');
if (version_compare($core->getVersion('LegacyEditor'), $version, '>=')) {
    return;
}

$settings = $core->blog->settings;
$settings->addNamespace('LegacyEditor');
$settings->LegacyEditor->put('active', true, 'boolean', 'LegacyEditor plugin activated ?', false, true);

$core->setVersion('LegacyEditor', $version);

return true;
