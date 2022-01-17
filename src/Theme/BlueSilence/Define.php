<?php
/**
 * @class Dotclear\Theme\BlueSilence
 * @brief blueSilence, a theme for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Themes
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\BlueSilence;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        'Blue Silence'
    )
    ->SetDescription(
        __('Dotclear Theme')
    )
    ->setAuthor(
        'Marco / marcarea.com'
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
;
