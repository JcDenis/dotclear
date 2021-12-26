<?php
/**
 * @class Dotclear\Core\Behaviors
 * @brief Dotclear core behaviors handler class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Exception;

use Dotclear\Core\Core;

class Behaviors
{
    /** @var Core  core instance */
    private $core;

    /** @var array Registered behavoirs */
    private $behaviors = [];

    public function __construct(Core $core)
    {
        $this->core = $core;
    }

    /**
     * Adds a new behavior to behaviors stack. <var>$func</var> must be a valid
     * and callable callback.
     *
     * @param      string    $behavior  The behavior
     * @param      callable  $func      The function
     */
    public function add(string $behavior, $func): void
    {
        if (is_callable($func)) {
            $this->behaviors[$behavior][] = $func;
        }
    }

    /**
     * Determines if behavior exists in behaviors stack.
     *
     * @param      string  $behavior  The behavior
     *
     * @return     bool    True if behavior exists, False otherwise.
     */
    public function has(string $behavior): bool
    {
        return isset($this->behaviors[$behavior]);
    }

    /**
     * Gets the behaviors stack (or part of).
     *
     * @param      string  $behavior  The behavior
     *
     * @return     array   The behaviors.
     */
    public function get(string $behavior = ''): array
    {
        if (empty($this->behaviors)) {
            return [];
        }

        if ($behavior == '') {
            return $this->behaviors;
        } elseif (isset($this->behaviors[$behavior])) {
            return $this->behaviors[$behavior];
        }

        return [];
    }

    /**
     * Calls every function in behaviors stack for a given behavior and returns
     * concatened result of each function.
     *
     * Every parameters added after <var>$behavior</var> will be pass to
     * behavior calls. Core will be injected as first argument.
     *
     * @param      string  $behavior  The behavior
     * @param      mixed   ...$args   The arguments
     *
     * @return     mixed   Behavior concatened result
     */
    public function call(string $behavior, ...$args)
    {
        return $this->callArray($behavior, $args);
    }

    public function callArray(string $behavior, array $args)
    {
        if (isset($this->behaviors[$behavior])) {
            $res = '';
            /* add core instance to every call */
            array_unshift($args, $this->core);

            foreach ($this->behaviors[$behavior] as $f) {
                $this->trace($f, $args);
                $res .= call_user_func_array($f, $args);
            }

            return $res;
        }
    }

    /**
     * Trace behaviors call
     *
     *  For debug purpose, you can define a callable function.
     *
     * @param  string $f Called function
     * @param  array  $a Called function arguments
     */
    private function trace($f, $a): void
    {
        if (defined('DOTCLEAR_BEHAVIOR_TRACE') && is_callable(DOTCLEAR_BEHAVIOR_TRACE)) {
            try {
                call_user_func(DOTCLEAR_BEHAVIOR_TRACE, $f, $a);
            } catch (Exception $e) {
            }
        }
    }
}
