<?php
/**
 * @class Dotclear\Network\Xmlrpc\Base64
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

namespace Dotclear\Network\Xmlrpc;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Base64
{
    protected $data; ///< string

    /**
     * Constructor
     *
     * Create a new instance of xmlrpcBase64.
     *
     * @param string        $data        Data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * XML Data
     *
     * Returns the XML fragment for XML-RPC message inclusion.
     *
     * @return string
     */
    public function getXml()
    {
        return '<base64>' . base64_encode($this->data) . '</base64>';
    }
}
