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
    'name'       => __('Ductile'),
    'description'=> __('Mediaqueries compliant elegant theme'),
    'version'    => '1.6-dev',
    'author'     => 'Dotclear team',
    'type'       => 'Theme',
    'templateset'=> 'mustek',
    'requires'   => [
        'core' => '3.0-dev',
    ],
];
