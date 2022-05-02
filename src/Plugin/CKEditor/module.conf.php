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
    'name'        => __('CKEditor'),
    'description' => __('dotclear CKEditor integration'),
    'version'     => '1.3-dev',
    'author'      => 'Dotclear Team',
    'type'        => 'Plugin',
    'permissions' => 'usage,contentadmin',
    'settings'    => [
        'pref' => '#user-options.user_options_edition',
    ],
    'requires' => [
        'core' => '3.0-dev',
    ],
];
