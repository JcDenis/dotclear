<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

// Dotclear\Database\Param;

/**
 * Tiny database query parameter helper.
 *
 * This class is a simple stack that help
 * to return right sql query parameters types.
 *
 * @ingroup  Core Helper Param
 */
class Param
{
    // / @name Setter methods
    // @{
    /**
     * @var array<string,mixed> $params
     *                          The list of parameters
     */
    private $params = [];

    /**
     * Constructor.
     *
     * Populate this Param instance from another Param instance.
     *
     * @param null|Param $params A Param instance
     */
    public function __construct(?Param $params = null)
    {
        if (null !== $params) {
            foreach ($params->dump() as $key => $value) {
                $this->set($key, $value);
            }
        }
    }

    /**
     * Get a parameter.
     *
     * @param string $key The parameter name
     *
     * @return mixed The parameter value or null if not exists
     */
    public function get(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Set a parameter.
     *
     * @param string $key   The parameter name
     * @param mixed  $value The parameter value
     */
    public function set(string $key, mixed $value): void
    {
        if (preg_match('/^[a-z_]+$/', $key)) {
            $this->params[$key] = $value;
        }
    }

    /**
     * Unset a parameter.
     *
     * @param string $key The parameter name
     */
    public function unset(string $key): void
    {
        unset($this->params[$key]);
    }

    /**
     * Check if a parameter is set.
     *
     * @param string $key The parameter name
     *
     * @return bool True if the parameter is set
     */
    public function isset(string $key): bool
    {
        return array_key_exists($key, $this->params);
    }

    /**
     * Push additionnal value to a paramater.
     *
     * If parameter does not exist, it will be created.
     * This works only for parameter that contains
     * string value or array of string values.
     *
     * @param string $key   The parameter key
     * @param string $value The parameter additional value
     */
    public function push(string $key, string $value): void
    {
        if (is_array($this->get($key))) {
            $values   = $this->getCleanedValues($key, 'string');
            $values[] = $value;
        } else {
            $values = $this->getCleanedValue($key, 'string') . $value;
        }
        $this->set($key, $values);
    }

    /**
     * Get all parameters in an array.
     *
     * @return array<string,mixed> The parameters array
     */
    public function dump(): array
    {
        return $this->params;
    }

    /**
     * Convert paramater of single value.
     *
     * @param string               $key     The parameter name
     * @param string               $type    The parameter value type
     * @param null|bool|int|string $default The parameter default value
     *
     * @return null|bool|int|string The parameter value
     */
    protected function getCleanedValue(string $key, string $type, null|string|int|bool $default = null): null|string|int|bool
    {
        return $this->isset($key) && get_debug_type($this->get($key)) == $type ? $this->get($key) : $default;
    }

    /**
     * Convert paramater of array values.
     *
     * @param string $key  The parameter name
     * @param string $type The parameter values type
     *
     * @return array<int,mixed> The parameter values
     */
    protected function getCleanedValues(string $key, string $type = 'string'): array
    {
        $res = $values = [];
        if (is_array($this->get($key))) {
            $values = $this->get($key);
        } else {
            $values[] = $this->get($key);
        }

        foreach ($values as $value) {
            if (null !== $value) {
                if (settype($value, $type)) {
                    $res[] = $value;
                }
            }
        }

        return $res;
    }
    // @}

    // / @name Common parameters methods
    // @{
    /**
     * Order of results (default "ORDER BY log_dt DESC").
     *
     * @param string $default The default order
     *
     * @return string The order (sorby ans order)
     */
    public function order(string $default = ''): string
    {
        return $this->getCleanedValue('order', 'string', $default);
    }

    /**
     * Get limit parameter.
     *
     * @return mixed The limit parameter
     */
    public function limit(): mixed
    {
        return $this->get('limit');
    }

    /**
     * Get sql additionnal query.
     *
     * @return null|string The sql additionnal query
     */
    public function sql(): ?string
    {
        return $this->getCleanedValue('sql', 'string');
    }

    /**
     * Get join paramater.
     *
     * @return array<int,string> The join parameter
     */
    public function join(): array
    {
        return $this->getCleanedValues('join', 'string');
    }

    /**
     * Get from paramater.
     *
     * @return array<int,string> The from parameter
     */
    public function from(): array
    {
        return $this->getCleanedValues('from', 'string');
    }

    /**
     * Get where paramater.
     *
     * @return array<int,string> The where parameter
     */
    public function where(): array
    {
        return $this->getCleanedValues('where', 'string');
    }

    /**
     * Get columns paramater.
     *
     * @return array<int,string> The columns parameter
     */
    public function columns(): array
    {
        return $this->getCleanedValues('columns', 'string');
    }
    // @}
}
