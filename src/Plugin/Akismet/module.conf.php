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
    'name'        => __('Akismet'),
    'description' => __('Akismet interface for Dotclear'),
    'version'     => '1.2-dev',
    'author'      => 'Olivier Meunier',
    'type'        => 'Plugin',
    'permissions' => 'usage,contentadmin',
    'priority'    => 200,
    'requires'    => [
        'core'     => '3.0-dev',
        'Antispam' => '0',
    ],
];
