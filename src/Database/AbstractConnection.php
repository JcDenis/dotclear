<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

// Dotclear\Database\AbstractConnection

/**
 * Database connector.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Database Connection
 */
abstract class AbstractConnection implements InterfaceConnection
{
    /**
     * @var string $__driver
     *             Drvier name
     */
    protected $__driver;

    /**
     * @var string $__syntax
     *             SQL syntax name
     */
    protected $__syntax;

    /**
     * @var string $__version
     *             SQL syntax name
     */
    protected $__version;

    /**
     * @var false|object $__link
     *                   Database resource link
     */
    protected $__link = false;

    /**
     * @var bool|object $__last_result
     *                  Last result resource link
     */
    protected $__last_result = false;

    /**
     * @var string $__database
     *             Database name
     */
    protected $__database;

    /**
     * @var bool $weak_locks
     *           Enables weak locks for some drivers if true
     */
    public static $weak_locks = true;

    /**
     * Start connection.
     *
     * Static function to use to init database layer. Returns a object extending
     * dbLayer.
     *
     * @param string $driver     Driver name
     * @param string $host       Database hostname
     * @param string $database   Database name
     * @param string $user       User ID
     * @param string $password   Password
     * @param bool   $persistent Persistent connection
     */
    public static function init(string $driver, string $host, string $database, string $user = '', string $password = '', bool $persistent = false): AbstractConnection
    {
        $parent = __CLASS__;
        $class  = '';

        // Set full namespace of distributed database driver
        if (in_array($driver, ['mysqli', 'mysqlimb4', 'pgsql', 'sqlite'])) {
            $class = __NAMESPACE__ . '\\Driver\\' . ucfirst($driver) . '\\Connection';
        }

        // You can set \DOTCLEAR_CON_CLASS to whatever you want.
        // Your new class *should* inherits Dotclear\Database\AbstractConnection class.
        $class = defined('DOTCLEAR_CON_CLASS') ? \DOTCLEAR_CON_CLASS : $class;

        if (!class_exists($class) || !is_subclass_of($class, $parent)) {
            trigger_error(sprintf('Database connection class %s does not exist or does not inherit %s', $class, $parent));

            exit(1);
        }

        return new $class($host, $database, $user, $password, $persistent);
    }

    /**
     * Constructor.
     *
     * @param string $host       Database hostname
     * @param string $database   Database name
     * @param string $user       User ID
     * @param string $password   Password
     * @param bool   $persistent Persistent connection
     */
    public function __construct(string $host, string $database, string $user = '', string $password = '', bool $persistent = false)
    {
        if ($persistent) {
            $this->__link = $this->db_pconnect($host, $user, $password, $database);
        } else {
            $this->__link = $this->db_connect($host, $user, $password, $database);
        }

        $this->__version  = $this->db_version($this->__link);
        $this->__database = $database;
    }

    /**
     * Close database connection.
     */
    public function close(): void
    {
        $this->db_close($this->__link);
    }

    /**
     * Get database driver name.
     */
    public function driver(): string
    {
        return $this->__driver;
    }

    /**
     * Get database SQL syntax name.
     */
    public function syntax(): string
    {
        return $this->__syntax;
    }

    /**
     * Get database driver version.
     */
    public function version(): string
    {
        return $this->__version;
    }

    /**
     * Get current database name.
     */
    public function database(): string
    {
        return $this->__database;
    }

    /**
     * Get link resource.
     *
     * @return mixed The resource
     */
    public function link(): mixed
    {
        return $this->__link;
    }

    /**
     * Run query and get results.
     *
     * Executes a query and return a Record object.
     *
     * @param string $sql SQL query
     */
    public function select(string $sql): Record
    {
        $result = $this->db_query($this->__link, $sql);

        $this->__last_result = &$result;

        $info         = [];
        $info['con']  = &$this;
        $info['cols'] = $this->db_num_fields($result);
        $info['rows'] = $this->db_num_rows($result);
        $info['info'] = [];

        for ($i = 0; $i < $info['cols']; ++$i) {
            $info['info']['name'][] = $this->db_field_name($result, $i);
            $info['info']['type'][] = $this->db_field_type($result, $i);
        }

        return new Record($result, $info);
    }

    /**
     * Return an empty record.
     *
     * Return an empty Record object (without any information).
     */
    public function nullRecord(): Record
    {
        $result = false;

        $info         = [];
        $info['con']  = &$this;
        $info['cols'] = 0; // no fields
        $info['rows'] = 0; // no rows
        $info['info'] = ['name' => [], 'type' => []];

        return new Record($result, $info);
    }

    /**
     * Run query.
     *
     * Executes a query and return true if succeed
     *
     * @param string $sql SQL query
     *
     * @return bool True
     */
    public function execute(string $sql): bool
    {
        $result = $this->db_exec($this->__link, $sql);

        $this->__last_result = &$result;

        return true;
    }

    /**
     * Begin transaction.
     *
     * Begins a transaction. Transaction should be {@link commit() commited}
     * or {@link rollback() rollbacked}.
     */
    public function begin(): void
    {
        $this->execute('BEGIN');
    }

    /**
     * Commit transaction.
     *
     * Commits a previoulsy started transaction.
     */
    public function commit(): void
    {
        $this->execute('COMMIT');
    }

    /**
     * Rollback transaction.
     *
     * Rollbacks a previously started transaction.
     */
    public function rollback(): void
    {
        $this->execute('ROLLBACK');
    }

    /**
     * Aquiere write lock.
     *
     * This method lock the given table in write access.
     *
     * @param string $table Table name
     */
    public function writeLock(string $table): void
    {
        $this->db_write_lock($table);
    }

    /**
     * Release lock.
     *
     * This method releases an acquiered lock.
     */
    public function unlock(): void
    {
        $this->db_unlock();
    }

    /**
     * Vacuum the table given in argument.
     *
     * @param string $table Table name
     */
    public function vacuum(string $table): void
    {
        $this->db_vacuum($table);
    }

    /**
     * Changed rows.
     *
     * Returns the number of lines affected by the last DELETE, INSERT or UPDATE
     * query.
     */
    public function changes(): int
    {
        return $this->db_changes($this->__link, $this->__last_result);
    }

    /**
     * Last error.
     *
     * Returns the last database error or false if no error.
     */
    public function error(): string|false
    {
        return $this->db_last_error($this->__link) ?: false;
    }

    /**
     * Date formatting.
     *
     * Returns a query fragment with date formater.
     *
     * The following modifiers are accepted:
     *
     * - %d : Day of the month, numeric
     * - %H : Hour 24 (00..23)
     * - %M : Minute (00..59)
     * - %m : Month numeric (01..12)
     * - %S : Seconds (00..59)
     * - %Y : Year, numeric, four digits
     *
     * @param string $field   Field name
     * @param string $pattern Date format
     */
    public function dateFormat(string $field, string $pattern): string
    {
        return 'TO_CHAR(' . $field . ',' . "'" . $this->escape($pattern) . "') ";
    }

    /**
     * Query Limit.
     *
     * Returns a LIMIT query fragment. <var>$arg1</var> could be an array of
     * offset and limit or an integer which is only limit. If <var>$arg2</var>
     * is given and <var>$arg1</var> is an integer, it would become limit.
     *
     * @param array|int  $arg1 array or integer with limit intervals
     * @param null|array $arg2 integer or null
     */
    public function limit(array|int $arg1, array|null $arg2 = null): string
    {
        if (is_array($arg1)) {
            $arg1 = array_values($arg1);
            $arg2 = $arg1[1] ?? null;
            $arg1 = $arg1[0];
        }

        if (null === $arg2) {
            $sql = ' LIMIT ' . (int) $arg1 . ' ';
        } else {
            $sql = ' LIMIT ' . (int) $arg2 . ' OFFSET ' . (int) $arg1 . ' ';
        }

        return $sql;
    }

    /**
     * IN fragment.
     *
     * Returns a IN query fragment where $in could be an array, a string,
     * an integer or null
     *
     * @param null|array|int|string $in "IN" values
     */
    public function in(array|string|int|null $in): string
    {
        if (is_null($in)) {
            return ' IN (NULL) ';
        }
        if (is_string($in)) {
            return " IN ('" . $this->escape($in) . "') ";
        }
        if (is_array($in)) {
            foreach ($in as $i => $v) {
                if (is_null($v)) {
                    $in[$i] = 'NULL';
                } elseif (is_string($v)) {
                    $in[$i] = "'" . $this->escape($v) . "'";
                }
            }

            return ' IN (' . implode(',', $in) . ') ';
        }

        return ' IN ( ' . (int) $in . ') ';
    }

    /**
     * ORDER BY fragment.
     *
     * Returns a ORDER BY query fragment where arguments could be an array or a string
     *
     * array param:
     *    key        : decription
     *    field    : field name (string)
     *    collate    : True or False (bool) (Alphabetical order / Binary order)
     *    order    : ASC or DESC (string) (Ascending order / Descending order)
     *
     * string param field name (Binary ascending order)
     */
    public function orderBy(): string
    {
        $default = [
            'order'   => '',
            'collate' => false,
        ];
        foreach (func_get_args() as $v) {
            if (is_string($v)) {
                $res[] = $v;
            } elseif (is_array($v) && !empty($v['field'])) {
                $v          = array_merge($default, $v);
                $v['order'] = (strtoupper($v['order']) == 'DESC' ? 'DESC' : '');
                $res[]      = ($v['collate'] ? 'LOWER(' . $v['field'] . ')' : $v['field']) . ' ' . $v['order'];
            }
        }

        return empty($res) ? '' : ' ORDER BY ' . implode(',', $res) . ' ';
    }

    /**
     * Field name(s) fragment (using generic UTF8 collating sequence if available else using SQL LOWER function).
     *
     * Returns a fields list where args could be an array or a string
     *
     * array param: list of field names
     * string param: field name
     */
    public function lexFields(): string
    {
        $fmt = 'LOWER(%s)';
        foreach (func_get_args() as $v) {
            if (is_string($v)) {
                $res[] = sprintf($fmt, $v);
            } elseif (is_array($v)) {
                $res = array_map(fn ($i) => sprintf($fmt, $i), $v);
            }
        }

        return empty($res) ? '' : implode(',', $res);
    }

    /**
     * Concat strings.
     *
     * Returns SQL concatenation of methods arguments. Theses arguments
     * should be properly escaped when needed.
     */
    public function concat(): string
    {
        $args = func_get_args();

        return implode(' || ', $args);
    }

    /**
     * Escape string.
     *
     * Returns SQL protected string or array values.
     *
     * @param null|array|string $i String or array to protect
     */
    public function escape(string|array|null $i): string|array|null
    {
        if (is_array($i)) {
            foreach ($i as $k => $s) {
                $i[$k] = $this->db_escape_string($s, $this->__link);
            }

            return $i;
        }

        return $this->db_escape_string($i, $this->__link);
    }

    /**
     * System escape string.
     *
     * Returns SQL system protected string.
     *
     * @param string $str String to protect
     */
    public function escapeSystem(string $str): string
    {
        return '"' . $str . '"';
    }

    /**
     * Cursor object.
     *
     * Returns a new instance of {@link Cursor} class on <var>$table</var> for
     * the current connection.
     *
     * @param string $table Target table
     */
    public function openCursor(string $table): Cursor
    {
        return new Cursor($this, $table);
    }
}
