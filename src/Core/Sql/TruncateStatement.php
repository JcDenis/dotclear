<?php
declare(strict_types=1);

namespace Dotclear\Core\Sql;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

/**
 * Truncate Statement : small utility to build truncate queries
 */
class TruncateStatement extends SqlStatement
{
    /**
     * Class constructor
     *
     * @param mixed     $ctx    optional context
     */
    public function __construct($ctx = null)
    {
        parent::__construct($ctx);
    }

    /**
     * Returns the truncate statement
     *
     * @return string the statement
     */
    public function statement(): string
    {
        # --BEHAVIOR-- coreBeforeInsertStatement
        dotclear()->behavior()->call('coreBeforeTruncateStatement', $this);

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
        dotclear()->behavior()->call('coreAfterTruncateStatement', $this, $query);

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
