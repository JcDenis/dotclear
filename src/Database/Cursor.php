<?php
/**
 * @class Dotclear\Database\Cursor
 * @brief Database cursor
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage Database
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

use Dotclear\Database\AbstractConnection;
use Dotclear\Exception\DatabaseException;

class Cursor
{
    private $__data = [];

    /**
     * Constructor
     *
     * Init cursor object on a given table. Note that you can init it with
     * {@link Layer::openCursor() openCursor()} method of your connection object.
     *
     * Example:
     * <code>
     * <?php
     *    $cur = $con->openCursor('table');
     *    $cur->setField('field1', 1);
     *    $cur->setField('field2', 'foo');
     *    $cur->insert(); // Insert field ...
     *
     *    $cur->update('WHERE field3 = 4'); // ... or update field
     * ?>
     * </code>
     *
     * @see     AbstractConnection::openCursor()
     * 
     * @param   AbstractConnection  $__con      AbstractConnection object
     * @param   string              $__table    Table name
     */
    public function __construct(private AbstractConnection $__con, private string $__table)
    {
    }

    /**
     * Set table
     *
     * Changes working table and resets data
     *
     * @param   string  $table  Table name
     */
    public function setTable(string $table): void
    {
        $this->__table = $table;
        $this->__data  = [];
    }

    /**
     * Set field
     *
     * Set value <var>$v</var> to a field named <var>$n</var>. Value could be
     * an string, an integer, a float, a null value or an array.
     *
     * If value is an array, its first value will be interpreted as a SQL
     * command. String values will be automatically escaped.
     *
     * @param   string  $n  Field name
     * @param   mixed   $v  Field value
     */
    public function setField(string $n, mixed $v): void
    {
        $this->__data[$n] = $v;
    }

    /**
     * Unset field
     *
     * Remove a field from data set.
     *
     * @param   string  $n  Field name
     */
    public function unsetField(string $n): void
    {
        unset($this->__data[$n]);
    }

    /**
     * Field exists
     *
     * @return  bool    True if field named <var>$n</var> exists
     */
    public function isField(string $n): bool
    {
        return isset($this->__data[$n]);
    }

    /**
     * Field value
     *
     * @return  mixed   Value for a field named <var>$n</var>
     */
    public function getField(string $n): mixed
    {
        return isset($this->__data[$n]) ? $this->__data[$n] : null;
    }

    /**
     * @see self::setField()
     */
    public function __set(string $n, $v): void
    {
        $this->setField($n, $v);
    }

    /**
     * @see self::getField()
     */
    public function __get(string $n)
    {
        return $this->getField($n);
    }

    /**
     * Empty data set
     *
     * Removes all data from data set
     */
    public function clean(): void
    {
        $this->__data = [];
    }

    private function formatFields(): array
    {
        $data = [];

        foreach ($this->__data as $k => $v) {
            $k = $this->__con->escapeSystem($k);

            if (is_null($v)) {
                $data[$k] = 'NULL';
            } elseif (is_string($v)) {
                $data[$k] = "'" . $this->__con->escape($v) . "'";
            } elseif (is_array($v)) {
                $data[$k] = is_string($v[0]) ? "'" . $this->__con->escape($v[0]) . "'" : $v[0];
            } else {
                $data[$k] = $v;
            }
        }

        return $data;
    }

    /**
     * Get insert query
     *
     * Returns the generated INSERT query
     *
     * @return  string
     */
    public function getInsert(): string
    {
        $data = $this->formatFields();

        $insReq = 'INSERT INTO ' . $this->__con->escapeSystem($this->__table) . " (\n" .
        implode(",\n", array_keys($data)) . "\n) VALUES (\n" .
        implode(",\n", array_values($data)) . "\n) ";

        return $insReq;
    }

    /**
     * Get update query
     *
     * Returns the generated UPDATE query
     *
     * @param   string  $where  WHERE condition
     * 
     * @return  string
     */
    public function getUpdate(string $where): string
    {
        $data   = $this->formatFields();
        $fields = [];

        $updReq = 'UPDATE ' . $this->__con->escapeSystem($this->__table) . " SET \n";

        foreach ($data as $k => $v) {
            $fields[] = $k . ' = ' . $v . '';
        }

        $updReq .= implode(",\n", $fields);
        $updReq .= "\n" . $where;

        return $updReq;
    }

    /**
     * Execute insert query
     *
     * Executes the generated INSERT query
     */
    public function insert(): bool
    {
        if (!$this->__table) {
            throw new DatabaseException('No table name.');
        }

        $insReq = $this->getInsert();

        $this->__con->execute($insReq);

        return true;
    }

    /**
     * Execute update query
     *
     * Executes the generated UPDATE query
     *
     * @param   string  $where  WHERE condition
     */
    public function update(string $where): bool
    {
        if (!$this->__table) {
            throw new DatabaseException('No table name.');
        }

        $updReq = $this->getUpdate($where);

        $this->__con->execute($updReq);

        return true;
    }
}
