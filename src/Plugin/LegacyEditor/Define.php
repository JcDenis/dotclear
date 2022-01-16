<?php
/**
 * @brief Dotclear Plugins define
 *
 * @package Dotclear
 * @subpackage PluginLegacyEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        __('Legacy editor')
    )
    ->SetDescription(
        __('dotclear legacy editor')
    )
    ->setAuthor(
        'dotclear Team'
    )
    ->setVersion(
        '0.1.4'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setPermissions(
        'usage,contentadmin'
    )
    ->setSettings(
        ['pref' => '#user-options.user_options_edition']
    )
    ->setType(
        'Plugin'
    )
;
