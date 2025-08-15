<?php

/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Database;

use Dotclear\Helper\Text;

/**
 * @class StaticRecord
 *
 * Query Result Static Record Class
 *
 * Unlike Record parent class, this one contains all results in an associative array.
 *
 * @psalm-no-seal-properties
 * @psalm-no-seal-methods
 */
class StaticRecord extends Record
{
    /**
     * Data arrat
     *
     * @var        array<mixed[]>   $__data
     */
    public $__data = [];

    /**
     * Sort field name
     *
     * @var string|int|null     $__sortfield
     */
    private $__sortfield;

    /**
     * Sort order (1 or -1)
     */
    private ?int $__sortsign = null;

    /**
     * Constructs a new instance.
     *
     * @param      mixed                        $result  The result
     * @param      null|array<string, mixed>    $info    The information
     */
    public function __construct($result, ?array $info)
    {
        if (is_array($result)) {
            $this->__info = $info ?? [];
            $this->__data = $result;
        } else {
            parent::__construct($result, $info ?? []);
            $this->__data = parent::getData();
        }
    }

    /**
     * Static record from array
     *
     * Returns a new instance of object from an associative array.
     *
     * @param array<mixed>        $data        Data array
     */
    public static function newFromArray(?array $data): StaticRecord
    {
        if (!is_array($data)) {
            $data = [];
        }

        $data = array_values($data);

        $cols = $data === [] || !is_array($data[0]) ? 0 : count($data[0]);

        $info = [
            'con'  => null,
            'info' => null,
            'cols' => $cols,
            'rows' => count($data),
        ];

        return new self($data, $info);
    }

    /**
     * Get field value
     *
     * @param      string|int  $n      Field name|position
     *
     * @return     mixed
     */
    public function field($n)
    {
        return $this->__data[$this->__index][$n] ?? null;
    }

    /**
     * Check if a field exists
     *
     * @param      string|int  $n      Field name|position
     */
    public function exists($n): bool
    {
        return isset($this->__data[$this->__index][$n]);
    }

    /**
     * Get current index
     *
     * @param      int   $row    The row
     *
     * @return     bool|int
     */
    public function index(?int $row = null)
    {
        if ($row === null) {
            return $this->__index;
        }

        if ($row < 0 || $row + 1 > $this->__info['rows']) {
            return false;
        }

        $this->__index = $row;

        return true;
    }

    /**
     * @return array<mixed>    current rows.
     */
    public function row(): array
    {
        return $this->__data[$this->__index] ?? [];
    }

    /**
     * Get record rows
     *
     * @return     array<array<mixed>>
     */
    public function rows(): array
    {
        return $this->__data;
    }

    /**
     * Changes value of a given field in the current row.
     *
     * @param string|int    $n            Field name|position
     * @param mixed         $v            Field value
     */
    public function set($n, $v): ?bool
    {
        if ($this->__index === null) {
            return false;
        }

        $this->__data[$this->__index][$n] = $v;

        return null;
    }

    /**
     * Sorts values by a field in a given order.
     *
     * @param string|int    $field        Field name|position
     * @param string        $order        Sort type (asc or desc)
     */
    public function sort($field, string $order = 'asc'): ?bool
    {
        if (!isset($this->__data[0][$field])) {
            return false;
        }

        $this->__sortfield = $field;
        $this->__sortsign  = strtolower($order) === 'asc' ? 1 : -1;

        usort($this->__data, $this->sortCallback(...));

        $this->__sortfield = null;
        $this->__sortsign  = null;

        return null;
    }

    /**
     * Sort callback
     *
     * @param      array<mixed>   $a      First term to compare
     * @param      array<mixed>   $b      Second term to compare
     */
    private function sortCallback(array $a, array $b): int
    {
        $a = $a[$this->__sortfield];
        $b = $b[$this->__sortfield];

        # Integer values
        if ($a == (string) (int) $a && $b == (string) (int) $b) {
            $a = (int) $a;
            $b = (int) $b;

            return ($a - $b) * $this->__sortsign;
        }

        return strcmp((string) $a, (string) $b) * $this->__sortsign;
    }

    /**
     * Lexically sort.
     *
     * @param      string  $field  The field
     * @param      string  $order  The order
     */
    public function lexicalSort(string $field, string $order = 'asc'): void
    {
        $this->__sortfield = $field;
        $this->__sortsign  = strtolower($order) === 'asc' ? 1 : -1;

        usort($this->__data, $this->lexicalSortCallback(...));

        $this->__sortfield = null;
        $this->__sortsign  = null;
    }

    /**
     * Lexical sort callback
     *
     * @param      mixed   $a      First term to compare
     * @param      mixed   $b      Second term to compare
     */
    private function lexicalSortCallback($a, $b): int
    {
        if (!isset($a[$this->__sortfield]) || !isset($b[$this->__sortfield])) {
            return 0;
        }

        $a = $a[$this->__sortfield];
        $b = $b[$this->__sortfield];

        # Integer values
        if ($a == (string) (int) $a && $b == (string) (int) $b) {
            $a = (int) $a;
            $b = (int) $b;

            return ($a - $b) * $this->__sortsign;
        }

        return strcoll(strtolower(Text::removeDiacritics($a)), strtolower(Text::removeDiacritics($b))) * $this->__sortsign;
    }
}
