<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Socket;

use Dotclear\Exception\NetworkException;

/**
 * Network base.
 *
 * \Dotclear\Helper\Network\Socket\Socket
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * This class handles network socket through an iterator.
 *
 * @ingroup  Helper Network
 */
class Socket
{
    protected $_transport = ''; // /< string: Server transport
    protected $_handle;         // /< resource: Resource handler

    /**
     * Class constructor.
     *
     * @param string $_host    Server host
     * @param int    $_port    Server port
     * @param int    $_timeout Connection timeout
     */
    public function __construct(protected string $_host, protected int $_port, protected int $_timeout = 10)
    {
        $this->_port    = abs($this->_port);
        $this->_timeout = abs($this->_timeout);
    }

    /**
     * Object destructor.
     *
     * Calls {@link close()} method
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Get / Set host.
     *
     * If <var>$host</var> is set, set {@link $_host} and returns true.
     * Otherwise, returns {@link $_host} value.
     *
     * @param null|string $host Server host
     */
    public function host(?string $host = null): string|bool
    {
        if ($host) {
            $this->_host = $host;

            return true;
        }

        return $this->_host;
    }

    /**
     * Get / Set port.
     *
     * If <var>$port</var> is set, set {@link $_port} and returns true.
     * Otherwise, returns {@link $_port} value.
     *
     * @param null|int $port Server port
     *
     * @return int|true
     */
    public function port(?int $port = null): int|bool
    {
        if ($port) {
            $this->_port = abs((int) $port);

            return true;
        }

        return $this->_port;
    }

    /**
     * Get / Set timeout.
     *
     * If <var>$timeout</var> is set, set {@link $_timeout} and returns true.
     * Otherwise, returns {@link $_timeout} value.
     *
     * @param null|int $timeout Connection timeout
     */
    public function timeout(?int $timeout = null): int|bool
    {
        if ($timeout) {
            $this->_timeout = abs((int) $timeout);

            return true;
        }

        return $this->_timeout;
    }

    /**
     * Set blocking.
     *
     * Sets blocking or non-blocking mode on the socket.
     *
     * @param int $i 1 for yes, 0 for no
     */
    public function setBlocking(int $i): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        return stream_set_blocking($this->_handle, (bool) $i);
    }

    /**
     * Open connection.
     *
     * Opens socket connection and Returns an object of type {@link Iterator}
     * which can be iterate with a simple foreach loop.
     */
    public function open(): Iterator|bool
    {
        $handle = @fsockopen($this->_transport . $this->_host, $this->_port, $errno, $errstr, $this->_timeout);
        if (!$handle) {
            throw new NetworkException('Socket error: ' . $errstr . ' (' . $errno . ')');
        }
        $this->_handle = $handle;

        return $this->iterator();
    }

    /**
     * Closes socket connection.
     */
    public function close(): void
    {
        if ($this->isOpen()) {
            fclose($this->_handle);
            $this->_handle = null;
        }
    }

    /**
     * Send data.
     *
     * Sends data to current socket and returns an object of type
     * {@link netSocketIterator} which can be iterate with a simple foreach loop.
     *
     * <var>$data</var> can be a string or an array of lines.
     *
     * Example:
     *
     * <code>
     * <?php
     * $s = new netSocket('www.google.com',80,2);
     * $s->open();
     * $data = [
     *     'GET / HTTP/1.0'
     * ];
     * foreach($s->write($data) as $v) {
     *     echo $v."\n";
     * }
     * $s->close();
     * ?>
     * </code>
     *
     * @param array|string $data Data to send
     */
    public function write(array|string $data): Iterator|false
    {
        if (!$this->isOpen()) {
            return false;
        }

        if (is_array($data)) {
            $data = implode("\r\n", $data) . "\r\n\r\n";
        }

        fwrite($this->_handle, $data);

        return $this->iterator();
    }

    /**
     * Flush buffer.
     *
     * Flushes socket write buffer.
     */
    public function flush(): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        fflush($this->_handle);

        return true;
    }

    /**
     * Iterator.
     */
    protected function iterator(): Iterator|false
    {
        if (!$this->isOpen()) {
            return false;
        }

        return new Iterator($this->_handle);
    }

    /**
     * Is open.
     *
     * Returns true if socket connection is open.
     */
    public function isOpen(): bool
    {
        return is_resource($this->_handle);
    }
}
