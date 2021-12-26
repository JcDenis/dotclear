<?php
/**
 * @class Dotclear\Network\Xmlrpc\XmlrpcException
 * @brief XML-RPC Exception
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

namespace Dotclear\Network\Xmlrpc;

use Dotclear\Exception;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class XmlrpcException extends Exception
{
    /**
     * @param string    $message        Exception message
     * @param integer    $code        Exception code
     */
    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }
}
