<?php
/**
 * @class Dotclear\Theme\Berlin
 * @brief blueSilence, a theme for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Themes
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Berlin;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        'Berlin'
    )
    ->SetDescription(
        __('Dotclear 2.7+ default theme')
    )
    ->setAuthor(
        'Dotclear Team'
    )
    ->setVersion(
        '1.4'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setType(
        'Theme'
    )
    ->setTemplateset(
        'Dotty'
    )
;
