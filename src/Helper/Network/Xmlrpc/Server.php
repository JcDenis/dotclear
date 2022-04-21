<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Xmlrpc;

// Dotclear\Helper\Network\Xmlrpc\Server
use Dotclear\Exception\NetworkException;
use Exception;

/**
 * Basic XML-RPC Server.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * XML-RPC Server
 *
 * This class library is fully based on Simon Willison's IXR library (http://scripts.incutio.com/xmlrpc/).
 *
 * This is the most basic XML-RPC server you can create. Built-in methods are:
 *
 * - system.getCapabilities
 * - system.listMethods
 * - system.multicall
 *
 * @ingroup  Helper Network Xmlrpc
 */
class Server
{
    /**
     * @var array $callbacks
     *            Server methods
     */
    protected $callbacks = [];

    /**
     * @var string $data
     *             Received data
     * */
    protected $data;

    /**
     * @var Message $message
     *              Xmlrpc returned message
     */
    protected $message;

    /**
     * @var array $capabilities
     *            Server capabilities
     */
    protected $capabilities;

    /**
     * @var bool $strict_check
     *           Strict XML-RPC checks
     */
    public $strict_check = false;

    /**
     * Constructor.
     *
     * @param array|false $callbacks Server callbacks
     * @param mixed       $data      Server data
     * @param string      $encoding  Server encoding
     */
    public function __construct(array|false $callbacks = false, mixed $data = false, protected string $encoding = 'UTF-8')
    {
        $this->setCapabilities();
        if ($callbacks) {
            $this->callbacks = $callbacks;
        }
        $this->setCallbacks();
        $this->serve($data);
    }

    /**
     * Start XML-RPC Server.
     *
     * This method starts the XML-RPC Server. It could take a data argument
     * which should be a valid XML-RPC raw stream. If data is not specified, it
     * take values from raw POST data.
     *
     * @param mixed $data XML-RPC raw stream
     */
    public function serve(mixed $data = false): void
    {
        $result = null;
        if (!$data) {
            try {
                // Check HTTP Method
                if ('POST' != $_SERVER['REQUEST_METHOD']) {
                    throw new NetworkException('XML-RPC server accepts POST requests only.', 405);
                }

                // Check HTTP_HOST
                if (!isset($_SERVER['HTTP_HOST'])) {
                    throw new NetworkException('No Host Specified', 400);
                }

                global $HTTP_RAW_POST_DATA;
                if (!$HTTP_RAW_POST_DATA) {
                    $HTTP_RAW_POST_DATA = @file_get_contents('php://input');
                    if (!$HTTP_RAW_POST_DATA) {
                        throw new NetworkException('No Message', 400);
                    }
                }

                if ($this->strict_check) {
                    // Check USER_AGENT
                    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
                        throw new NetworkException('No User Agent Specified', 400);
                    }

                    // Check CONTENT_TYPE
                    if (!isset($_SERVER['CONTENT_TYPE']) || !str_starts_with($_SERVER['CONTENT_TYPE'], 'text/xml')) {
                        throw new NetworkException('Invalid Content-Type', 400);
                    }

                    // Check CONTENT_LENGTH
                    if (!isset($_SERVER['CONTENT_LENGTH']) || strlen($HTTP_RAW_POST_DATA) != $_SERVER['CONTENT_LENGTH']) {
                        throw new NetworkException('Invalid Content-Lenth', 400);
                    }
                }

                $data = $HTTP_RAW_POST_DATA;
            } catch (Exception $e) {
                if ($e->getCode() == 400) {
                    $this->head(400, 'Bad Request');
                } elseif ($e->getCode() == 405) {
                    $this->head(405, 'Method Not Allowed');
                    header('Allow: POST');
                }

                header('Content-Type: text/plain');
                echo $e->getMessage();

                exit;
            }
        }

        $this->message = new Message($data);

        try {
            $this->message->parse();

            if ('methodCall' != $this->message->messageType) {
                throw new XmlrpcException('Server error. Invalid xml-rpc. not conforming to spec. Request must be a methodCall', -32600);
            }

            $result = $this->call($this->message->methodName, $this->message->params);
        } catch (Exception $e) {
            $this->error($e);
        }

        // Encode the result
        $r         = new Value($result);
        $resultxml = $r->getXml();

        // Create the XML
        $xml = "<methodResponse>\n" .
            "<params>\n" .
            "<param>\n" .
            "  <value>\n" .
            '   ' . $resultxml . "\n" .
            "  </value>\n" .
            "</param>\n" .
            "</params>\n" .
            '</methodResponse>';

        // Send it
        $this->output($xml);
    }

    /**
     * Send HTTP Headers.
     *
     * This method sends a HTTP Header
     *
     * @param int    $code HTTP Status Code
     * @param string $msg  Header message
     */
    protected function head(int $code, string $msg): void
    {
        $status_mode = preg_match('/cgi/', PHP_SAPI);

        if ($status_mode) {
            header('Status: ' . $code . ' ' . $msg);
        } else {
            header($msg, true, $code);
        }
    }

    /**
     * Method call.
     *
     * This method calls the given XML-RPC method with arguments.
     *
     * @param string $methodname Method name
     * @param array  $args       Method arguments
     */
    protected function call(string $methodname, array $args): mixed
    {
        if (!$this->hasMethod($methodname)) {
            throw new XmlrpcException('server error. requested method "' . $methodname . '" does not exist.', -32601);
        }

        $method = $this->callbacks[$methodname];

        // Perform the callback and send the response
        if (!is_callable($method)) {
            throw new XmlrpcException('server error. internal requested function for "' . $methodname . '" does not exist.', -32601);
        }

        return call_user_func_array($method, $args);
    }

    /**
     * XML-RPC Error.
     *
     * This method create an XML-RPC error message from a PHP Exception object.
     * You should avoid using this in your own method and throw exceptions
     * instead.
     *
     * @param Exception $e Exception object
     */
    protected function error(Exception $e): void
    {
        $msg = $e->getMessage();

        $this->output(
            "<methodResponse>\n" .
            "  <fault>\n" .
            "    <value>\n" .
            "      <struct>\n" .
            "        <member>\n" .
            "          <name>faultCode</name>\n" .
            '          <value><int>' . $e->getCode() . "</int></value>\n" .
            "        </member>\n" .
            "        <member>\n" .
            "          <name>faultString</name>\n" .
            '          <value><string>' . $msg . "</string></value>\n" .
            "        </member>\n" .
            "      </struct>\n" .
            "    </value>\n" .
            "  </fault>\n" .
            "</methodResponse>\n"
        );
    }

    /**
     * Output response.
     *
     * This method sends the whole XML-RPC response through HTTP.
     *
     * @param string $xml XML Content
     */
    protected function output(string $xml): void
    {
        $xml    = '<?xml version="1.0" encoding="' . $this->encoding . '"?>' . "\n" . $xml;
        $length = strlen($xml);
        header('Connection: close');
        header('Content-Length: ' . $length);
        header('Content-Type: text/xml');
        header('Date: ' . date('r'));
        echo $xml;

        exit;
    }

    /**
     * XML-RPC Server has method?
     *
     * Returns true if the server has the given method <var>$method</var>
     *
     * @param string $method Method name
     */
    protected function hasMethod(string $method): bool
    {
        return in_array($method, array_keys($this->callbacks));
    }

    /**
     * Server Capabilities.
     *
     * This method initiates the server capabilities:
     * - xmlrpc
     * - faults_interop
     * - system.multicall
     */
    protected function setCapabilities(): void
    {
        // Initialises capabilities array
        $this->capabilities = [
            'xmlrpc' => [
                'specUrl'     => 'http://www.xmlrpc.com/spec',
                'specVersion' => 1,
            ],
            'faults_interop' => [
                'specUrl'     => 'http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php',
                'specVersion' => 20010516,
            ],
            'system.multicall' => [
                'specUrl'     => 'http://www.xmlrpc.com/discuss/msgReader$1208',
                'specVersion' => 1,
            ],
        ];
    }

    /**
     * Server Methods.
     *
     * This method creates the three main server's methods:
     * - system.getCapabilities
     * - system.listMethods
     * - system.multicall
     *
     * @see getCapabilities()
     * @see listMethods()
     * @see multiCall()
     */
    protected function setCallbacks(): void
    {
        $this->callbacks['system.getCapabilities'] = [$this, 'getCapabilities'];
        $this->callbacks['system.listMethods']     = [$this, 'listMethods'];
        $this->callbacks['system.multicall']       = [$this, 'multiCall'];
    }

    /**
     * Server Capabilities.
     *
     * Returns server capabilities
     */
    protected function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Server methods.
     *
     * Returns all server methods
     */
    protected function listMethods(): array
    {
        // Returns a list of methods - uses array_reverse to ensure user defined
        // methods are listed before server defined methods
        return array_reverse(array_keys($this->callbacks));
    }

    /**
     * Multicall.
     *
     * This method handles a multi-methods call
     *
     *  @see http://www.xmlrpc.com/discuss/msgReader$1208
     *
     * @param array $methodcalls Array of methods
     */
    protected function multiCall(array $methodcalls): array
    {
        $return = [];
        foreach ($methodcalls as $call) {
            $method = $call['methodName'];
            $params = $call['params'];

            try {
                if ('system.multicall' == $method) {
                    throw new XmlrpcException('Recursive calls to system.multicall are forbidden', -32600);
                }

                $result   = $this->call($method, $params);
                $return[] = [$result];
            } catch (Exception $e) {
                $return[] = [
                    'faultCode'   => $e->getCode(),
                    'faultString' => $e->getMessage(),
                ];
            }
        }

        return $return;
    }
}
