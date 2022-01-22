<?php
/**
 * @brief Dotclear Plugins define
 *
 * @package Dotclear
 * @subpackage PluginSimpleMenu
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        __('Simple menu')
    )
    ->SetDescription(
        __('Simple menu for Dotclear')
    )
    ->setAuthor(
        'Franck Paul'
    )
    ->setVersion(
        '1.6'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setType(
        'Plugin'
    )
    ->setPermissions(
        'admin'
    )
    ->setSettings(
        ['self' => '']
    )
;
