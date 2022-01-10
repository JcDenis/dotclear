<?php
/**
 * @brief Dotclear Plugins define
 *
 * @package Dotclear
 * @subpackage PluginUserPref
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\UserPref;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        'user:preferences'
    )
    ->SetDescription(
        __('Manage every user preference directive')
    )
    ->setAuthor(
        'Franck Paul'
    )
    ->setversion(
        '0.3'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
;
