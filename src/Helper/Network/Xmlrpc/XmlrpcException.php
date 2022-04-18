<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Xmlrpc;

use Exception;

/**
 * XML-RPC Exception.
 *
 * \Dotclear\Helper\Network\Xmlrpc\XmlrpcException
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Network Xmlrpc Exception
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
class XmlrpcException extends Exception
{
    /**
     * @param string $message Exception message
     * @param int    $code    Exception code
     */
    public function __construct(string $message, int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
