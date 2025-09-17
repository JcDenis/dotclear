<?php

/**
 * @file
 * @brief       The plugin dcProxyV2 functions aliases
 * @ingroup     dcProxyV2
 *
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */

use Dotclear\App;
use Dotclear\Core\Backend\Helper;
use Dotclear\Core\Fault;
use Dotclear\Exception\AppException;

/**
 * Load locales.
 *
 * @deprecated  since 2.21, use Dotclear::Core::Backend::Helper::loadLocales() instead
 */
function dc_load_locales(): void
{
    Helper::loadLocales();
}

/**
 * Get icon URL.
 *
 * @deprecated  since 2.21
 *
 * @param   string  $img    The image
 */
function dc_admin_icon_url(string $img): string
{
    return $img;
}

/**
 * Compose HTML icon markup for favorites, menu, … depending on theme (light, dark).
 *
 * @deprecated  since 2.21, use Dotclear::Core::Backend::Helper::adminIcon() instead
 *
 * @param   mixed   $img        string (default) or array (0 : light, 1 : dark)
 * @param   bool    $fallback   use fallback image if none given
 * @param   string  $alt        alt attribute
 * @param   string  $title      title attribute
 * @param   string  $class      class attribute
 */
function dc_admin_icon_theme($img, bool $fallback = true, string $alt = '', string $title = '', string $class = ''): string
{
    return Helper::adminIcon($img, $fallback, $alt, $title, $class);
}

/**
 * Adds a menu item.
 *
 * @deprecated  since 2.21, use App::backend()->menus()->addItem() instead
 *
 * @param   string  $section    The section
 * @param   string  $desc       The description
 * @param   string  $adminurl   The adminurl
 * @param   mixed   $icon       The icon(s)
 * @param   mixed   $perm       The permission
 * @param   bool    $pinned     The pinned
 * @param   bool    $strict     The strict
 */
function addMenuItem(string $section, string $desc, string $adminurl, $icon, $perm, bool $pinned = false, bool $strict = false): void
{
    App::backend()->menus()->addItem($section, $desc, $adminurl, $icon, $perm, $pinned, $strict);
}

/**
 * @brief   Error handling function.
 *
 * @deprecated  since 2.27, use class Dotclear::Fault instead
 *
 * @param   string  $summary    The summary
 * @param   string  $message    The message
 * @param   int     $code   The code
 */
function __error(string $summary, string $message, int $code = 0): void
{
    throw (new Fault())->exception($summary, $code, new AppException($message, $code));
}
