<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Statement;

// Dotclear\Database\Statement\SelectStatement
use Dotclear\App;

/**
 * Select Statement : small utility to build select queries.
 *
 * @ingroup Database Statement
 */
class SelectStatement extends SqlStatement
{
    protected $join   = [];
    protected $having = [];
    protected $order  = [];
    protected $group  = [];
    protected $limit;
    protected $offset;
    protected $distinct = false;

    /**
     * Adds JOIN clause(s) (applied on first from item only).
     *
     * @param mixed $c     the join clause(s)
     * @param bool  $reset reset previous join(s) first
     */
    public function join($c, bool $reset = false): void
    {
        if ($reset) {
            $this->join = [];
        }
        if (is_array($c)) {
            $this->join = array_merge($this->join, $c);
        } else {
            array_push($this->join, $c);
        }
    }

    /**
     * Adds HAVING clause(s).
     *
     * @param mixed $c     the clause(s)
     * @param bool  $reset reset previous having(s) first
     */
    public function having($c, bool $reset = false): void
    {
        if ($reset) {
            $this->having = [];
        }
        if (is_array($c)) {
            $this->having = array_merge($this->having, $c);
        } else {
            array_push($this->having, $c);
        }
    }

    /**
     * Adds ORDER BY clause(s).
     *
     * @param mixed $c     the clause(s)
     * @param bool  $reset reset previous order(s) first
     */
    public function order($c, bool $reset = false): void
    {
        if ($reset) {
            $this->order = [];
        }
        if (is_array($c)) {
            $this->order = array_merge($this->order, $c);
        } else {
            array_push($this->order, $c);
        }
    }

    /**
     * Adds GROUP BY clause(s).
     *
     * @param mixed $c     the clause(s)
     * @param bool  $reset reset previous group(s) first
     */
    public function group($c, bool $reset = false): void
    {
        if ($reset) {
            $this->group = [];
        }
        if (is_array($c)) {
            $this->group = array_merge($this->group, $c);
        } else {
            array_push($this->group, $c);
        }
    }

    /**
     * Defines the LIMIT for select.
     *
     * @param mixed $limit
     */
    public function limit($limit): void
    {
        $offset = null;
        if (is_array($limit)) {
            // Keep only values
            $limit = array_values($limit);
            // If 2 values, [0] -> offset, [1] -> limit
            // If 1 value, [0] -> limit
            if (isset($limit[1])) {
                $offset = $limit[0];
                $limit  = $limit[1];
            } else {
                $limit = $limit[0];
            }
        }
        $this->limit = $limit;
        if (null !== $offset) {
            $this->offset = $offset;
        }
    }

    /**
     * Defines the OFFSET for select.
     */
    public function offset(int $offset): void
    {
        $this->offset = $offset;
    }

    /**
     * Defines the DISTINCT flag for select.
     */
    public function distinct(bool $distinct = true): void
    {
        $this->distinct = $distinct;
    }

    /**
     * Returns the select statement.
     *
     * @return string the statement
     */
    public function statement(): string
    {
        // --BEHAVIOR-- coreBeforeSelectStatement
        App::core()->behavior('coreBeforeSelectStatement')->call($this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL SELECT requires a FROM source'), E_USER_ERROR);
        }

        // Query
        $query = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '');

        // Specific column(s) or all (*)
        if (count($this->columns)) {
            $query .= join(', ', $this->columns) . ' ';
        } else {
            $query .= '* ';
        }

        // Table(s) and Join(s)
        $query .= 'FROM ' . $this->from[0] . ' ';
        $query .= join(' ', $this->join) . ' ';
        if (count($this->from) > 1) {
            $query .= ', ' . join(', ', array_slice($this->from, 1)) . ' '; // All other from(s)
        }

        // Where clause(s)
        if (count($this->where)) {
            $query .= 'WHERE ' . join(' AND ', $this->where) . ' ';
        }

        // Direct where clause(s)
        if (count($this->cond)) {
            if (!count($this->where)) {
                $query .= 'WHERE TRUE '; // Hack to cope with the operator included in top of each condition
            }
            $query .= join(' ', $this->cond) . ' ';
        }

        // Generic clause(s)
        if (count($this->sql)) {
            $query .= join(' ', $this->sql) . ' ';
        }

        // Group by clause (columns or aliases)
        if (count($this->group)) {
            $query .= 'GROUP BY ' . join(', ', $this->group) . ' ';
        }

        // Having clause(s)
        if (count($this->having)) {
            $query .= 'HAVING ' . join(' AND ', $this->having) . ' ';
        }

        // Order by clause (columns or aliases and optionnaly order ASC/DESC)
        if (count($this->order)) {
            $query .= 'ORDER BY ' . join(', ', $this->order) . ' ';
        }

        // Limit clause
        if (null !== $this->limit) {
            $query .= 'LIMIT ' . $this->limit . ' ';
        }

        // Offset clause
        if (null !== $this->offset) {
            $query .= 'OFFSET ' . $this->offset . ' ';
        }

        $query = trim($query);

        // --BEHAVIOR-- coreAfertSelectStatement
        App::core()->behavior('coreAfterSelectStatement')->call($this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result.
     *
     * @return mixed record or staticRecord (for sqlite)
     */
    public function select()
    {
        if (($sql = $this->statement())) {
            return App::core()->con()->select($sql);
        }

        return null;
    }

    /**
     * select() alias.
     */
    public function run(): bool
    {
        return $this->select();
    }
}
