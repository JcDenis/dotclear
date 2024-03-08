<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Core\Backend\Notices; // deprecated
use Dotclear\Interface\Core\DeprecatedInterface;
use Dotclear\Interface\Core\ErrorInterface;

/**
 * @brief   Error handler.
 *
 * @since   2.28, container services have been added to constructor
 */
class Error implements ErrorInterface
{
    /**
     * Errors stack.
     *
     * @var     array<int,string>   $stack
     */
    protected $stack = [];

    /**
     * True if stack is not empty
     *
     * @var     bool    $flag
     */
    protected $flag = false;

    /**
     * Constructor.
     *
     * @param   DeprecatedInterface     $deprecated     The deprecated handler
     */
    public function __construct(
        protected DeprecatedInterface $deprecated
    ) {
    }

    public function add(string $msg): void
    {
        $this->flag    = true;
        $this->stack[] = $msg;
    }

    public function flag(): bool
    {
        return $this->flag;
    }

    public function reset(): void
    {
        $this->flag  = false;
        $this->stack = [];
    }

    public function count(): int
    {
        return count($this->stack);
    }

    public function dump(): array
    {
        return $this->stack;
    }

    /**
     * @deprecated since 2.28, use your own parser instead.
     */
    public function toHTML(bool $reset = true): string
    {
        $this->deprecated->set('', '2.28');

        $res = '';

        if ($this->flag) {
            foreach ($this->stack as $msg) {
                $res .= Notices::error($msg, true, false, false);
            }
            if ($reset) {
                $this->reset();
            }
        }

        return $res;
    }
}
