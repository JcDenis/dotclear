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
    'name'        => __('Fair Trackbacks'),
    'description' => __('Trackback validity check'),
    'version'     => '1.1.2-dev',
    'author'      => 'Olivier Meunier',
    'type'        => 'Plugin',
    'permissions' => 'usage,contentadmin',
    'priority'    => 200,
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
