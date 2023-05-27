<?php
/**
 * @brief pages, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\pages;

use dcCore;
use dcNsProcess;

class Prepend extends dcNsProcess
{
    public static function init(): bool
    {
        return (static::$init = My::checkContext(My::PREPEND));
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        dcCore::app()->url->register('pages', 'pages', '^pages/(.+)$', [FrontendUrl::class, 'pages']);
        dcCore::app()->url->register('pagespreview', 'pagespreview', '^pagespreview/(.+)$', [FrontendUrl::class, 'pagespreview']);

        dcCore::app()->post_type->set('page', 'plugin.php?p=pages&act=page&id=%d', dcCore::app()->url->getURLFor('pages', '%s'), 'Pages');

        return true;
    }
}
