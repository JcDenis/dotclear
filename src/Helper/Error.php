<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\Error

/**
 * Error stack.
 *
 * @ingroup  Helper Error Stack
 */
class Error
{
    /**
     * @var array $errors
     *            Errors stack
     */
    protected $errors = [];

    /**
     * @var bool $flag
     *           True if stack is not empty
     */
    protected $flag = false;

    /**
     * @var string $html_list
     *             HTML errors list pattern
     */
    protected $html_list = "<ul>\n%s</ul>\n";

    /**
     * @var string $html_item
     *             HTML error item pattern
     */
    protected $html_item = "<li>%s</li>\n";

    /**
     * @var string $html_single
     *             HTML error single pattern
     */
    protected $html_single = "<p>%s</p>\n";

    /**
     * Object string representation.
     *
     * @return string the errors stack
     */
    public function __toString(): string
    {
        $res = '';

        foreach ($this->errors as $msg) {
            $res .= $msg . "\n";
        }

        return $res;
    }

    /**
     * Adds an error to stack.
     *
     * @param string ...$msgs Error message
     */
    public function add(string ...$msgs): void
    {
        $this->flag = true;
        foreach ($msgs as $msg) {
            $this->errors[] = (string) $msg;
        }
    }

    /**
     * Returns the value of <var>flag</var> property. True if errors stack is not empty.
     */
    public function flag(): bool
    {
        return $this->flag;
    }

    /**
     * Resets errors stack.
     */
    public function reset(): void
    {
        $this->flag   = false;
        $this->errors = [];
    }

    /**
     * Returns <var>errors</var> property.
     */
    public function dump(): array
    {
        return $this->errors;
    }

    /**
     * Sets <var>list</var> and <var>item</var> properties.
     *
     * @param string      $list   HTML errors list pattern
     * @param string      $item   HTML error item pattern
     * @param null|string $single HTML single item pattern
     */
    public function setHTMLFormat(string $list, string $item, ?string $single = null): void
    {
        $this->html_list = $list;
        $this->html_item = $item;
        if ($single) {
            $this->html_single = $single;
        }
    }

    /**
     * Returns errors stack as HTML.
     */
    public function toHTML(): string
    {
        $res = '';

        if ($this->flag) {
            if (count($this->errors) == 1) {
                $res = sprintf($this->html_single, $this->errors[0]);
            } else {
                foreach ($this->errors as $msg) {
                    $res .= sprintf($this->html_item, $msg);
                }
                $res = sprintf($this->html_list, $res);
            }
        }

        return $res;
    }
}
