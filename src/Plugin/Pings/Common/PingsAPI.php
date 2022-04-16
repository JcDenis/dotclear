<?php
/**
 * @class Dotclear\Plugin\Pings\Common\PingsAPI
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

use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Network\Xmlrpc\Client;

class PingsAPI extends Client
{
    public static function doPings(string $srv_uri, string $site_name, string $site_url): bool
    {
        $o          = new self($srv_uri);
        $o->timeout = 3;

        $rsp = $o->query('weblogUpdates.ping', $site_name, $site_url);

        if (isset($rsp['flerror']) && $rsp['flerror']) {
            throw new ModuleException($rsp['message']);
        }

        return true;
    }
}
