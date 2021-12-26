<?php
/**
 * @class Dotclear\Network\Xmlrpc\ClientMulticall
 * @brief Multicall XML-RPC Client
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * Multicall XML-RPC Client
 *
 * This class library is fully based on Simon Willison's IXR library (http://scripts.incutio.com/xmlrpc/).
 *
 * Multicall client using system.multicall method of server.
 *
 * @package Dotclear
 * @subpackage Network
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Network\Xmlrpc;

use Dotclear\Network\Xmlrpc\Client;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class ClientMulticall extends Client
{
    protected $calls = []; ///< array

    public function __construct($url)
    {
        parent::__construct($url);
    }

    /**
     * Add call to stack
     *
     * This method adds a method call for the given query (first argument) to
     * calls stack.
     * All other arguments of this method are XML-RPC method arguments.
     *
     * Example:
     * <code>
     * <?php
     * $o = new Client('http://example.com/xmlrpc');
     * $o->addCall('method1','hello','world');
     * $o->addCall('method2','foo','bar');
     * $r = $o->query();
     * ?>
     * </code>
     *
     * @param string    $method
     * @param mixed     $args
     *
     * @return mixed
     */
    public function addCall($method, ...$args)
    {
        $struct = [
            'methodName' => $method,
            'params'     => $args,
        ];

        $this->calls[] = $struct;
    }

    /**
     * XML-RPC Query
     *
     * This method sends calls stack to XML-RPC system.multicall method.
     * See {@link Server::multiCall()} for details and links about it.
     *
     * @param string    $method (not used, use ::addCall() before invoking ::query())
     * @param mixed     $args (see above)
     *
     * @return array
     */
    public function query($method = null, ...$args)
    {
        # Prepare multicall, then call the parent::query() method
        return parent::query('system.multicall', $this->calls);
    }
}
