<?php
/**
 * @class SelectStatement
 *
 * Select Statement : small utility to build select queries
 *
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Statement;

use dcCore;
use Dotclear\Core\Core;
use Dotclear\Database\MetaRecord;

class SelectStatement extends SqlStatement
{
    protected $join;
    protected $union;
    protected $having;
    protected $order;
    protected $group;
    protected $limit;
    protected $offset;
    protected $distinct;

    /**
     * Constructs a new instance.
     *
     * @param      mixed         $con     The DB handle
     * @param      null|string   $syntax  The syntax
     */
    public function __construct($con = null, ?string $syntax = null)
    {
        $this->join = $this->union = $this->having = $this->order = $this->group = [];

        $this->limit    = null;
        $this->offset   = null;
        $this->distinct = false;

        parent::__construct($con, $syntax);
    }

    /**
     * Adds JOIN clause(s) (applied on first from item only)
     *
     * @param mixed     $c      the join clause(s)
     * @param boolean   $reset  reset previous join(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function join($c, bool $reset = false): SelectStatement
    {
        if ($reset) {
            $this->join = [];
        }
        if (is_array($c)) {
            $this->join = array_merge($this->join, $c);
        } else {
            array_push($this->join, $c);
        }

        return $this;
    }

    /**
     * Adds UNION clause(s)
     *
     * @param mixed     $c      the union clause(s)
     * @param boolean   $reset  reset previous union(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function union($c, bool $reset = false): SelectStatement
    {
        if ($reset) {
            $this->union = [];
        }
        if (is_array($c)) {
            $this->union = array_merge($this->union, $c);
        } else {
            array_push($this->union, $c);
        }

        return $this;
    }

    /**
     * Adds HAVING clause(s)
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous having(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function having($c, bool $reset = false): SelectStatement
    {
        if ($reset) {
            $this->having = [];
        }
        if (is_array($c)) {
            $this->having = array_merge($this->having, $c);
        } else {
            array_push($this->having, $c);
        }

        return $this;
    }

    /**
     * Adds ORDER BY clause(s)
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous order(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function order($c, bool $reset = false): SelectStatement
    {
        if ($reset) {
            $this->order = [];
        }
        if (is_array($c)) {
            $this->order = array_merge($this->order, $c);
        } else {
            array_push($this->order, $c);
        }

        return $this;
    }

    /**
     * Adds GROUP BY clause(s)
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous group(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function group($c, bool $reset = false): SelectStatement
    {
        if ($reset) {
            $this->group = [];
        }
        if (is_array($c)) {
            $this->group = array_merge($this->group, $c);
        } else {
            array_push($this->group, $c);
        }

        return $this;
    }

    /**
     * group() alias
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous group(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function groupBy($c, bool $reset = false): SelectStatement
    {
        return $this->group($c, $reset);
    }

    /**
     * Defines the LIMIT for select
     *
     * @param mixed $limit (limit or [offset,limit])
     *
     * @return self instance, enabling to chain calls
     */
    public function limit($limit): SelectStatement
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
        if ($offset !== null) {
            $this->offset = $offset;
        }

        return $this;
    }

    /**
     * Defines the OFFSET for select
     *
     * @param integer $offset
     *
     * @return self instance, enabling to chain calls
     */
    public function offset(int $offset): SelectStatement
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Defines the DISTINCT flag for select
     *
     * @param boolean $distinct
     *
     * @return self instance, enabling to chain calls
     */
    public function distinct(bool $distinct = true): SelectStatement
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * Returns the select statement
     *
     * @return string the statement
     */
    public function statement(): string
    {
        # --BEHAVIOR-- coreBeforeSelectStatement -- SqlStatement
        if (class_exists('dcCore')) {
            Core::behavior()->callBehavior('coreBeforeSelectStatement', $this);
        }

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL SELECT requires a FROM source'), E_USER_ERROR);

            return '';  // @phpstan-ignore-line
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
        if (is_countable($this->join) ? count($this->join) : 0) {
            $query .= join(' ', $this->join) . ' ';
        }
        if (count($this->from) > 1) {
            $query = trim($query) . ', ' . join(', ', array_slice($this->from, 1)) . ' '; // All other from(s)
        }

        // Where clause(s)
        if (count($this->where)) {
            $query .= 'WHERE ' . join(' AND ', $this->where) . ' ';
        }

        // Direct where clause(s)
        if (count($this->cond)) {
            if (!count($this->where)) {
                // Hack to cope with the operator included in top of each condition
                $query .= 'WHERE ' . ($this->syntax === 'sqlite' ? '1' : 'TRUE') . ' ';
            }
            $query .= join(' ', $this->cond) . ' ';
        }

        // Generic clause(s)
        if (count($this->sql)) {
            $query .= join(' ', $this->sql) . ' ';
        }

        // Group by clause (columns or aliases)
        if (is_countable($this->group) ? count($this->group) : 0) {
            $query .= 'GROUP BY ' . join(', ', $this->group) . ' ';
        }

        // Having clause(s)
        if (is_countable($this->having) ? count($this->having) : 0) {
            $query .= 'HAVING ' . join(' AND ', $this->having) . ' ';
        }

        // Union clause(s)
        if (is_countable($this->union) ? count($this->union) : 0) {
            $query .= 'UNION ' . join(' UNION ', $this->union) . ' ';
        }

        // Clauses applied on result
        // -------------------------

        // Order by clause (columns or aliases and optionnaly order ASC/DESC)
        if (is_countable($this->order) ? count($this->order) : 0) {
            $query .= 'ORDER BY ' . join(', ', $this->order) . ' ';
        }

        // Limit clause
        if ($this->limit !== null) {
            $query .= 'LIMIT ' . $this->limit . ' ';
        }

        // Offset clause
        if ($this->offset !== null) {
            $query .= 'OFFSET ' . $this->offset . ' ';
        }

        $query = trim($query);

        # --BEHAVIOR-- coreAfertSelectStatement -- SqlStatement, string
        if (class_exists('dcCore')) {
            Core::behavior()->callBehavior('coreAfterSelectStatement', $this, $query);
        }

        return $query;
    }

    /**
     * Run the SQL select query and return result
     *
     * @return     MetaRecord  record
     */
    public function select(): ?MetaRecord
    {
        if ($this->con && ($sql = $this->statement())) {
            return new MetaRecord($this->con->select($sql));
        }

        return null;
    }

    /**
     * select() alias
     *
     * @return     MetaRecord  record
     */
    public function run(): ?MetaRecord
    {
        return $this->select();
    }
}
