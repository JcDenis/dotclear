<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

// Dotclear\Database\InterfaceConnection

/**
 * PHP Interface for connector.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Database Connection
 */
interface InterfaceConnection
{
    /**
     * Open connection.
     *
     * This method should open a database connection and return a new resource
     * link.
     *
     * @param string $host     Database server host
     * @param string $user     Database user name
     * @param string $password Database password
     * @param string $database Database name
     */
    public function db_connect(string $host, string $user, string $password, string $database): mixed;

    /**
     * Open persistent connection.
     *
     * This method should open a persistent database connection and return a new
     * resource link.
     *
     * @param string $host     Database server host
     * @param string $user     Database user name
     * @param string $password Database password
     * @param string $database Database name
     */
    public function db_pconnect(string $host, string $user, string $password, string $database): mixed;

    /**
     * Close connection.
     *
     * This method should close resource link.
     *
     * @param mixed $handle Resource link
     */
    public function db_close(mixed $handle): void;

    /**
     * Database version.
     *
     * This method should return database version number.
     *
     * @param mixed $handle Resource link
     */
    public function db_version(mixed $handle): string;

    /**
     * Database query.
     *
     * This method should run an SQL query and return a resource result.
     *
     * @param mixed  $handle Resource link
     * @param string $query  SQL query string
     */
    public function db_query(mixed $handle, string $query): mixed;

    /**
     * Database exec query.
     *
     * This method should run an SQL query and return a resource result.
     *
     * @param mixed  $handle Resource link
     * @param string $query  SQL query string
     */
    public function db_exec(mixed $handle, string $query): mixed;

    /**
     * Result columns count.
     *
     * This method should return the number of fields in a result.
     *
     * @param mixed $res Resource result
     */
    public function db_num_fields(mixed $res): int;

    /**
     * Result rows count.
     *
     * This method should return the number of rows in a result.
     *
     * @param mixed $res Resource result
     */
    public function db_num_rows(mixed $res): int;

    /**
     * Field name.
     *
     * This method should return the name of the field at the given position
     * <var>$position</var>.
     *
     * @param mixed $res      Resource result
     * @param int   $position Field position
     */
    public function db_field_name(mixed $res, int $position): string;

    /**
     * Field type.
     *
     * This method should return the field type a the given position
     * <var>$position</var>.
     *
     * @param mixed $res      Resource result
     * @param int   $position Field position
     */
    public function db_field_type(mixed $res, int $position): string;

    /**
     * Fetch result.
     *
     * This method should fetch one line of result and return an associative array
     * with field name as key and field value as value.
     *
     * @param mixed $res Resource result
     */
    public function db_fetch_assoc(mixed $res): array|false;

    /**
     * Move result cursor.
     *
     * This method should move result cursor on given row position <var>$row</var>
     * and return true on success.
     *
     * @param mixed $res Resource result
     * @param int   $row Row position
     */
    public function db_result_seek(mixed $res, int $row): bool;

    /**
     * Affected rows.
     *
     * This method should return number of rows affected by INSERT, UPDATE or
     * DELETE queries.
     *
     * @param mixed $handle Resource link
     * @param mixed $res    Resource result
     */
    public function db_changes(mixed $handle, mixed $res): int;

    /**
     * Last error.
     *
     * This method should return the last error string for the current connection.
     *
     * @param mixed $handle Resource link
     */
    public function db_last_error(mixed $handle): string|false;

    /**
     * Escape string.
     *
     * This method should return an escaped string for the current connection.
     *
     * @param null|string $str    String to escape
     * @param mixed       $handle Resource link
     */
    public function db_escape_string(?string $str, mixed $handle = null): string;

    /**
     * Acquiere Write lock.
     *
     * This method should lock the given table in write access.
     *
     * @param string $table Table name
     */
    public function db_write_lock(string $table): void;

    /**
     * Release lock.
     *
     * This method should releases an acquiered lock.
     */
    public function db_unlock(): void;

    /**
     * Vaccum a table.
     *
     * @param string $table Table name
     */
    public function db_vacuum(string $table): void;
}
