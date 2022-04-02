<?php
/**
 * @class Dotclear\Helper\Network\Xmlrpc\XmlrpcException
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

namespace Dotclear\Helper\Network\Xmlrpc;

class XmlrpcException extends \Exception
{
    /**
     * @param   string  $message    Exception message
     * @param   int     $code       Exception code
     */
    public function __construct(string $message, int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
