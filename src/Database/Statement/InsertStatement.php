<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Statement;

// Dotclear\Database\Statement\InsertStatement
use Dotclear\App;

/**
 * Insert Statement : small utility to build insert queries.
 *
 * @ingroup Database Statement
 */
class InsertStatement extends SqlStatement
{
    protected $lines = [];

    /**
     * from() alias.
     *
     * @param mixed $c     the into clause(s)
     * @param bool  $reset reset previous into first
     */
    public function into($c, bool $reset = false): void
    {
        $this->from($c, $reset);
    }

    /**
     * Adds update value(s).
     *
     * @param mixed $c     the insert values(s)
     * @param bool  $reset reset previous insert value(s) first
     */
    public function lines($c, bool $reset = false): void
    {
        if ($reset) {
            $this->lines = [];
        }
        if (is_array($c)) {
            $this->lines = array_merge($this->lines, $c);
        } else {
            array_push($this->lines, $c);
        }
    }

    /**
     * line() alias.
     *
     * @param mixed $c     the insert value(s)
     * @param bool  $reset reset previous insert value(s) first
     */
    public function line($c, bool $reset = false): void
    {
        $this->lines($c, $reset);
    }

    /**
     * Returns the insert statement.
     *
     * @return string the statement
     */
    public function statement(): string
    {
        // --BEHAVIOR-- coreBeforeInsertStatement
        App::core()->behavior()->call('coreBeforeInsertStatement', $this);

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

        // --BEHAVIOR-- coreAfertInsertStatement
        App::core()->behavior()->call('coreAfterInsertStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result.
     *
     * @return bool true
     */
    public function insert(): bool
    {
        if (($sql = $this->statement())) {
            return App::core()->con()->execute($sql);
        }

        return false;
    }

    /**
     * insert() alias.
     */
    public function run(): bool
    {
        return $this->insert();
    }
}
