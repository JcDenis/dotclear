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
    'name'        => __('Import & Export'),
    'description' => __('Import and Export your blog'),
    'version'     => '3.3-dev',
    'author'      => 'Olivier Meunier and Contributors',
    'type'        => 'Plugin',
    'permissions' => 'admin',
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
