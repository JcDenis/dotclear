<?php
/**
 * @class Dotclear\Network\Http\Client
 *
 * @package Dotclear
 * @subpackage Network
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Client extends Http
{
    public function getError()
    {
    }
}
