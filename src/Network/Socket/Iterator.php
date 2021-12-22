<?php
/**
 * @class Dotclear\Network\Socket\Socket\Iterator
 * @brief Network socket iterator
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * This class offers an iterator for network operations made with
 * {@link Dotclear\Network\Socket\Socket}.
 *
 * @see Dotclear\Network\Socket\Socket::write()
 *
 * @package Dotclear
 * @subpackage Network
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Network\Socket;

use Dotclear\Exception;
use Dotclear\Exception\NetworkException;

class Iterator implements \Iterator
{
    protected $_handle; ///< resource: Socket resource handler
    protected $_index;  ///< integer: Current index position

    /**
     * Constructor
     *
     * @param resource    $handle        Socket resource handler
     */
    public function __construct(&$handle)
    {
        if (!is_resource($handle)) {
            throw new NetworkException('Handle is not a resource');
        }
        $this->_handle = &$handle;
        $this->_index  = 0;
    }

    /* Iterator methods
    --------------------------------------------------- */
    /**
     * Rewind
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        # Nothing
    }

    /**
     * Valid
     *
     * @return boolean    True if EOF of handler
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return !feof($this->_handle);
    }

    /**
     * Move index forward
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->_index++;
    }

    /**
     * Current index
     *
     * @return integer    Current index
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->_index;
    }

    /**
     * Current value
     *
     * @return string    Current socket response line
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return fgets($this->_handle, 4096);
    }
}
