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
    'name'        => __('attachments'),
    'description' => __('Manage post attachments'),
    'version'     => '1.2-dev',
    'author'      => 'Dotclear Team',
    'type'        => 'Plugin',
    'permissions' => 'usage,contentadmin,pages',
    'priority'    => 999,
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
