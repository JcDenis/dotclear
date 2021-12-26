<?php
/**
 * @class Dotclear\Network\Xmlrpc\Request
 * @brief XML-RPC Request
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

use Dotclear\Network\Xmlrpc\Value;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Request
{
    public $method; ///< string Request method name
    public $args;   ///< array Request method arguments
    public $xml;    ///< string Request XML string

    /**
     * Constructor
     *
     * @param string    $method        Method name
     * @param array        $args        Method arguments
     */
    public function __construct($method, $args)
    {
        $this->method = $method;
        $this->args   = $args;

        $this->xml = '<?xml version="1.0"?>' . "\n" .
        "<methodCall>\n" .
        '  <methodName>' . $this->method . "</methodName>\n" .
            "  <params>\n";

        foreach ($this->args as $arg) {
            $this->xml .= '    <param><value>';
            $v = new Value($arg);
            $this->xml .= $v->getXml();
            $this->xml .= "</value></param>\n";
        }

        $this->xml .= '  </params></methodCall>';
    }

    /**
     * Request length
     *
     * Returns {@link $xml} content length.
     *
     * @return integer
     */
    public function getLength()
    {
        return strlen($this->xml);
    }

    /**
     * Request XML
     *
     * Returns request XML version.
     *
     * @return string
     */
    public function getXml()
    {
        return $this->xml;
    }
}
