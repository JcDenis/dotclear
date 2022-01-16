<?php
/**
 * @brief Dotclear Iconset define
 *
 * @package Dotclear
 * @subpackage IconsetThomasDaveluy
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Iconset\ThomasDaveluy;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        __('ThomasDaveluy')
    )
    ->SetDescription(
        __('Original Dotclear 2.4/2.5 iconset from Thomas Daveluy')
    )
    ->setAuthor(
        'Thomas Daveluy'
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
