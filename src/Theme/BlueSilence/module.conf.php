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
    'name'       => __('Dotclear Theme'),
    'description'=> __('Default Dotclear theme, fully customizable'),
    'version'    => '1.1-dev',
    'author'     => 'Marco / marcarea.com',
    'type'       => 'Theme',
    'templateset'=> 'mustek',
    'requires'   => [
        'core' => '3.0-dev',
    ],
];
