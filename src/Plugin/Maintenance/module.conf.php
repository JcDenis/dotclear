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
    'name'        => __('Maintenance'),
    'description' => __('Maintain your installation'),
    'version'     => '1.3.2-dev',
    'author'      => 'Olivier Meunier and Association Dotclear',
    'type'        => 'Plugin',
    'permissions' => 'admin',
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
