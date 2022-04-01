<?php
declare(strict_types=1);

namespace Dotclear\Database\Statement;

/**
 * Insert Statement : small utility to build insert queries
 */
class InsertStatement extends SqlStatement
{
    protected $lines;

    /**
     * Class constructor
     *
     * @param mixed     $ctx    optional context
     */
    public function __construct($ctx = null)
    {
        $this->lines = [];

        parent::__construct($ctx);
    }

    /**
     * from() alias
     *
     * @param mixed     $c      the into clause(s)
     * @param boolean   $reset  reset previous into first
     *
     * @return self instance, enabling to chain calls
     */
    public function into($c, bool $reset = false): InsertStatement
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
    public function lines($c, bool $reset = false): InsertStatement
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
    public function line($c, bool $reset = false): InsertStatement
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
        dotclear()->behavior()->call('coreBeforeInsertStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL INSERT requires an INTO source'), E_USER_ERROR);
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
        dotclear()->behavior()->call('coreAfterInsertStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result
     *
     * @return     bool  true
     */
    public function insert(): bool
    {
        if (($sql = $this->statement())) {
            return dotclear()->con()->execute($sql);
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
