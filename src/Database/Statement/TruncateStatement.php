<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Statement;

// Dotclear\Database\Statement\TruncateStatement
use Dotclear\App;

/**
 * Truncate Statement : small utility to build truncate queries.
 *
 * @ingroup Database Statement
 */
class TruncateStatement extends SqlStatement
{
    /**
     * Returns the truncate statement.
     *
     * @return string the statement
     */
    public function statement(): string
    {
        // --BEHAVIOR-- coreBeforeInsertStatement
        App::core()->behavior('coreBeforeTruncateStatement')->call($this);

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
        App::core()->behavior('coreAfterTruncateStatement')->call($this, $query);

        return $query;
    }

    /**
     * Run the SQL select query and return result.
     */
    public function truncate(): bool
    {
        if (($sql = $this->statement())) {
            return App::core()->con()->execute($sql);
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
