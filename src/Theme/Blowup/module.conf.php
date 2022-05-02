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
    'name'        => __('Blowup'),
    'description' => __('Default Dotclear theme, fully customizable'),
    'version'     => '1.1-dev',
    'author'      => 'Marco and Olivier',
    'type'        => 'Theme',
    'templateset' => 'mustek',
    'requires'    => [
        'Core' => '3.0-dev',
    ],
];
