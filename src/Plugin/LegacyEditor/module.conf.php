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
    'name'        => __('Legacy editor'),
    'description' => __('dotclear standard editor'),
    'version'     => '0.2-dev',
    'author'      => 'dotclear Team',
    'type'        => 'Plugin',
    'permissions' => 'usage,contentadmin',
    'settings'    => [
        'pref' => '#user-options.user_options_edition',
    ],
    'requires' => [
        'core' => '3.0-dev',
    ],
];
