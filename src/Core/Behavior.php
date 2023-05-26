<?php
/**
 * @brief Behavior core class.
 *
 * Provides an object to manage behaviors stack
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

class Behavior
{
    /** @var    array<string,callable>   Stack of behaviors */
    private $stack = [];

    /**
     * Add behavior(s).
     * 
     * For one, arguments are the behavior name et its callback.
     * For multiple, the first argument if an array on which
     * each row must contains the behavior and a valid callable callback.
     *
     * @param   string|array<string,mixed>  $behavior   The behaviors or the behavior name (for one)
     * @param   mixed                       $callback   The callback (only for one)
     */
    public function add(string|array $behaviors, mixed $callback = null): void
    {
        if (is_string($behaviors)) {
            $behaviors = [$behaviors => $callback];
        }

        foreach ($behaviors as $behavior => $callback) {
            if (is_callable($callback)) {
                $this->stack[$behavior][] = $callback;
            }
        }
    }

    /**
     * Check if behavior exists.
     *
     * @param   string  behavior    The behavior
     *
     * @return  bool    True if behavior exists, False otherwise.
     */
    public function has(string $behavior): bool
    {
        return isset($this->stack[$behavior]);
    }

    /**
     * Get the behaviors stack.
     *
     * @param   string  $behavior   The behavior
     *
     * @return  array<string,callable>  The behaviors.
     */
    public function dump(): array
    {
        return $this->stack;
    }

    /**
     * Get a behavior.
     *
     * @param   string  $behaviour  The behaviour
     *
     * @return  mixed   The behaviours.
     */
    public function get(string $behavior = ''): ?callable
    {
        return isset($this->stack[$behavior]) ? $this->stack[$behavior] : null;
    }

    /**
     * Calls every function in behaviors stack for a given behavior.
     * 
     * This returns concatened result of each function.
     * Every parameters added after <var>$behavior</var> will be pass to
     * behavior calls.
     *
     * @param   string  $behavior   The behavior
     * @param   mixed   ...$args    The arguments
     *
     * @return  string  Behavior concatened result
     */
    public function call(string $behavior, ...$args): string
    {
        $res = '';
        if (isset($this->stack[$behavior])) {
            foreach ($this->stack[$behavior] as $callback) {
                $result = $callback(...$args);
                if (is_string($result)) {
                    $res .= $result;
                }
            }
        }

        return $res;
    }
}