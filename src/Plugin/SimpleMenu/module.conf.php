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
    'name'        => __('Simple menu'),
    'description' => __('Simple menu for Dotclear'),
    'version'     => '1.7-dev',
    'author'      => 'Franck Paul',
    'type'        => 'Plugin',
    'permissions' => 'usage,contentadmin',
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
