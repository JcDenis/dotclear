<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Mapper;

// Dotclear\Helper\Mapper\Callables
use Dotclear\App;
use Dotclear\Exception\InvalidMethodException;
use Exception;
use Error;

/**
 * Tiny helper to manage array of callable values.
 *
 * @ingroup Helper Stack
 */
class Callables
{
    /**
     * @var array<int,callable> $stack
     *                          The callables stack
     */
    protected $stack = [];

    /**
     * Add a new callback.
     *
     * @param callable $value The callback to add
     */
    public function add(callable $value): void
    {
        $this->stack[] = $value;
    }

    /**
     * Get stack size (number of callback).
     *
     * @return int The size
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Dump array of callbacks.
     *
     * @return array<int,callable> The callbacks
     */
    public function dump(): array
    {
        return $this->stack;
    }

    /**
     * Calls every function in stack.
     *
     * @param mixed $args The callback arguments
     *
     * @return string The callbacks concatened result
     */
    public function call(mixed ...$args): string
    {
        $result = '';

        try {
            foreach ($this->stack as $callback) {
                $response = $callback(...$args);
                if (is_string($response)) {
                    $result .= $response;
                }
            }
        } catch (Exception|Error $e) {
            if (!App::core()->isProductionMode()) {
                throw new InvalidMethodException('Invalid callback: ' . $e->getMessage() . $e->getPrevious()?->getMessage());
            }
        }

        return $result;
    }
}
