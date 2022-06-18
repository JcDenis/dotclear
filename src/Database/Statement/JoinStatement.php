<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Statement;

// Dotclear\Database\Statement\JoinStatement
use Dotclear\App;

/**
 * Join (sub)Statement : small utility to build join query fragments.
 *
 * @ingroup Database Statement
 */
class JoinStatement extends SqlStatement
{
    protected $type;

    /**
     * Defines the type for join.
     */
    public function type(string $type = ''): void
    {
        $this->type = $type;
    }

    /**
     * Returns the join fragment.
     *
     * @return string the fragment
     */
    public function statement(): string
    {
        // --BEHAVIOR-- coreBeforeDeleteStatement
        App::core()->behavior('coreBeforeJoinStatement')->call($this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL JOIN requires a source'), E_USER_ERROR);
        }

        // Query
        $query = 'JOIN ';

        if ($this->type) {
            // LEFT, RIGHT, …
            $query = $this->type . ' ' . $query;
        }

        // Table
        $query .= ' ' . $this->from[0] . ' ';

        // Where clause(s)
        if (count($this->where)) {
            $query .= 'ON ' . join(' AND ', $this->where) . ' ';
        }

        // Direct where clause(s)
        if (count($this->cond)) {
            $query .= join(' ', $this->cond) . ' ';
        }

        // Generic clause(s)
        if (count($this->sql)) {
            $query .= join(' ', $this->sql) . ' ';
        }

        $query = trim($query);

        // --BEHAVIOR-- coreAfertSelectStatement
        App::core()->behavior('coreAfterJoinStatement')->call($this, $query);

        return $query;
    }
}
