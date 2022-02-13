<?php
/**
 * @class Dotclear\Core\Instance\Configuration
 * @brief Dotclear configuration stacker
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Exception\UtilsException;

class Configuration
{
    /** @var array Stack */
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
     * The new value type must match default value.
     *
     * @param   array   $default    The default values
     * @param   array   $new        The new value
     */
    public function __construct(array $default, string|array $new = [])
    {
        if (is_string($new)) {
            $new = $this->fromFile($new);
        }

        foreach($default as $k => $v) {
            # Read only (or from scratch)
            if ($v[0] === false || empty($new)) {
                $this->stack[$k] = $v[1];
            # Required
            } elseif ($v[0] === true && !isset($new[$k])) {
                throw new UtilsException('Configuration file is not complete.');
            # Type
            } else {
                $this->stack[$k] = isset($new[$k]) && gettype($v[1]) == gettype($new[$k]) ? $new[$k] : $v[1];
            }
        }
    }

    public function __get($k)
    {
        return array_key_exists($k, $this->stack) ? $this->stack[$k] : null;
    }

    public function __isset($k)
    {
        return isset($this->stack[$k]);
    }

    public function __set($k, $v)
    {
        throw new UtilsException('Configuration properties are read only.');
    }

    private function fromFile(string $file): array
    {
        ob_start();
        $new = is_file($file) ? require_once $file : null;
        ob_end_clean();

        if (!is_array($new)) {
            throw new UtilsException('Configuration file not found.');
        }

        return $new;
    }
}
