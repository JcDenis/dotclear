<?php
/**
 * @brief Dotclear Plugins define
 *
 * @package Dotclear
 * @subpackage PluginThemeEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ThemeEditor;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        'themeEditor'
    )
    ->SetDescription(
        __('Theme Editor')
    )
    ->setAuthor(
        'Olivier Meunier'
    )
    ->setVersion(
        '1.5-dev'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setType(
        'Plugin'
    )
    ->setSettings(
        ['pref' => '#user-options.themeEditor_prefs']
    )
;
