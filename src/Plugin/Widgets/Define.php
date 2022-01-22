<?php
/**
 * @brief Dotclear Plugins define
 *
 * @package Dotclear
 * @subpackage PluginAboutConfig
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        'Widgets'
    )
    ->SetDescription(
        __('Widgets for your blog sidebars')
    )
    ->setAuthor(
        'Olivier Meunier & Dotclear Team'
    )
    ->setVersion(
        '3.5'
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
    ->setPriority(
        1000000000
    )
;
