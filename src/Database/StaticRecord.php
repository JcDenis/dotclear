<?php
/**
 * @note Dotclear\Database\StaticRecord
 * @brief Database static record
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

use Dotclear\Helper\Lexical;

class StaticRecord extends Record
{
    /** @var array The record data */
    public $__data = [];

    /** @var null|string The sort field */
    private $__sortfield;

    /** @var null|int The sort order */
    private $__sortsign;

    /**
     * Constructor.
     *
     * @param mixed $__result Resource result or result array
     * @param array $__info   Information array
     */
    public function __construct(mixed $__result, array $__info)
    {
        if (is_array($__result)) {
            $this->__info = $__info;
            $this->__data = $__result;
        } else {
            parent::__construct($__result, $__info);
            $this->__data = parent::getData();
        }

        unset($this->__link, $this->__result);
    }

    /**
     * Static record from array.
     *
     * Returns a new instance of object from an associative array.
     *
     * @param null|array $data Data array
     */
    public static function newFromArray(?array $data): StaticRecord
    {
        if (!is_array($data)) {
            $data = [];
        }

        $data = array_values($data);

        if (empty($data) || !is_array($data[0])) {
            $cols = 0;
        } else {
            $cols = count($data[0]);
        }

        $info = [
            'con'  => null,
            'info' => null,
            'cols' => $cols,
            'rows' => count($data),
        ];

        return new self($data, $info);
    }

    public function field(string|int $n): mixed
    {
        return $this->__data[$this->__index][$n];
    }

    public function exists(string $n): bool
    {
        return isset($this->__data[$this->__index][$n]);
    }

    public function index(int $row = null): int|bool
    {
        if (null === $row) {
            return $this->__index;
        }

        if (0 > $row || $row + 1 > $this->__info['rows']) {
            return false;
        }

        $this->__index = $row;

        return true;
    }

    public function rows(): array
    {
        return $this->__data;
    }

    /**
     * Changes value of a given field in the current row.
     *
     * @param string $n Field name
     * @param mixed  $v Field value
     *
     * @return bool Success
     */
    public function set(string $n, mixed $v): bool
    {
        if (null === $this->__index) {
            return false;
        }

        $this->__data[$this->__index][$n] = $v;

        return true;
    }

    /**
     * Sorts values by a field in a given order.
     *
     * @param string $field Field name
     * @param string $order Sort type (asc or desc)
     *
     * @return bool Success
     */
    public function sort(string $field, string $order = 'asc'): bool
    {
        if (!isset($this->__data[0][$field])) {
            return false;
        }

        $this->__sortfield = $field;
        $this->__sortsign  = strtolower($order) == 'asc' ? 1 : -1;

        usort($this->__data, [$this, 'sortCallback']);

        $this->__sortfield = null;
        $this->__sortsign  = null;

        return true;
    }

    private function sortCallback(array $a, array $b): int
    {
        $a = $a[$this->__sortfield];
        $b = $b[$this->__sortfield];

        // Integer values
        if ((string) (int) $a == $a && (string) (int) $b == $b) {
            $a = (int) $a;
            $b = (int) $b;

            return ($a - $b) * $this->__sortsign;
        }

        return strcmp($a, $b) * $this->__sortsign;
    }

    /**
     * Lexically sort.
     *
     * @param string $field The field
     * @param string $order The order
     */
    public function lexicalSort(string $field, string $order = 'asc'): void
    {
        $this->__sortfield = $field;
        $this->__sortsign  = strtolower($order) == 'asc' ? 1 : -1;

        usort($this->__data, [$this, 'lexicalSortCallback']);

        $this->__sortfield = null;
        $this->__sortsign  = null;
    }

    /**
     * Lexical sort field.
     */
    private function lexicalSortCallback(array $a, array $b): int
    {
        if (!isset($a[$this->__sortfield]) || !isset($b[$this->__sortfield])) {
            return 0;
        }

        $a = $a[$this->__sortfield];
        $b = $b[$this->__sortfield];

        // Integer values
        if ((string) (int) $a == $a && (string) (int) $b == $b) {
            $a = (int) $a;
            $b = (int) $b;

            return ($a - $b) * $this->__sortsign;
        }

        return strcoll(strtolower(Lexical::removeDiacritics($a)), strtolower(Lexical::removeDiacritics($b))) * $this->__sortsign;
    }
}
