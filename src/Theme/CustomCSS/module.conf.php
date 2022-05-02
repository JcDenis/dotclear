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
    'name'       => __('Custom theme'),
    'description'=> __('A CSS customizable theme'),
    'version'    => '1.3-dev',
    'author'     => 'Olivier Meunier',
    'type'       => 'Theme',
    'templateset'=> 'mustek',
    'requires'   => [
        'core' => '3.0-dev',
    ],
];
