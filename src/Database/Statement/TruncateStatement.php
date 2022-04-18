<?php

declare(strict_types=1);

namespace Dotclear\Database\Statement;

/**
 * Truncate Statement : small utility to build truncate queries.
 */
class TruncateStatement extends SqlStatement
{
    public static function init(string $ctx = null): TruncateStatement
    {
        return new self($ctx);
    }

    /**
     * Returns the truncate statement.
     *
     * @return string the statement
     */
    public function statement(): string
    {
        // --BEHAVIOR-- coreBeforeInsertStatement
        dotclear()->behavior()->call('coreBeforeTruncateStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL TRUNCATE TABLE requires a table source'), E_USER_ERROR);
        }

        // Query
        $query = 'TRUNCATE ';

        // Reference
        $query .= 'TABLE ' . $this->from[0] . ' ';

        $query = trim($query);

        // --BEHAVIOR-- coreAfertInsertStatement
        dotclear()->behavior()->call('coreAfterTruncateStatement', $this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result.
     */
    public function truncate(): bool
    {
        if (($sql = $this->statement())) {
            return dotclear()->con()->execute($sql);
        }

        return false;
    }

    /**
     * truncate() alias.
     */
    public function run(): bool
    {
        return $this->truncate();
    }
}
