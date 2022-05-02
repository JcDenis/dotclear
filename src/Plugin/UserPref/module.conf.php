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
    'name'       => __('user:preferences'),
    'description'=> __('Manage every user preference directive'),
    'version'    => '0.4-dev',
    'author'     => 'Franck Paul',
    'type'       => 'Plugin',
    'requires'   => [
        'core' => '3.0-dev',
    ],
];
