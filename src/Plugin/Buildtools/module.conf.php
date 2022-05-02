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
    'name'        => __('Buildtools'),
    'description' => __('Internal build tools for dotclear team'),
    'version'     => '1.1-dev',
    'author'      => 'Dotclear team',
    'type'        => 'Plugin',
    'permissions' => 'admin',
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
