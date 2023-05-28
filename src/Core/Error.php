<?php
/**
 * @brief Error core class
 *
 * dcError is a very simple error class, with a stack. Call dcError::add to
 * add an error in stack. In administration area, errors are automatically
 * displayed.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use dcAdminNotices;

class Error
{
    /** @var    array<int,string>   Errors stack */
    protected $stack = [];

    /** @var    bool    True if stack is not empty */
    protected $flag = false;

    /**
     * Adds an error to stack.
     *
     * @param   string  $msg    Error message
     */
    public function add(string $msg): void
    {
        $this->flag     = true;
        $this->stack[] = $msg;
    }

    /**
     * Returns the value of <var>flag</var> property. 
     *
     * True if errors stack is not empty
     *
     * @return  bool
     */
    public function flag(): bool
    {
        return $this->flag;
    }

    /**
     * Resets errors stack.
     */
    private function reset(): void
    {
        $this->flag   = false;
        $this->stack = [];
    }

    /**
     * Return number of stacked errors.
     *
     * @return  int
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Returns errors stack as HTML and reset it.
     *
     * @return  string
     */
    public function toHTML(): string
    {
        $res = '';

        if ($this->flag) {
            foreach ($this->stack as $msg) {
                $res .= dcAdminNotices::error($msg, true, false, false);
            }
            $this->reset();
        }

        return $res;
    }
}
