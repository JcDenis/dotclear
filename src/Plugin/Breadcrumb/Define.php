<?php
/**
 * @brief Dotclear Plugins define
 *
 * @package Dotclear
 * @subpackage PluginBreadcrumb
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        __('Breadcrumb')
    )
    ->SetDescription(
        __('Breadcrumb for Dotclear')
    )
    ->setAuthor(
        'Franck Paul'
    )
    ->setVersion(
        '0.8-dev'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setType(
        'Plugin'
    )
    ->setSettings(
        ['blog' => '#params.breadcrumb_params']
    )
    ->setPermissions(
        'usage,contentadmin'
    )
;
