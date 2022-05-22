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
use ArrayObject;
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
        App::core()->behavior()->add('coreFirstPublicationEntries', [$this, 'doPings']);
    }

    public function doPings(ArrayObject $ids): void
    {
        if (!App::core()->blog()->settings()->get('pings')->get('pings_active')) {
            return;
        }
        if (!App::core()->blog()->settings()->get('pings')->get('pings_auto')) {
            return;
        }

        $pings_uris = App::core()->blog()->settings()->get('pings')->get('pings_uris');
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
