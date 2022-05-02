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
    'name'        => __('Antispam'),
    'description' => __('Generic antispam plugin for Dotclear'),
    'version'     => '1.4.2-dev',
    'author'      => 'Alain Vagner',
    'type'        => 'Plugin',
    'permissions' => 'usage,contentadmin',
    'settings'    => [
        'blog' => '#params.antispam_params',
    ],
    'priority' => 10,
    'requires' => [
        'core' => '3.0-dev',
    ],
];
