<?php
declare(strict_types=1);

namespace Dotclear\Core\Sql;

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

/**
 * Join (sub)Statement : small utility to build join query fragments
 */
class JoinStatement extends SqlStatement
{
    protected $type;

    /**
     * Class constructor
     *
     * @param Core    $core   Core instance
     * @param mixed     $ctx    optional context
     */
    public function __construct(Core &$core, $ctx = null)
    {
        $this->type = null;

        parent::__construct($core, $ctx);
    }

    /**
     * Defines the type for join
     *
     * @param string $type
     * @return self instance, enabling to chain calls
     */
    public function type(string $type = ''): JoinStatement
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Returns the join fragment
     *
     * @return string the fragment
     */
    public function statement(): string
    {
        # --BEHAVIOR-- coreBeforeDeleteStatement
        $this->core->callBehavior('coreBeforeJoinStatement', $this);

        // Check if source given
        if (!count($this->from)) {
            trigger_error(__('SQL JOIN requires a source'), E_USER_ERROR);

            return '';
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

        # --BEHAVIOR-- coreAfertSelectStatement
        $this->core->callBehavior('coreAfterJoinStatement', $this, $query);

        return $query;
    }
}
