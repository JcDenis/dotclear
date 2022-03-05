<?php
/**
 * @class Dotclear\Utils\Behavior
 * @brief Stack by group of callable functions
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Utils;

use Closure;

class Behavior
{
    /** @var array Registered behavoirs */
    private $stack = [];

    /**
     * Adds a new function to a stack group.
     *
     * $callback must be a valid and callable callback.
     *
     * @param   string          $group      The group name
     * @param   string|array    $callback   The callback function
     */
    public function add(string $group, string|array|Closure $callback): void
    {
        # Silently failed non callable function
        if (is_callable($callback)) {
            $this->stack[$group][] = $callback;
        }
    }

    /**
     * Determines if a group exists in stack.
     *
     * @param   string  $group   The behavior
     *
     * @return  bool    True if behavior exists, False otherwise.
     */
    public function has(string $group): bool
    {
        return isset($this->stack[$group]);
    }

    /**
     * Gets the stack (or part of).
     *
     * @param   string  $group   The group
     *
     * @return  array   The stack.
     */
    public function get(string $group): array
    {
        return !empty($group) && isset($this->stack[$group]) ? $this->stack[$group] : [];
    }

    /**
     * Calls every function in stack for a given group
     * and returns concatened result of each function.
     *
     * Every parameters added after $group will be pass to calls.
     *
     * @param   string  $group      The group
     * @param   mixed   ...$args    The arguments
     *
     * @return  string|null  Behavior concatened result
     */
    public function call(string $group, mixed ...$args): ?string
    {
        return $this->callArray($group, $args);
    }

    public function callArray(string $group, array $args): ?string
    {
        if (isset($this->stack[$group])) {
            $res = '';
            foreach ($this->stack[$group] as $callback) {
                $this->trace($callback, $args);
                $ret = call_user_func_array($callback, $args);
                if (is_string($ret)) {
                    $res .= $ret;
                }
            }

            return $res;
        }

        return null;
    }

    /**
     * Dump behaviors stack
     *
     * @return  array   Registred behaviors
     */
    public function dump()
    {
        return $this->stack;
    }

    /**
     * Trace call
     *
     * For debug purpose, you can define a callable function.
     *
     * @param  string   $callback   Called function
     * @param  array    $args       Called function arguments
     */
    private function trace($callback, $args): void
    {
        if (defined('DOTCLEAR_BEHAVIOR_TRACE') && is_callable(DOTCLEAR_BEHAVIOR_TRACE)) {
            try {
                call_user_func(DOTCLEAR_BEHAVIOR_TRACE, $callback, $args);
            } catch (\Exception $e) {
            }
        }
    }
}
