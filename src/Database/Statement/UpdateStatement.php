<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Statement;

use Dotclear\Database\Cursor;

/**
 * Update Statement : small utility to build update queries.
 *
 * \Dotclear\Database\Statement\UpdateStatement
 *
 * @ingroup Database Statement
 */
class UpdateStatement extends SqlStatement
{
    protected $set = [];

    /**
     * Class constructor.
     *
     * @param mixed $ctx optional context
     */
    public function __construct($ctx = null)
    {
        parent::__construct($ctx);
    }

    public static function init(string $ctx = null): UpdateStatement
    {
        return new self($ctx);
    }

    /**
     * from() alias.
     *
     * @param mixed $c     the reference clause(s)
     * @param bool  $reset reset previous reference first
     *
     * @return self instance, enabling to chain calls
     */
    public function reference($c, bool $reset = false): UpdateStatement
    {
        $this->from($c, $reset);

        return $this;
    }

    /**
     * from() alias.
     *
     * @param mixed $c     the reference clause(s)
     * @param bool  $reset reset previous reference first
     *
     * @return self instance, enabling to chain calls
     */
    public function ref($c, bool $reset = false): UpdateStatement
    {
        return $this->reference($c, $reset);
    }

    /**
     * Adds update value(s).
     *
     * @param mixed $c     the udpate values(s)
     * @param bool  $reset reset previous update value(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function set($c, bool $reset = false): UpdateStatement
    {
        if ($reset) {
            $this->set = [];
        }
        if (is_array($c)) {
            $this->set = array_merge($this->set, $c);
        } else {
            array_push($this->set, $c);
        }

        return $this;
    }

    /**
     * set() alias.
     *
     * @param mixed $c     the update value(s)
     * @param bool  $reset reset previous update value(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function sets($c, bool $reset = false): UpdateStatement
    {
        return $this->set($c, $reset);
    }

    /**
     * Returns the WHERE part of update statement.
     *
     * Useful to construct the where clause used with cursor->update() method
     *
     * @return string The where part of update statement
     */
    public function whereStatement(): string
    {
        // --BEHAVIOR-- coreBeforeUpdateWhereStatement
        dotclear()->behavior()->call('coreBeforeUpdateWhereStatement', $this);

        $query = '';

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

        $query = trim($query);

        // --BEHAVIOR-- coreAfertUpdateWhereStatement
        dotclear()->behavior()->call('coreAfterUpdateWhereStatement', $this, $query);

        return $query;
    }

    /**
     * Returns the update statement.
     *
     * @return string the statement
     */
    public function statement(): string
    {
        // --BEHAVIOR-- coreBeforeUpdateStatement
        dotclear()->behavior()->call('coreBeforeUpdateStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL UPDATE requires an INTO source'), E_USER_ERROR);
        }

        // Query
        $query = 'UPDATE ';

        // Reference
        $query .= $this->from[0] . ' ';

        // Value(s)
        if (count($this->set)) {
            $query .= 'SET ' . join(', ', $this->set) . ' ';
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

        $query = trim($query);

        // --BEHAVIOR-- coreAfertUpdateStatement
        dotclear()->behavior()->call('coreAfterUpdateStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL update query.
     *
     * @param null|cursor $cur The cursor
     */
    public function update(?Cursor $cur = null): bool
    {
        if ($cur) {
            return $cur->update($this->whereStatement());
        }

        if (($sql = $this->statement())) {
            return dotclear()->con()->execute($sql);
        }

        return false;
    }

    /**
     * update() alias.
     *
     * @param null|cursor $cur The cursor
     */
    public function run(?Cursor $cur = null): bool
    {
        return $this->update($cur);
    }
}
