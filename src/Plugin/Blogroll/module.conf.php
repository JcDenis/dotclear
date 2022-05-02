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
    'name'        => __('Blogroll'),
    'description' => __('Manage your blogroll'),
    'version'     => '1.6-dev',
    'author'      => 'Olivier Meunier',
    'type'        => 'Plugin',
    'permissions' => 'blogroll',
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
