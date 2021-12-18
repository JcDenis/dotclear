<?php
declare(strict_types=1);

namespace Dotclear\Core\Sql;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

/**
 * Delete Statement : small utility to build delete queries
 */
class DeleteStatement extends SqlStatement
{
    /**
     * Returns the delete statement
     *
     * @return string the statement
     */
    public function statement(): string
    {
        # --BEHAVIOR-- coreBeforeDeleteStatement
        $this->core->callBehavior('coreBeforeDeleteStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL DELETE requires a FROM source'), E_USER_ERROR);

            return '';
        }

        // Query
        $query = 'DELETE ';

        // Table
        $query .= 'FROM ' . $this->from[0] . ' ';

        // Where clause(s)
        if (count($this->where)) {
            $query .= 'WHERE ' . join(' AND ', $this->where) . ' ';
        }

        // Direct where clause(s)
        if (count($this->cond)) {
            if (!count($this->where)) {
                $query .= 'WHERE 1 '; // Hack to cope with the operator included in top of each condition
            }
            $query .= join(' ', $this->cond) . ' ';
        }

        // Generic clause(s)
        if (count($this->sql)) {
            $query .= join(' ', $this->sql) . ' ';
        }

        $query = trim($query);

        # --BEHAVIOR-- coreAfertDeleteStatement
        $this->core->callBehavior('coreAfterDeleteStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result
     *
     * @return     bool
     */
    public function delete(): bool
    {
        if ($this->con && ($sql = $this->statement())) {
            return $this->con->execute($sql);
        }

        return false;
    }

    /**
     * delete() alias
     *
     * @return     bool
     */
    public function run(): bool
    {
        return $this->delete();
    }
}

/**
 * Update Statement : small utility to build update queries
 */
class dcUpdateStatement extends dcSqlStatement
{
    protected $set;

    /**
     * Class constructor
     *
     * @param dcCore    $core   dcCore instance
     * @param mixed     $ctx    optional context
     */
    public function __construct(dcCore &$core, $ctx = null)
    {
        $this->set = [];

        parent::__construct($core, $ctx);
    }

    /**
     * from() alias
     *
     * @param mixed     $c      the reference clause(s)
     * @param boolean   $reset  reset previous reference first
     *
     * @return self instance, enabling to chain calls
     */
    public function reference($c, bool $reset = false): dcUpdateStatement
    {
        $this->from($c, $reset);

        return $this;
    }

    /**
     * from() alias
     *
     * @param mixed     $c      the reference clause(s)
     * @param boolean   $reset  reset previous reference first
     *
     * @return self instance, enabling to chain calls
     */
    public function ref($c, bool $reset = false): dcUpdateStatement
    {
        return $this->reference($c, $reset);
    }

    /**
     * Adds update value(s)
     *
     * @param mixed     $c      the udpate values(s)
     * @param boolean   $reset  reset previous update value(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function set($c, bool $reset = false): dcUpdateStatement
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
     * set() alias
     *
     * @param      mixed    $c      the update value(s)
     * @param      boolean  $reset  reset previous update value(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function sets($c, bool $reset = false): dcUpdateStatement
    {
        return $this->set($c, $reset);
    }

    /**
     * Returns the WHERE part of update statement
     *
     * Useful to construct the where clause used with cursor->update() method
     *
     * @return string The where part of update statement
     */
    public function whereStatement(): string
    {
        # --BEHAVIOR-- coreBeforeUpdateWhereStatement
        $this->core->callBehavior('coreBeforeUpdateWhereStatement', $this);

        $query = '';

        // Where clause(s)
        if (count($this->where)) {
            $query .= 'WHERE ' . join(' AND ', $this->where) . ' ';
        }

        // Direct where clause(s)
        if (count($this->cond)) {
            if (!count($this->where)) {
                $query .= 'WHERE 1 '; // Hack to cope with the operator included in top of each condition
            }
            $query .= join(' ', $this->cond) . ' ';
        }

        // Generic clause(s)
        if (count($this->sql)) {
            $query .= join(' ', $this->sql) . ' ';
        }

        $query = trim($query);

        # --BEHAVIOR-- coreAfertUpdateWhereStatement
        $this->core->callBehavior('coreAfterUpdateWhereStatement', $this, $query);

        return $query;
    }

    /**
     * Returns the update statement
     *
     * @return string the statement
     */
    public function statement(): string
    {
        # --BEHAVIOR-- coreBeforeUpdateStatement
        $this->core->callBehavior('coreBeforeUpdateStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL UPDATE requires an INTO source'), E_USER_ERROR);

            return '';
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
                $query .= 'WHERE 1 '; // Hack to cope with the operator included in top of each condition
            }
            $query .= join(' ', $this->cond) . ' ';
        }

        // Generic clause(s)
        if (count($this->sql)) {
            $query .= join(' ', $this->sql) . ' ';
        }

        $query = trim($query);

        # --BEHAVIOR-- coreAfertUpdateStatement
        $this->core->callBehavior('coreAfterUpdateStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL update query
     *
     * @param      cursor|null  $cur    The cursor
     *
     * @return     bool
     */
    public function update(?cursor $cur = null): bool
    {
        if ($cur) {
            return $cur->update($this->whereStatement());
        }

        if ($this->con && ($sql = $this->statement())) {
            return $this->con->execute($sql);
        }

        return false;
    }

    /**
     * update() alias
     *
     * @param      cursor|null  $cur    The cursor
     *
     * @return     bool
     */
    public function run(?cursor $cur = null): bool
    {
        return $this->update($cur);
    }
}

/**
 * Insert Statement : small utility to build insert queries
 */
class dcInsertStatement extends dcSqlStatement
{
    protected $lines;

    /**
     * Class constructor
     *
     * @param dcCore    $core   dcCore instance
     * @param mixed     $ctx    optional context
     */
    public function __construct(dcCore &$core, $ctx = null)
    {
        $this->lines = [];

        parent::__construct($core, $ctx);
    }

    /**
     * from() alias
     *
     * @param mixed     $c      the into clause(s)
     * @param boolean   $reset  reset previous into first
     *
     * @return self instance, enabling to chain calls
     */
    public function into($c, bool $reset = false): dcInsertStatement
    {
        $this->from($c, $reset);

        return $this;
    }

    /**
     * Adds update value(s)
     *
     * @param mixed     $c      the insert values(s)
     * @param boolean   $reset  reset previous insert value(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function lines($c, bool $reset = false): dcInsertStatement
    {
        if ($reset) {
            $this->lines = [];
        }
        if (is_array($c)) {
            $this->lines = array_merge($this->lines, $c);
        } else {
            array_push($this->lines, $c);
        }

        return $this;
    }

    /**
     * line() alias
     *
     * @param      mixed    $c      the insert value(s)
     * @param      boolean  $reset  reset previous insert value(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function line($c, bool $reset = false): dcInsertStatement
    {
        return $this->lines($c, $reset);
    }

    /**
     * Returns the insert statement
     *
     * @return string the statement
     */
    public function statement(): string
    {
        # --BEHAVIOR-- coreBeforeInsertStatement
        $this->core->callBehavior('coreBeforeInsertStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL INSERT requires an INTO source'), E_USER_ERROR);

            return '';
        }

        // Query
        $query = 'INSERT ';

        // Reference
        $query .= 'INTO ' . $this->from[0] . ' ';

        // Column(s)
        if (count($this->columns)) {
            $query .= '(' . join(', ', $this->columns) . ') ';
        }

        // Value(s)
        $query .= 'VALUES ';
        if (count($this->lines)) {
            $raws = [];
            foreach ($this->lines as $line) {
                $raws[] = '(' . join(', ', $line) . ')';
            }
            $query .= join(', ', $raws);
        } else {
            // Use SQL default values
            // (useful only if SQL strict mode is off or if every columns has a defined default value)
            $query .= '()';
        }

        $query = trim($query);

        # --BEHAVIOR-- coreAfertInsertStatement
        $this->core->callBehavior('coreAfterInsertStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result
     *
     * @return     bool  true
     */
    public function insert(): bool
    {
        if ($this->con && ($sql = $this->statement())) {
            return $this->con->execute($sql);
        }

        return false;
    }

    /**
     * insert() alias
     *
     * @return     bool
     */
    public function run(): bool
    {
        return $this->insert();
    }
}

/**
 * Truncate Statement : small utility to build truncate queries
 */
class dcTruncateStatement extends dcSqlStatement
{
    /**
     * Class constructor
     *
     * @param dcCore    $core   dcCore instance
     * @param mixed     $ctx    optional context
     */
    public function __construct(dcCore &$core, $ctx = null)
    {
        parent::__construct($core, $ctx);
    }

    /**
     * Returns the truncate statement
     *
     * @return string the statement
     */
    public function statement(): string
    {
        # --BEHAVIOR-- coreBeforeInsertStatement
        $this->core->callBehavior('coreBeforeTruncateStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL TRUNCATE TABLE requires a table source'), E_USER_ERROR);

            return '';
        }

        // Query
        $query = 'TRUNCATE ';

        // Reference
        $query .= 'TABLE ' . $this->from[0] . ' ';

        $query = trim($query);

        # --BEHAVIOR-- coreAfertInsertStatement
        $this->core->callBehavior('coreAfterTruncateStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result
     *
     * @return     bool
     */
    public function truncate(): bool
    {
        if ($this->con && ($sql = $this->statement())) {
            return $this->con->execute($sql);
        }

        return false;
    }

    /**
     * truncate() alias
     *
     * @return     bool
     */
    public function run(): bool
    {
        return $this->truncate();
    }
}
