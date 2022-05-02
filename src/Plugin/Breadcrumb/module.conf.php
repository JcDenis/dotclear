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
    'name'        => __('Breadcrumb'),
    'description' => __('Breadcrumb for Dotclear'),
    'version'     => '0.8-dev',
    'author'      => 'Franck Paul',
    'type'        => 'Plugin',
    'permissions' => 'usage,contentadmin',
    'settings'    => [
        'blog' => '#params.breadcrumb_params',
    ],
    'requires' => [
        'core' => '3.0-dev',
    ],
];
