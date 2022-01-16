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

namespace Dotclear\Plugin\AboutConfig;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        'about:config'
    )
    ->SetDescription(
        __('Manage every blog configuration directive')
    )
    ->setAuthor(
        'Olivier Meunier'
    )
    ->setVersion(
        '0.5'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setType(
        'Plugin'
    )
;
