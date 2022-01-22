<?php
/**
 * @class Dotclear\Theme\BlowUp
 * @brief blueSilence, a theme for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Themes
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\BlowUp;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        'Blowup'
    )
    ->SetDescription(
        __('Default Dotclear theme, fully customizable')
    )
    ->setAuthor(
        'Marco & Olivier'
    )
    ->setVersion(
        '1.0'
    )
    ->setRequires(
        [['core', '3.0-dev']]
    )
    ->setType(
        'Theme'
    )
    ->setTemplateset(
        'Mustek'
    )
;
