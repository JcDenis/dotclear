<?php
/**
 * @class Dotclear\Plugin\Pings\Lib\PingsCore
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginPings
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pings\Lib;

use Dotclear\Plugin\Pings\Lib\PingsAPI;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class PingsCore
{
    public static function doPings($blog, $ids)
    {
        if (!$blog->settings()->pings->pings_active) {
            return;
        }
        if (!$blog->settings()->pings->pings_auto) {
            return;
        }

        $pings_uris = $blog->settings()->pings->pings_uris;
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        foreach ($pings_uris as $uri) {
            try {
                PingsAPI::doPings($uri, $blog->name, $blog->url);
            } catch (\Exception $e) {
            }
        }
    }
}
