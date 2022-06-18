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
use Dotclear\App;

/**
 * Core methods for plugin Pings.
 *
 * @ingroup  Plugin Pings
 */
class PingsCore
{
    public function __construct()
    {
        App::core()->behavior('coreAfterFirstPublicationPosts')->add([$this, 'doPings']);
    }

    public function doPings($ids): void
    {
        if (!App::core()->blog()->settings()->getGroup('pings')->getSetting('pings_active')) {
            return;
        }
        if (!App::core()->blog()->settings()->getGroup('pings')->getSetting('pings_auto')) {
            return;
        }

        $pings_uris = App::core()->blog()->settings()->getGroup('pings')->getSetting('pings_uris');
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        foreach ($pings_uris as $uri) {
            try {
                PingsAPI::doPings($uri, App::core()->blog()->name, App::core()->blog()->url);
            } catch (\Exception) {
            }
        }
    }
}
