<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

if (!class_exists('Dotclear\App')) {
    exit(1);
}

return [
    'name'       => __('ThomasDaveluy'),
    'description'=> __('Original Dotclear 2.4/2.5 iconset from Thomas Daveluy'),
    'version'    => '0.2-dev',
    'author'     => 'Thomas Daveluy',
    'type'       => 'Iconset',
    'requires'   => [
        'core'=> '3.0-dev',
    ],
];
