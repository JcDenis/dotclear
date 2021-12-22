<?php
/**
 * @class Dotclear\Network\NetHttp\Client
 * @brief Http client
 * @see Dotclear\Network\NetHttp\NetHttp
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage Network
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Network\NetHttp;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Client extends NetHttp
{
    public function getError()
    {
    }
}
