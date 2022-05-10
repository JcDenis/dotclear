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
    'name'        => __('Widgets'),
    'description' => __('Widgets for your blog sidebars'),
    'version'     => '3.6-dev',
    'author'      => 'Olivier Meunier and Dotclear Team',
    'type'        => 'Plugin',
    'permissions' => 'admin',
    'priority'    => 1000000000,
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
