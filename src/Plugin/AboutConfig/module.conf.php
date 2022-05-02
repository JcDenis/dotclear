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
    'name'        => __('about:config'),
    'description' => __('Manage every blog configuration directive'),
    'version'     => '0.6-dev',
    'author'      => 'Olivier Meunier',
    'requires'    => [
        'core' => '3.0-dev',
    ],
];
