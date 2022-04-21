<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

// Dotclear\Database\Table
use Dotclear\Exception\DatabaseException;

/**
 * Database table structure manipulator.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Database Structure
 */
class Table
{
    /**
     * @var bool $has_primary
     *           Table has primary key
     */
    protected $has_primary = false;

    /**
     * @var array $fields
     *            Table fields
     */
    protected $fields = [];

    /**
     * @var array $keys
     *            Table keys
     */
    protected $keys = [];

    /**
     * @var array $indexes
     *            Table indexex
     */
    protected $indexes = [];

    /**
     * @var array $references
     *            Table references
     */
    protected $references = [];

    /**
     * @var array $allowed_types
     *            Universal data types supported by dbSchema
     *
     * - SMALLINT    : signed 2 bytes integer
     * - INTEGER    : signed 4 bytes integer
     * - BIGINT    : signed 8 bytes integer
     * - REAL        : signed 4 bytes floating point number
     * - FLOAT    : signed 8 bytes floating point number
     * - NUMERIC    : exact numeric type
     *
     * - DATE        : Calendar date (day, month and year)
     * - TIME        : Time of day
     * - TIMESTAMP    : Date and time
     *
     * - CHAR        : A fixed n-length character string
     * - VARCHAR    : A variable length character string
     * - TEXT        : A variable length of text
     */
    protected $allowed_types = [
        'smallint', 'integer', 'bigint', 'real', 'float', 'numeric',
        'date', 'time', 'timestamp',
        'char', 'varchar', 'text',
    ];

    /**
     * Constructor.
     *
     * @param string $name The table name
     */
    public function __construct(protected string $name)
    {
    }

    /**
     * Get table fields.
     *
     * @return array The table fields
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get table keys.
     *
     * @return array The table keys
     */
    public function getKeys(bool $primary = null): array
    {
        return $this->keys;
    }

    /**
     * Get table indexes.
     *
     * @return array The table indexes
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * Get table references keys.
     *
     * @return array The table references keys
     */
    public function getReferences(): array
    {
        return $this->references;
    }

    /**
     * Check if field exists.
     *
     * @param string $name The field name
     *
     * @return bool True if field exists
     */
    public function fieldExists(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /**
     * Check if key exists.
     *
     * @param string       $name The key name
     * @param string       $type The key type
     * @param array|string $cols The key columns
     *
     * @return false|string False if key not exists, else return its name
     */
    public function keyExists(string $name, string $type, array|string $cols): string|false
    {
        // Look for key with the same name
        if (isset($this->keys[$name])) {
            return $name;
        }

        // Look for key with the same columns list and type
        foreach ($this->keys as $n => $k) {
            if ($k['cols'] == $cols && $k['type'] == $type) {
                // Same columns and type, return new name
                return $n;
            }
        }

        return false;
    }

    /**
     * Check if index exists.
     *
     * @param string $name The index name
     * @param string $type The index type
     * @param array  $cols The index columns
     *
     * @return false|string False if key not exists, else return its name
     */
    public function indexExists(string $name, string $type, array $cols): string|false
    {
        // Look for key with the same name
        if (isset($this->indexes[$name])) {
            return $name;
        }

        // Look for index with the same columns list and type
        foreach ($this->indexes as $n => $i) {
            if ($i['cols'] == $cols && $i['type'] == $type) {
                // Same columns and type, return new name
                return $n;
            }
        }

        return false;
    }

    /**
     * Check if reference exists.
     *
     * @param string $name    The reference name
     * @param array  $c_cols  The reference children columns
     * @param string $p_table The reference parent table
     * @param array  $p_cols  The reference paranet columns
     *
     * @return false|string False if key not exists, else return its name
     */
    public function referenceExists(string $name, array $c_cols, string $p_table, array $p_cols): string|false
    {
        if (isset($this->references[$name])) {
            return $name;
        }

        // Look for reference with same chil columns, parent table and columns
        foreach ($this->references as $n => $r) {
            if ($c_cols == $r['c_cols'] && $p_table == $r['p_table'] && $p_cols == $r['p_cols']) {
                // Only name differs, return new name
                return $n;
            }
        }

        return false;
    }

    /**
     * Set a field.
     *
     * @param string   $name    The field name
     * @param string   $type    The field type
     * @param null|int $len     The field len
     * @param bool     $null    The field can be null
     * @param mixed    $default The field default value
     * @param bool     $to_null Convert to null
     *
     * @return Table The table instance
     */
    public function field(string $name, string $type, ?int $len, bool $null = true, $default = false, bool $to_null = false): Table
    {
        $type = strtolower($type);

        if (!in_array($type, $this->allowed_types)) {
            if ($to_null) {
                $type = null;
            } else {
                throw new DatabaseException('Invalid data type ' . $type . ' in schema');
            }
        }

        $this->fields[$name] = [
            'type'    => $type,
            'len'     => (int) $len,
            'default' => $default,
            'null'    => (bool) $null,
        ];

        return $this;
    }

    /**
     * Set primary key.
     *
     * @see self::newKey()
     *
     * @param string[] $cols
     */
    public function primary(string $name, string ...$cols): Table
    {
        if ($this->has_primary) {
            throw new DatabaseException(sprintf('Table %s already has a primary key', $this->name));
        }

        return $this->newKey('primary', $name, $cols);
    }

    /**
     * Set unique key.
     *
     * @see self::newKey()
     *
     * @param string[] $cols
     */
    public function unique(string $name, string ...$cols): Table
    {
        return $this->newKey('unique', $name, $cols);
    }

    /**
     * Set index key.
     *
     * @param string $name    The index name
     * @param string $type    The index type
     * @param string ...$cols The columns
     *
     * @return Table The table instance
     */
    public function index(string $name, string $type, string ...$cols): Table
    {
        $this->checkCols($cols);

        $this->indexes[$name] = [
            'type' => strtolower($type),
            'cols' => $cols,
        ];

        return $this;
    }

    /**
     * Set reference key.
     *
     * @param string       $name    The reference name
     * @param array|string $c_cols  The child columns
     * @param string       $p_table The parent table
     * @param array|string $p_cols  The parent columns
     * @param false|string $update  The update method
     * @param false|string $delete  The delete method
     *
     * @return Table The table instacne
     */
    public function reference(string $name, array|string $c_cols, string $p_table, array|string $p_cols, string|false $update = false, string|false $delete = false): Table
    {
        if (!is_array($p_cols)) {
            $p_cols = [$p_cols];
        }
        if (!is_array($c_cols)) {
            $c_cols = [$c_cols];
        }

        $this->checkCols($c_cols);

        $this->references[$name] = [
            'c_cols'  => $c_cols,
            'p_table' => $p_table,
            'p_cols'  => $p_cols,
            'update'  => $update,
            'delete'  => $delete,
        ];

        return $this;
    }

    /**
     * Set a new key.
     *
     * @param string $type The key type
     * @param string $name The key name
     * @param array  $cols The key columns
     *
     * @return Table The table instance
     */
    protected function newKey(string $type, string $name, array $cols): Table
    {
        $this->checkCols($cols);

        $this->keys[$name] = [
            'type' => $type,
            'cols' => $cols,
        ];

        if ('primary' == $type) {
            $this->has_primary = true;
        }

        return $this;
    }

    /**
     * Check columns.
     *
     * @param array $cols The columns
     *
     * @throws DatabaseException
     */
    protected function checkCols(array $cols)
    {
        foreach ($cols as $v) {
            if (!preg_match('/^\(.*?\)$/', $v) && !isset($this->fields[$v])) {
                throw new DatabaseException(sprintf('Field %s does not exist in table %s', $v, $this->name));
            }
        }
    }
}
