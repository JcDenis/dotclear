<?php
/**
 * @class Dotclear\Theme\CustomCSS
 * @brief CustomCSS, a theme for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Themes
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\CustomCSS;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

$this
    ->setName(
        'Custom theme'
    )
    ->SetDescription(
        __('A CSS customizable theme')
    )
    ->setAuthor(
        'Olivier Meunier'
    )
    ->setVersion(
        '1.2'
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
