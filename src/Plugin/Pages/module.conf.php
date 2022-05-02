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
    'name'        => __('Pages'),
    'description' => __('Serve entries as simple web pages'),
    'version'     => '1.6-dev',
    'author'      => 'Olivier Meunier',
    'type'        => 'Plugin',
    'permissions' => 'contentadmin,pages',
    'priority'    => 999,
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
