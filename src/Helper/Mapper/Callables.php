<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\Behavior
use Dotclear\App;
use Dotclear\Exception\InvalidMethodException;
use Exception;
use Error;

/**
 * Stack by group of callable functions.
 *
 * @ingroup  Helper Behavior Stack
 */
class Behavior
{
    /**
     * @var array<string,array> $behaviors
     *                          Registered behaviors
     */
    private $behaviors = [];

    /**
     * Adds a new function to a behaviors group.
     *
     * $callback must be a valid and callable callback.
     *
     * @param string   $group    The group name
     * @param callable $callback The callback function
     */
    public function add(string $group, callable $callback): void
    {
        $this->behaviors[$group][] = $callback;
    }

    /**
     * Determines if a group exists in behaviors.
     *
     * @param string $group The behavior
     *
     * @return bool True if behavior exists, False otherwise
     */
    public function has(string $group): bool
    {
        return !empty($group) && isset($this->behaviors[$group]);
    }

    /**
     * Gets the behaviors of a given group.
     *
     * @param string $group The group
     *
     * @return array<int,callable> The behaviors of a group
     */
    public function get(string $group): array
    {
        return $this->has($group) ? $this->behaviors[$group] : [];
    }

    /**
     * Calls every function in behaviors for a given group
     * and returns concatened result of each function.
     *
     * Every parameters added after $group will be pass to calls.
     *
     * @param string $group   The group
     * @param mixed  ...$args The arguments
     *
     * @return string Behavior concatened result
     */
    public function call(string $group, mixed ...$args): string
    {
        $result = '';

        try {
            foreach ($this->get($group) as $callback) {
                $response = $callback(...$args);
                if (is_string($response)) {
                    $result .= $response;
                }
            }
        } catch (Exception|Error $e) {
            if (!App::core()->production()) {
                throw new InvalidMethodException('Invalid callback on behavior "' . $group . '": ' . $e->getMessage() . $e->getPrevious()?->getMessage());
            }
        }

        return $result;
    }

    /**
     * Dump behaviors stack.
     *
     * @return array<string,array> Registred behaviors
     */
    public function dump(): array
    {
        return $this->behaviors;
    }
}
