<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

// Dotclear\Process\Public\Template\TplAttr

/**
 * Template attributes.
 * 
 * Key and value of attributes must be of type string.
 *
 * @ingroup  Template Stack
 */
class TplAttr
{
    /**
     * @var array<string,string> $stack
     *                           The attributes stack
     */
    protected $stack = [];

    /**
     * Constructor.
     *
     * @param string $str The default attributes to parse
     */
    public function __construct(string $str = '')
    {
        if (preg_match_all('|([a-zA-Z0-9_:-]+)="([^"]*)"|ms', $str, $m) > 0) {
            foreach ($m[1] as $i => $v) {
                $this->set($v, $m[2][$i]);
            }
        }
    }

    /**
     * Get an attribute.
     *
     * @param string $key The attribute name
     *
     * @return string The attribute value
     */
    public function get(string $key): string
    {
        return $this->stack[$key] ?? '';
    }

    /**
     * Set an attribute.
     *
     * @param string $key   The attribute name
     * @param string $value The attribute value
     */
    public function set(string $key, string $value): void
    {
        $this->stack[$key] = $value;
    }

    /**
     * Check if a attribte is set.
     *
     * @param string $key The attribute name
     *
     * @return bool true if attribute exists
     */
    public function isset(string $key): bool
    {
        return isset($this->stack[$key]);
    }

    /**
     * Check if a attribte is empty.
     *
     * @param string $key The attribute name
     *
     * @return bool true if attribute exists
     */
    public function empty(string $key): bool
    {
        return !isset($this->stack[$key]) || empty(trim($this->stack[$key]));
    }

    /**
     * Get all attributes.
     *
     * @return array<string,string> The attributes
     */
    public function dump(): array
    {
        return $this->stack;
    }
}
