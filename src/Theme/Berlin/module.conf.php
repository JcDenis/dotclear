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
    'name'       => __('Berlin'),
    'description'=> __('Dotclear 2.7+ default theme'),
    'version'    => '1.5-dev',
    'author'     => 'Dotclear team',
    'type'       => 'Theme',
    'templateset'=> 'dotty',
    'requires'   => [
        'core' => '3.0-dev',
    ],
];
