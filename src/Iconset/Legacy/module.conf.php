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
    'name'       => __('Legacy'),
    'description'=> __('Original Dotclear 2.0 icons'),
    'version'    => '0.2-dev',
    'author'     => 'Dotclear team',
    'type'       => 'Iconset',
    'requires'   => [
        'core'=> '3.0-dev',
    ],
];
