<?php
declare(strict_types=1);

namespace Dotclear\Database\Statement;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

/**
 * SQL Statement : small utility to build SQL queries
 */
class SqlStatement
{
    protected $ctx; // Context (may be useful for behaviour's callback)

    protected $columns = [];
    protected $from    = [];
    protected $where   = [];
    protected $cond    = [];
    protected $sql     = [];

    /**
     * Class constructor
     *
     * @param mixed     $ctx    optional context
     */
    public function __construct($ctx = null)
    {
        $this->ctx  = $ctx;
    }

    /**
     * Magic getter method
     *
     * @param      string  $property  The property
     *
     * @return     mixed   property value if property exists
     */
    public function __get(string $property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        trigger_error('Unknown property ' . $property, E_USER_ERROR);
    }

    /**
     * Magic setter method
     *
     * @param      string  $property  The property
     * @param      mixed   $value     The value
     *
     * @return     self
     */
    public function __set(string $property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        } else {
            trigger_error('Unknown property ' . $property, E_USER_ERROR);
        }

        return $this;
    }

    /**
     * Magic isset method
     *
     * @param      string  $property  The property
     *
     * @return     bool
     */
    public function __isset(string $property): bool
    {
        if (property_exists($this, $property)) {
            return isset($this->$property);
        }

        return false;
    }

    /**
     * Magic unset method
     *
     * @param      string  $property  The property
     */
    public function __unset(string $property)
    {
        if (property_exists($this, $property)) {
            unset($this->$property);
        }
    }

    /**
     * Magic invoke method
     *
     * Alias of statement()
     *
     * @return     string
     */
    public function __invoke(): string
    {
        return $this->statement();
    }

    /**
     * Returns a SQL dummy statement
     *
     * @return string the statement
     */
    public function statement(): string
    {
        return '';
    }

    /**
     * Adds context
     *
     * @param mixed     $c      the context(s)
     *
     * @return self instance, enabling to chain calls
     */
    public function ctx($c): SqlStatement
    {
        $this->ctx = $c;

        return $this;
    }

    /**
     * Adds column(s)
     *
     * @param mixed     $c      the column(s)
     * @param boolean   $reset  reset previous column(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function columns($c, bool $reset = false): SqlStatement
    {
        if ($reset) {
            $this->columns = [];
        }
        if (is_array($c)) {
            $this->columns = array_merge($this->columns, $c);
        } else {
            array_push($this->columns, $c);
        }

        return $this;
    }

    /**
     * columns() alias
     *
     * @param mixed     $c      the column(s)
     * @param boolean   $reset  reset previous column(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function fields($c, bool $reset = false): SqlStatement
    {
        return $this->columns($c, $reset);
    }

    /**
     * columns() alias
     *
     * @param      mixed    $c      the column(s)
     * @param      boolean  $reset  reset previous column(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function column($c, bool $reset = false): SqlStatement
    {
        return $this->columns($c, $reset);
    }

    /**
     * column() alias
     *
     * @param      mixed    $c      the column(s)
     * @param      boolean  $reset  reset previous column(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function field($c, bool $reset = false): SqlStatement
    {
        return $this->column($c, $reset);
    }

    /**
     * Adds FROM clause(s)
     *
     * @param mixed     $c      the from clause(s)
     * @param boolean   $reset  reset previous from(s) first
     * @param boolean   $first  put the from clause(s) at top of list
     *
     * @return self instance, enabling to chain calls
     */
    public function from($c, bool $reset = false, bool $first = false): SqlStatement
    {
        $filter = fn ($v) => trim(ltrim((string) $v, ','));
        if ($reset) {
            $this->from = [];
        }
        // Remove comma on beginning of clause(s) (legacy code)
        if (is_array($c)) {
            $c = array_map($filter, $c);   // Cope with legacy code
            if ($first) {
                $this->from = array_merge($c, $this->from);
            } else {
                $this->from = array_merge($this->from, $c);
            }
        } else {
            $c = $filter($c);   // Cope with legacy code
            if ($first) {
                array_unshift($this->from, $c);
            } else {
                array_push($this->from, $c);
            }
        }

        return $this;
    }

    /**
     * Adds WHERE clause(s) condition (each will be AND combined in statement)
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous where(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function where($c, bool $reset = false): SqlStatement
    {
        $filter = fn ($v) => preg_replace('/^\s*(AND|OR)\s*/i', '', $v);
        if ($reset) {
            $this->where = [];
        }
        if (is_array($c)) {
            $c           = array_map($filter, $c);  // Cope with legacy code
            $this->where = array_merge($this->where, $c);
        } else {
            $c = $filter($c);   // Cope with legacy code
            array_push($this->where, $c);
        }

        return $this;
    }

    /**
     * from() alias
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous where(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function on($c, bool $reset = false): SqlStatement
    {
        return $this->where($c, $reset);
    }

    /**
     * Adds additional WHERE clause condition(s) (including an operator at beginning)
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous condition(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function cond($c, bool $reset = false): SqlStatement
    {
        if ($reset) {
            $this->cond = [];
        }
        if (is_array($c)) {
            $this->cond = array_merge($this->cond, $c);
        } else {
            array_push($this->cond, $c);
        }

        return $this;
    }

    /**
     * Adds additional WHERE AND clause condition(s)
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous condition(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function and($c, bool $reset = false): SqlStatement
    {
        return $this->cond(array_map(fn ($v) => 'AND ' . $v, is_array($c) ? $c : [$c]), $reset);
    }

    /**
     * Helper to group some AND parts
     *
     * @param      mixed  $c      the parts}
     *
     * @return     string
     */
    public function andGroup($c): string
    {
        $group = '(' . implode(' AND ', is_array($c) ? $c : [$c]) . ')';

        return $group === '()' ? '' : $group;
    }

    /**
     * Adds additional WHERE OR clause condition(s)
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous condition(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function or($c, bool $reset = false): SqlStatement
    {
        return $this->cond(array_map(fn ($v) => 'OR ' . $v, is_array($c) ? $c : [$c]), $reset);
    }

    /**
     * Helper to group some OR parts
     *
     * @param      mixed  $c      the parts}
     *
     * @return     string
     */
    public function orGroup($c): string
    {
        $group = '(' . implode(' OR ', is_array($c) ? $c : [$c]) . ')';

        return $group === '()' ? '' : $group;
    }

    /**
     * Adds generic clause(s)
     *
     * @param mixed     $c      the clause(s)
     * @param boolean   $reset  reset previous generic clause(s) first
     *
     * @return self instance, enabling to chain calls
     */
    public function sql($c, bool $reset = false): SqlStatement
    {
        if ($reset) {
            $this->sql = [];
        }
        if (is_array($c)) {
            $this->sql = array_merge($this->sql, $c);
        } else {
            array_push($this->sql, $c);
        }

        return $this;
    }

    // Helpers

    /**
     * Escape a value
     *
     * @param      string  $value  The value
     *
     * @return     string
     */
    public function escape(string $value): string
    {
        return dotclear()->con()->escape($value);
    }

    /**
     * Quote and escape a value if necessary (type string)
     *
     * @param      mixed    $value   The value
     * @param      boolean  $escape  The escape
     *
     * @return     string
     */
    public function quote($value, bool $escape = true): string
    {
        return "'" . ($escape ? dotclear()->con()->escape($value) : $value) . "'";
    }

    /**
     * Return an SQL IN (…) fragment
     *
     * @param      mixed  $list   The list
     * @param      mixed  $list   The list of values
     * @param      string $cast   Cast given not null values to specified type
     *
     * @return     string
     */
    public function in($list, string $cast = ''): string
    {
        if ($cast !== '') {
            switch ($cast) {
                case 'int':
                    if (is_array($list)) {
                        $list = array_map(fn ($v) => is_null($v) ? $v : (int) $v, $list);
                    } else {
                        $list = is_null($list) ? null : (int) $list;
                    }

                    break;
                case 'string':
                    if (is_array($list)) {
                        $list = array_map(fn ($v) => is_null($v) ? $v : (string) $v, $list);
                    } else {
                        $list = is_null($list) ? null : (string) $list;
                    }

                    break;
            }
        }

        return dotclear()->con()->in($list);
    }

    /**
     * Return an SQL formatted date
     *
     * @param   string    $field     Field name
     * @param   string    $pattern   Date format
     *
     * @return     string
     */
    public function dateFormat(string $field, string $pattern): string
    {
        return dotclear()->con()->dateFormat($field, $pattern);
    }

    /**
     * Return an SQL formatted like
     *
     * @param      string  $field    The field
     * @param      string  $pattern  The pattern
     *
     * @return     string
     */
    public function like(string $field, string $pattern): string
    {
        return $field . ' LIKE ' . $this->quote($pattern);
    }

    /**
     * Return an SQL formatted REGEXP clause
     *
     * @param      string  $value  The value
     *
     * @return     string
     */
    public function regexp(string $value): string
    {
        if (dotclear()->con()->syntax() == 'mysql') {
            $clause = "REGEXP '^" . $this->escape(preg_quote($value)) . "[0-9]+$'";
        } elseif (dotclear()->con()->syntax() == 'postgresql') {
            $clause = "~ '^" . $this->escape(preg_quote($value)) . "[0-9]+$'";
        } else {
            $clause = "LIKE '" .
            $this->escape(preg_replace(['%', '_', '!'], ['!%', '!_', '!!'], $value)) . "%' ESCAPE '!'"; // @phpstan-ignore-line
        }

        return $clause;
    }

    /**
     * Return an DISTINCT clause
     *
     * @param      string       $field     The field
     *
     * @return     string
     */
    public function unique(string $field): string
    {
        return 'DISTINCT ' . $field;
    }

    /**
     * Return an COUNT(…) clause
     *
     * @param      string       $field     The field
     * @param      null|string  $as        Optional alias
     * @param      bool         $unique    Unique values only
     *
     * @return     string
     */
    public function count(string $field, ?string $as = null, bool $unique = false): string
    {
        return 'COUNT(' . ($unique ? $this->unique($field) : $field) . ')' . ($as ? ' as ' . $as : '');
    }

    /**
     * Return an AVG(…) clause
     *
     * @param      string       $field     The field
     * @param      null|string  $as        Optional alias
     *
     * @return     string
     */
    public function avg(string $field, ?string $as = null): string
    {
        return 'AVG(' . $field . ')' . ($as ? ' as ' . $as : '');
    }

    /**
     * Return an MAX(…) clause
     *
     * @param      string       $field     The field
     * @param      null|string  $as        Optional alias
     *
     * @return     string
     */
    public function max(string $field, ?string $as = null): string
    {
        return 'MAX(' . $field . ')' . ($as ? ' as ' . $as : '');
    }

    /**
     * Return an MIN(…) clause
     *
     * @param      string       $field     The field
     * @param      null|string  $as        Optional alias
     *
     * @return     string
     */
    public function min(string $field, ?string $as = null): string
    {
        return 'MIN(' . $field . ')' . ($as ? ' as ' . $as : '');
    }

    /**
     * Return an SUM(…) clause
     *
     * @param      string       $field     The field
     * @param      null|string  $as        Optional alias
     *
     * @return     string
     */
    public function sum(string $field, ?string $as = null): string
    {
        return 'SUM(' . $field . ')' . ($as ? ' as ' . $as : '');
    }

    /**
     * Compare two SQL queries
     *
     * May be used for debugging purpose as:
     *
     * if (!$sql->isSame($sql->statement(), $strReq)) {
     *     trigger_error('SQL statement error: ' . $sql->statement() . ' / ' . $strReq, E_USER_ERROR);
     * }
     *
     * @param      string   $local     The local
     * @param      string   $external  The external
     *
     * @return     boolean  True if same, False otherwise.
     */
    public function isSame(string $local, string $external): bool
    {
        $filter = function ($s) {
            $s        = strtoupper($s);
            $patterns = [
                '\s+' => ' ', // Multiple spaces/tabs -> one space
                ' \)' => ')', // <space>) -> )
                ' ,'  => ',', // <space>, -> ,
                '\( ' => '(', // (<space> -> (
            ];
            foreach ($patterns as $pattern => $replace) {
                $s = preg_replace('!' . $pattern . '!', $replace, $s);
            }

            return trim($s);
        };

        return ($filter($local) === $filter($external));
    }

    /**
     * Compare local statement and external one
     *
     * @param      string   $external       The external
     * @param      bool     $trigger_error  True to trigger an error if compare failsl
     * @param      bool     $dump           True to var_dump() all if compare fails
     * @param      bool     $print          True to print_r() all if compare fails
     *
     * @return     bool
     */
    public function compare(string $external, bool $trigger_error = false, bool $dump = false, bool $print = false): bool
    {
        $str = $this->statement();
        if (!$this->isSame($str, $external)) {
            if ($print) {
                print_r($str);
                print_r($external);
            } elseif ($dump) {
                var_dump($str);
                var_dump($external);
            }
            if ($trigger_error) {
                trigger_error('SQL statement error (internal/external): ' . $str . ' / ' . $external, E_USER_ERROR);
            }

            return false;
        }

        return true;
    }
}

