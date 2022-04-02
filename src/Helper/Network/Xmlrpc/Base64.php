<?php
/**
 * @class Dotclear\Helper\Network\Xmlrpc\Base64
 * @brief XML-RPC Base 64 object
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

class Base64
{
    /**
     * Constructor
     *
     * Create a new instance of xmlrpcBase64.
     *
     * @param   string  $data   Data
     */
    public function __construct(protected string $data)
    {
    }

    /**
     * XML Data
     *
     * Returns the XML fragment for XML-RPC message inclusion.
     *
     * @return  string
     */
    public function getXml(): string
    {
        return '<base64>' . base64_encode($this->data) . '</base64>';
    }
}
