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
 * Multicall XML-RPC Client.
 *
 * \Dotclear\Helper\Network\Xmlrpc\ClientMulticall
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * Multicall XML-RPC Client
 *
 * This class library is fully based on Simon Willison's IXR library (http://scripts.incutio.com/xmlrpc/).
 *
 * Multicall client using system.multicall method of server.
 *
 * @ingroup  Helper Network Xmlrpc
 */
class ClientMulticall extends Client
{
    protected $calls = []; // /< array

    /**
     * Add call to stack.
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
     */
    public function addCall(string $method, mixed ...$args): void
    {
        $struct = [
            'methodName' => $method,
            'params'     => $args,
        ];

        $this->calls[] = $struct;
    }

    /**
     * XML-RPC Query.
     *
     * This method sends calls stack to XML-RPC system.multicall method.
     * See {@link Server::multiCall()} for details and links about it.
     *
     * @param string $method (not used, use ::addCall() before invoking ::query())
     * @param mixed  $args   (see above)
     *
     * @return mixed (array)
     */
    public function query(string $method = null, mixed ...$args): mixed
    {
        // Prepare multicall, then call the parent::query() method
        return parent::query('system.multicall', $this->calls);
    }
}
