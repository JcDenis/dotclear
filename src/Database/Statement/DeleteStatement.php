<?php

declare(strict_types=1);

namespace Dotclear\Database\Statement;

/**
 * Delete Statement : small utility to build delete queries.
 */
class DeleteStatement extends SqlStatement
{
    public static function init(string $ctx = null): DeleteStatement
    {
        return new self($ctx);
    }

    /**
     * Returns the delete statement.
     *
     * @return string the statement
     */
    public function statement(): string
    {
        // --BEHAVIOR-- coreBeforeDeleteStatement
        dotclear()->behavior()->call('coreBeforeDeleteStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL DELETE requires a FROM source'), E_USER_ERROR);
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
                $query .= 'WHERE TRUE '; // Hack to cope with the operator included in top of each condition
            }
            $query .= join(' ', $this->cond) . ' ';
        }

        // Generic clause(s)
        if (count($this->sql)) {
            $query .= join(' ', $this->sql) . ' ';
        }

        $query = trim($query);

        // --BEHAVIOR-- coreAfertDeleteStatement
        dotclear()->behavior()->call('coreAfterDeleteStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result.
     */
    public function delete(): bool
    {
        if (($sql = $this->statement())) {
            return dotclear()->con()->execute($sql);
        }

        return false;
    }

    /**
     * delete() alias.
     */
    public function run(): bool
    {
        return $this->delete();
    }
}
