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
    'name'       => __('themeEditor'),
    'description'=> __('Theme Editor'),
    'version'    => '1.5-dev',
    'author'     => 'Olivier Meunier',
    'type'       => 'Plugin',
    'permissions'=> 'usage,contentadmin',
    'settings'   => [
        'pref'=> '#user-options.themeEditor_prefs',
    ],
    'requires' => [
        'core' => '3.0-dev',
    ],
];
