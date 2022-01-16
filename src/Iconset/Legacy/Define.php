<?php
/**
 * @brief Dotclear Iconset define
 *
 * @package Dotclear
 * @subpackage IconsetLegacy
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Iconset\Legacy;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        __('Legacy')
    )
    ->SetDescription(
        __('Original Dotclear 2.0 icons')
    )
    ->setAuthor(
        'Dotclear team'
    )
    ->setversion(
        '0.1'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setType(
        'Iconset'
    )
;
