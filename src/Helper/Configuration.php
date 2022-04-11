<?php
/**
 * @class Dotclear\Helper\Configuration
 * @brief Dotclear configuration stacker
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

use Dotclear\Exception\UtilsException;
use Dotclear\Helper\ErrorTrait;

class Configuration
{
    use ErrorTrait;

    /** @var    array   Read files */
    protected static $file = [];

    /** @var    array   Stack */
    private $stack = [];

    /**
     * Constructor
     *
     * $default = [
     *     key => [state, value],
     *     ...
     * ]
     * with state like
     * * false = read only,
     * * true  = required,
     * * null  = could be override
     *
     * $new = [
     *     key => value,
     *     ...
     * ]
     * The new value type must match default value type.
     *
     * @param   array           $default    The default values
     * @param   array|string    $path       The new values or config file path
     */
    public function __construct(array $default, array|string $path = [])
    {
        $new = is_string($path) ? $this->fromFile($path) : $path;

        foreach($default as $k => $v) {
            # Read only (or from scratch)
            if (false === $v[0] || empty($new)) {
                $this->stack[$k] = $v[1];
            # Required
            } elseif (true === $v[0] && !isset($new[$k])) {
                $this->error()->add(sprintf(
                    'Required configuration key "%s" is not set', $k
                ));
            # Type
            } else {
                $this->stack[$k] = isset($new[$k]) && gettype($v[1]) == gettype($new[$k]) ? $new[$k] : $v[1];
            }
        }
    }

    /**
     * Get a configuration value
     * 
     * @param   string  $key    The key
     * 
     * @return  mixed           The value
     */
    public function get(string $key): mixed
    {
        return array_key_exists($key, $this->stack) ? $this->stack[$key] : null; 
    }

    /**
     * Check if a key exists
     * 
     * @param   string  $key    The key
     * 
     * @return  bool            True if it exists
     */
    public function exists(string $key): bool
    {
        return isset($this->stack[$key]);
    }

    /**
     * Read configuration from a file
     */
    private function fromFile(string $file): array
    {
        $new = [];
        # Do not require twice the same file (prevent loop)
        if (!isset(static::$file[$file])) {
            static::$file[$file] = true;
            ob_start();
            $new = is_file($file) ? require_once $file : null;
            ob_end_clean();
        }

        if (!is_array($new)) {
            throw new UtilsException('Configuration file not found.');
        }

        return $new;
    }

    /**
     * Dump configuration
     * 
     * @return  array
     */
    public function dump(): array
    {
        return $this->stack;
    }
}
