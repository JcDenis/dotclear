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
    'name'       => __('Tags'),
    'description'=> __('Tags for posts'),
    'version'    => '1.7-dev',
    'author'     => 'Olivier Meunier',
    'type'       => 'Plugin',
    'permissions'=> 'usage,contentadmin',
    'priority'   => 1001,
    'settings'   => [
        'pref'=> '#user-options.tags_prefs',
    ],
    'requires' => [
        'core' => '3.0-dev',
    ],
];
