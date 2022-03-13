<?php
/**
 * @class Dotclear\Network\Xmlrpc\Client
 * @brief XML-RPC Client
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * XML-RPC Client
 *
 * This class library is fully based on Simon Willison's IXR library (http://scripts.incutio.com/xmlrpc/).
 *
 * Basic XML-RPC Client.
 *
 * @package Dotclear
 * @subpackage Network
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Network\Xmlrpc;

use Dotclear\Exception\NetworkException;

use Dotclear\Network\Xmlrpc\XmlrpcException;
use Dotclear\Network\Xmlrpc\Message;
use Dotclear\Network\Xmlrpc\Request;

use Dotclear\Network\NetHttp\NetHttp;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Client extends NetHttp
{
    protected $request; ///< Request XML-RPC Request object
    protected $message; ///< Message XML-RPC Message object

    /**
     * Constructor
     *
     * Creates a new instance. <var>$url</var> is the XML-RPC Server end point.
     *
     * @param string        $url            Service URL
     */
    public function __construct($url)
    {
        if (!$this->readUrl($url, $ssl, $host, $port, $path, $user, $pass)) {
            return;
        }

        parent::__construct($host, $port);
        $this->useSSL($ssl);
        $this->setAuthorization($user, $pass);

        $this->path       = $path;
        $this->user_agent = 'Clearbricks XML/RPC Client';
    }

    /**
     * XML-RPC Query
     *
     * This method calls the given query (first argument) on XML-RPC Server.
     * All other arguments of this method are XML-RPC method arguments.
     * This method throws an exception if XML-RPC method returns an error or
     * returns the server's response.
     *
     * Example:
     * <code>
     * <?php
     * $o = new Client('http://example.com/xmlrpc');
     * $r = $o->query('method1','hello','world');
     * ?>
     * </code>
     *
     * @param string    $method
     * @param mixed     $args
     *
     * @return mixed
     */
    public function query($method, ...$args)
    {
        $this->request = new Request($method, $args);

        $this->doRequest();

        if ($this->status != 200) {
            throw new NetworkException('HTTP Error. ' . $this->status . ' ' . $this->status_string);
        }

        # Now parse what we've got back
        $this->message = new Message($this->content);
        $this->message->parse();

        # Is the message a fault?
        if ($this->message->messageType == 'fault') {
            throw new XmlrpcException($this->message->faultString, $this->message->faultCode);
        }

        return $this->message->params[0];
    }

    # Overloading Http::buildRequest method, we don't need all the stuff of
    # HTTP client.
    protected function buildRequest()
    {
        if ($this->proxy_host) {
            $path = $this->getRequestURL();
        } else {
            $path = $this->path;
        }

        return [
            'POST ' . $path . ' HTTP/1.0',
            'Host: ' . $this->host,
            'Content-Type: text/xml',
            'User-Agent: ' . $this->user_agent,
            'Content-Length: ' . $this->request->getLength(),
            '',
            $this->request->getXML(),
        ];
    }
}
