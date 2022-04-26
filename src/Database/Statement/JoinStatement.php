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
     * Class constructor.
     *
     * @param mixed $ctx optional context
     */
    public function __construct($ctx = null)
    {
        $this->type = null;

        parent::__construct($ctx);
    }

    public static function init(string $ctx = null): JoinStatement
    {
        return new self($ctx);
    }

    /**
     * Defines the type for join.
     *
     * @return self instance, enabling to chain calls
     */
    public function type(string $type = ''): JoinStatement
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Returns the join fragment.
     *
     * @return string the fragment
     */
    public function statement(): string
    {
        // --BEHAVIOR-- coreBeforeDeleteStatement
        App::core()->behavior()->call('coreBeforeJoinStatement', $this);

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
        App::core()->behavior()->call('coreAfterJoinStatement', $this, $query);

        return $query;
    }
}
