<?php
/**
 * @brief Dotclear Plugins define
 *
 * @package Dotclear
 * @subpackage PluginAttachments
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        __('attachments')
    )
    ->SetDescription(
        __('Manage post attachments')
    )
    ->setAuthor(
        'Dotclear Team'
    )
    ->setVersion(
        '1.2-dev'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setType(
        'Plugin'
    )
    ->setPermissions(
        'usage,contentadmin,pages'
    )
    ->setPriority(
        999
    )
;
