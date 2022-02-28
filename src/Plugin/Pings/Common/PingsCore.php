<?php
/**
 * @class Dotclear\Plugin\Pings\Common\PingsCore
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginPings
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pings\Common;

use Dotclear\Plugin\Pings\Common\PingsAPI;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class PingsCore
{
    public static function initPings()
    {
        dotclear()->behavior()->add('coreFirstPublicationEntries', [__CLASS__, 'doPings']);
    }

    public static function doPings($blog, $ids)
    {
        if (!dotclear()->blog()->settings()->pings->pings_active) {
            return;
        }
        if (!dotclear()->blog()->settings()->pings->pings_auto) {
            return;
        }

        $pings_uris = dotclear()->blog()->settings()->pings->pings_uris;
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        foreach ($pings_uris as $uri) {
            try {
                PingsAPI::doPings($uri, dotclear()->blog()->name, $dotclear()->blog()->url);
            } catch (\Exception $e) {
            }
        }
    }
}
