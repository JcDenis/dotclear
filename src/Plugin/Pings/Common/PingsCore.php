<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pings\Common;

// Dotclear\Plugin\Pings\Common\PingsCore

/**
 * Core methods for plugin Pings.
 *
 * @ingroup  Plugin Pings
 */
class PingsCore
{
    public function __construct()
    {
        dotclear()->behavior()->add('coreFirstPublicationEntries', [$this, 'doPings']);
    }

    public function doPings($posts, $ids): void
    {
        if (!dotclear()->blog()->settings()->get('pings')->get('pings_active')) {
            return;
        }
        if (!dotclear()->blog()->settings()->get('pings')->get('pings_auto')) {
            return;
        }

        $pings_uris = dotclear()->blog()->settings()->get('pings')->get('pings_uris');
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        foreach ($pings_uris as $uri) {
            try {
                PingsAPI::doPings($uri, dotclear()->blog()->name, dotclear()->blog()->url);
            } catch (\Exception) {
            }
        }
    }
}
