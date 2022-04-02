<?php
/**
 * @class Dotclear\Helper\Network\Xmlrpc\Request
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

namespace Dotclear\Helper\Network\Xmlrpc;

use Dotclear\Helper\Network\Xmlrpc\Value;

class Request
{
    public $xml;    ///< string Request XML string

    /**
     * Constructor
     *
     * @param   string  $method     Method name
     * @param   array   $args       Method arguments
     */
    public function __construct(public string $method, public array $args)
    {
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
     * @return  int
     */
    public function getLength(): int
    {
        return strlen($this->xml);
    }

    /**
     * Request XML
     *
     * Returns request XML version.
     *
     * @return  string
     */
    public function getXml(): string
    {
        return $this->xml;
    }
}
