<?php
/**
 * @note Dotclear\Database\AbstractSchema
 * @brief Database schema manipulator
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Database
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

abstract class AbstractSchema implements InterfaceSchema
{
    /**
     * Constructor.
     *
     * @param AbstractConnection $con database connection instance
     */
    public function __construct(protected AbstractConnection $con)
    {
    }

    /**
     * Initialize database schema handler.
     *
     * @param AbstractConnection $con database connection instance
     */
    public static function init(AbstractConnection $con): AbstractSchema
    {
        $driver = $con->driver();
        $parent = __CLASS__;
        $class  = '';

        // Set full namespace of distributed database driver
        if (in_array($driver, ['mysqli', 'mysqlimb4', 'pgsql', 'sqlite'])) {
            $class = 'Dotclear\\Database\\Driver\\' . ucfirst($driver) . '\\Schema';
        }

        // You can set \DOTCLEAR_SCH_CLASS to whatever you want.
        // Your new class *should* inherits Dotclear\Database\Schema class.
        $class = defined('DOTCLEAR_SCH_CLASS') ? \DOTCLEAR_SCH_CLASS : $class;

        if (!class_exists($class) || !is_subclass_of($class, $parent)) {
            trigger_error('Database schema class ' . $class . ' does not exist or does not inherit ' . $parent);

            exit(1);
        }

        if (!class_exists($class)) {
            trigger_error('Unable to load DB schema for ' . $driver, E_USER_ERROR);
        }

        return new $class($con);
    }

    /**
     * Database data type to universal data type conversion.
     *
     * @param string $type    Type name
     * @param int    $len     Field length (in/out)
     * @param mixed  $default Default field value (in/out)
     */
    public function dbt2udt(string $type, ?int &$len, mixed &$default): string
    {
        $c = [
            'bool'              => 'boolean',
            'int2'              => 'smallint',
            'int'               => 'integer',
            'int4'              => 'integer',
            'int8'              => 'bigint',
            'float4'            => 'real',
            'double precision'  => 'float',
            'float8'            => 'float',
            'decimal'           => 'numeric',
            'character varying' => 'varchar',
            'character'         => 'char',
        ];

        return $c[$type] ?? $type;
    }

    /**
     * Universal data type to database data tye conversion.
     *
     * @param string $type    Type name
     * @param int    $len     Field length (in/out)
     * @param mixed  $default Default field value (in/out)
     */
    public function udt2dbt(string $type, ?int &$len, mixed &$default): string
    {
        return $type;
    }

    /**
     * Returns an array of all table names.
     *
     * @see     interfaceSchema::db_get_tables
     */
    public function getTables(): array
    {
        return $this->db_get_tables();
    }

    /**
     * Returns an array of columns (name and type) of a given table.
     *
     * @see     interfaceSchema::db_get_columns
     *
     * @param string $table Table name
     */
    public function getColumns(string $table): array
    {
        return $this->db_get_columns($table);
    }

    /**
     * Returns an array of index of a given table.
     *
     * @see     interfaceSchema::db_get_keys
     *
     * @param string $table Table name
     */
    public function getKeys(string $table): array
    {
        return $this->db_get_keys($table);
    }

    /**
     * Returns an array of indexes of a given table.
     *
     * @see     interfaceSchema::db_get_index
     *
     * @param string $table Table name
     */
    public function getIndexes(string $table): array
    {
        return $this->db_get_indexes($table);
    }

    /**
     * Returns an array of foreign keys of a given table.
     *
     * @see     interfaceSchema::db_get_references
     *
     * @param string $table Table name
     */
    public function getReferences(string $table): array
    {
        return $this->db_get_references($table);
    }

    public function createTable(string $name, array $fields): void
    {
        $this->db_create_table($name, $fields);
    }

    public function createField(string $table, string $name, string $type, ?int $len, bool $null, mixed $default): void
    {
        $this->db_create_field($table, $name, $type, $len, $null, $default);
    }

    public function createPrimary(string $table, string $name, array $cols): void
    {
        $this->db_create_primary($table, $name, $cols);
    }

    public function createUnique(string $table, string $name, array $cols): void
    {
        $this->db_create_unique($table, $name, $cols);
    }

    public function createIndex(string $table, string $name, string $type, array $cols): void
    {
        $this->db_create_index($table, $name, $type, $cols);
    }

    public function createReference(string $name, string $c_table, array $c_cols, string $p_table, array $p_cols, string $update, string $delete): void
    {
        $this->db_create_reference($name, $c_table, $c_cols, $p_table, $p_cols, $update, $delete);
    }

    public function alterField(string $table, string $name, string $type, ?int $len, bool $null, $default): void
    {
        $this->db_alter_field($table, $name, $type, $len, $null, $default);
    }

    public function alterPrimary(string $table, string $name, string $newname, array $cols): void
    {
        $this->db_alter_primary($table, $name, $newname, $cols);
    }

    public function alterUnique(string $table, string $name, string $newname, array $cols): void
    {
        $this->db_alter_unique($table, $name, $newname, $cols);
    }

    public function alterIndex(string $table, string $name, string $newname, string $type, array $cols): void
    {
        $this->db_alter_index($table, $name, $newname, $type, $cols);
    }

    public function alterReference(string $name, string $newname, string $c_table, array $c_cols, string $p_table, array $p_cols, string $update, string $delete): void
    {
        $this->db_alter_reference($name, $newname, $c_table, $c_cols, $p_table, $p_cols, $update, $delete);
    }

    public function dropUnique(string $table, string $name): void
    {
        $this->db_drop_unique($table, $name);
    }

    public function flushStack(): void
    {
    }
}
