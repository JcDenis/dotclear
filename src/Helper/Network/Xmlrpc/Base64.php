<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Xmlrpc;

/**
 * XML-RPC Base 64 object.
 *
 * \Dotclear\Helper\Network\Xmlrpc\Base64
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Network Xmlrpc
 */
class Base64
{
    /**
     * Constructor.
     *
     * Create a new instance of xmlrpcBase64.
     *
     * @param string $data Data
     */
    public function __construct(protected string $data)
    {
    }

    /**
     * XML Data.
     *
     * Returns the XML fragment for XML-RPC message inclusion.
     */
    public function getXml(): string
    {
        return '<base64>' . base64_encode($this->data) . '</base64>';
    }
}
