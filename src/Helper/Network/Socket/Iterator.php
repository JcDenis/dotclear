<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Socket;

// Dotclear\Helper\Network\Socket\Socket\Iterator
use Dotclear\Exception\NetworkException;
use ReturnTypeWillChange;

/**
 * Network socket iterator.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * This class offers an iterator for network operations made with
 * {@link Socket}.
 *
 * @see Socket::write()
 *
 * @ingroup  Helper Network
 */
class Iterator implements \Iterator
{
    protected $_handle; // /< resource: Socket resource handler
    protected $_index;  // /< integer: Current index position

    /**
     * Constructor.
     *
     * @param resource $handle Socket resource handler
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
     * Rewind.
     */
    #[ReturnTypeWillChange]
    public function rewind()
    {
        // Nothing
    }

    /**
     * Valid.
     *
     * @return bool True if EOF of handler
     */
    #[ReturnTypeWillChange]
    public function valid()
    {
        return !feof($this->_handle);
    }

    /**
     * Move index forward.
     */
    #[ReturnTypeWillChange]
    public function next()
    {
        ++$this->_index;
    }

    /**
     * Current index.
     *
     * @return int Current index
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        return $this->_index;
    }

    /**
     * Current value.
     *
     * @return string Current socket response line
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        return fgets($this->_handle, 4096);
    }
}
