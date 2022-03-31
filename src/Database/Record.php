<?php
/**
 * @class Dotclear\Database\Record
 * @brief Database record
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

use Iterator;
use Countable;
use Dotclear\Database\StaticRecord;

class Record implements Iterator, Countable
{
    protected $__link;             ///< resource: Database resource link
    protected $__extend = []; ///< array: List of static functions that extend record
    protected $__index  = 0;       ///< integer: Current result position
    protected $__row    = false;   ///< array: Current result row content

    private $__fetch = false;

    /**
     * Constructor
     *
     * Creates class instance from result link and some informations.
     * <var>$info</var> is an array with the following content:
     *
     * - con => database object instance
     * - cols => number of columns
     * - rows => number of rows
     * - info[name] => an array with columns names
     * - info[type] => an array with columns types
     *
     * @param   mixed   $__result   Resource result
     * @param   array   $__info     Information array
     */
    public function __construct(protected mixed $__result, protected array $__info)
    {
        $this->__link = $this->__info['con']->link();
        $this->index(0);
    }

    /**
     * To staticRecord
     *
     * Converts this record to a {@link StaticRecord} instance.
     * 
     * @return  StaticRecord
     */
    public function toStatic(): StaticRecord
    {
        return  ($this instanceof StaticRecord) ? $this : new StaticRecord($this->__result, $this->__info);
    }

    /**
     * Magic call
     *
     * Magic call function. Calls function added by {@link extend()} if exists, passing it
     * self object and arguments.
     *
     * @return  mixed
     */
    public function __call(string $function, array $args): mixed
    {
        if (isset($this->__extend[$function])) {

            return call_user_func_array($this->__extend[$function], $args);
        }

        trigger_error('Call to undefined method Record::' . $function . '()', E_USER_ERROR);
    }

    /**
     * @see     self::field()
     */
    public function __get(string|int $field): mixed
    {
        return $this->field($field);
    }

    /**
     * @see     self::field()
     */
    public function f(string|int $field): mixed
    {
        return $this->field($field);
    }

    /**
     * Get field
     *
     * Retrieve field value by its name or column position.
     *
     * @param   string|int  $field  Field name
     * 
     * @return  mixed
     */
    public function field(string|int $field): mixed
    {
        if (method_exists($this->__info['con'], 'db_field_cast')) {
            $types = is_numeric($field) ?
                $this->__info['info']['type'] :
                array_combine($this->__info['info']['name'], $this->__info['info']['type']);

            return $this->__info['con']->db_field_cast($this->__row[$field], $types[$field]);
        }

        return $this->__row[$field];
    }

    /**
     * Field exists
     *
     * Returns true if a field exists.
     *
     * @param   string  $field  Field name
     * 
     * @return  bool
     */
    public function exists(string $field): bool
    {
        return isset($this->__row[$field]);
    }

    /**
     * @see     self::exists()
     */
    public function __isset(string $field): bool
    {
        return $this->exists($field);
    }

    /**
     * Extend record
     *
     * Extends this instance capabilities by adding all public static methods of
     * <var>$class</var> to current instance. Class methods should take at least
     * this record as first parameter.
     *
     * @see     self::__call()
     *
     * @param   RecordExtend    $class  Class
     */
    public function extend(RecordExtend $class): void
    {
        if (!is_a($class, __NAMESPACE__ . '\\RecordExtend')) {
            return;
        }

        $class->setRecord($this);

        $c = new \ReflectionClass($class);
        foreach ($c->getMethods() as $m) {
            if ($m->isPublic()) {
                $this->__extend[$m->name] = [$class, $m->name];
            }
        }
    }

    /**
     * Returns record extensions.
     *
     * @return  array
     */
    public function extensions(): array
    {
        return $this->__extend;
    }

    private function setRow(): bool
    {
        $this->__row = $this->__info['con']->db_fetch_assoc($this->__result);

        if ($this->__row !== false) {
            foreach ($this->__row as $k => $v) {
                $this->__row[] = &$this->__row[$k];
            }

            return true;
        }

        return false;
    }

    /**
     * Returns the current index position (0 is first) or move to <var>$row</var> if
     * specified.
     *
     * @param   int         $row    Row number to move
     * 
     * @return  int|bool
     */
    public function index(int $row = null): int|bool
    {
        if ($row === null) {
            return $this->__index === null ? 0 : $this->__index;
        }

        if ($row < 0 || $row + 1 > $this->__info['rows']) {
            return false;
        }

        if ($this->__info['con']->db_result_seek($this->__result, (int) $row)) {
            $this->__index = $row;
            $this->setRow();
            $this->__info['con']->db_result_seek($this->__result, (int) $row);

            return true;
        }

        return false;
    }

    /**
     * One step move index
     *
     * This method moves index forward and return true until index is not
     * the last one. You can use it to loop over record. Example:
     * <code>
     * <?php
     * while ($rs->fetch()) {
     *     echo $rs->field1;
     * }
     * ?>
     * </code>
     *
     * @return  bool
     */
    public function fetch(): bool
    {
        if (!$this->__fetch) {
            $this->__fetch = true;
            $i             = -1;
        } else {
            $i = $this->__index;
        }

        if (!$this->index($i + 1)) {
            $this->__fetch = false;
            $this->__index = 0;

            return false;
        }

        return true;
    }

    /**
     * Moves index to first position.
     *
     * @return  int|bool
     */
    public function moveStart(): int|bool
    {
        $this->__fetch = false;

        return $this->index(0);
    }

    /**
     * Moves index to last position.
     *
     * @return  int|bool
     */
    public function moveEnd(): int|bool
    {
        return $this->index($this->__info['rows'] - 1);
    }

    /**
     * Moves index to next position.
     *
     * @return  int|bool
     */
    public function moveNext(): int|bool
    {
        return $this->index($this->__index + 1);
    }

    /**
     * Moves index to previous position.
     *
     * @return  int|bool
     */
    public function movePrev(): int|bool
    {
        return $this->index($this->__index - 1);
    }

    /**
     * Check if it is end position
     * 
     * @return  bool    true if index is at last position
     */
    public function isEnd(): bool
    {
        return $this->__index + 1 == $this->count();
    }

    /**
     * Check if it is start position
     * 
     * @return  bool    true if index is at first position.
     */
    public function isStart(): bool
    {
        return $this->__index <= 0;
    }

    /**
     * Chek if record is empty
     * 
     * @return  bool    true if record contains no result.
     */
    public function isEmpty(): bool
    {
        return $this->count() == 0;
    }

    /**
     * Get number of rows
     * 
     * @return  int     number of rows in record
     */
    #[\ReturnTypeWillChange]
    public function count(): int
    {
        return $this->__info['rows'];
    }

    /**
     * Get columns
     * 
     * @return  array   array of columns, with name as key and type as value.
     */
    public function columns(): array
    {
        return $this->__info['info']['name'];
    }

    /**
     * Get all rows
     * 
     * @return  array    all rows in record.
     */
    public function rows(): array
    {
        return $this->getData();
    }

    /**
     * Get all data
     *
     * Returns an array of all rows in record. This method is called by rows().
     *
     * @return  array
     */
    protected function getData(): array
    {
        $res = [];

        if ($this->count() == 0) {
            return $res;
        }

        $this->__info['con']->db_result_seek($this->__result, 0);
        while (($r = $this->__info['con']->db_fetch_assoc($this->__result)) !== false) {
            foreach ($r as $k => $v) {
                $r[] = &$r[$k];
            }
            $res[] = $r;
        }
        $this->__info['con']->db_result_seek($this->__result, $this->__index);

        return $res;
    }

    /**
     * Get current row
     * 
     * @return  array    current rows.
     */
    public function row(): array
    {
        return $this->__row;
    }

    /* Iterator methods */

    /**
     * @see     Iterator::current
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this;
    }

    /**
     * @see     Iterator::key
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->index();
    }
    /**
     * @see     Iterator::next
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->fetch();
    }

    /**
     * @see     Iterator::rewind
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->moveStart();
        $this->fetch();
    }

    /**
     * @see     Iterator::valid
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->__fetch;
    }

    /**
     * Return as integer the field result
     *
     * Usefull with 'count only' request
     *
     * @param   string|int  $n  The field
     *
     * @return  int
     */
    public function fInt(string|int $n = 0): int
    {
        return $this->count() == 0 ? 0 : (int) $this->field($n);
    }
}
