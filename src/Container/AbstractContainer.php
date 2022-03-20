<?php
/**
 * @class Dotclear\Container\AbstractContainer
 * @brief Dotclear simple Container helper
 *
 * @package Dotclear
 * @subpackage Container
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Container;

use Dotclear\Database\Cursor;
use Dotclear\Database\Record;

class AbstractContainer
{
    protected $id    = '';
    protected $info  = [];
    private $row     = [];
    private $change  = [];

    public function __construct(Record $rs = null)
    {
        foreach($this->info() as $key => $value) {
            $this->add($key, $value);
        }

        $this->fromRecord($rs);
    }

    public function fromRecord(Record $rs = null): void
    {
        if ($rs && !$rs->isEmpty()) {
            foreach($this->info() as $key => $value) {
                if ($rs->exists($key)) {
                    $this->row[$key] = $rs->f($key);
                }
            }
        }
    }

    public function toCursor(Cursor $cur): Cursor
    {
        foreach($this->change as $key => $value) {
            $cur->setField($key, $value);
        }

        return $cur;
    }

    public function row(): array
    {
        return $this->row;
    }

    public function info(): array
    {
        return $this->info;
    }

    public function add(string $key, mixed $value): AbstractContainer
    {
        if (!$this->exists($key)) {
            $this->row[$key] = $value;
        }

        return $this;
    }

    public function set(string $key, mixed $value): mixed
    {
        $this->exists($key, true);
        $this->row[$key] = $this->change[$key] = $value;

        return $this->row[$key];
    }

    public function __set(string $key, mixed $value)
    {
        return $this->set($key, $value);
    }

    public function get(string $key): mixed
    {
        $this->exists($key, true);

        return $this->row[$key];
    }

    public function __get(string $key)
    {
        return $this->get($key);
    }

    public function exists(string $key, bool $throw = false): bool
    {
        if (!($exists = array_key_exists($key, $this->row)) && $throw) {
            throw new \Exception(sprintf('Unknown container key %s', $key));
        }

        return $exists;
    }

    public function __isset(string $key)
    {
        return $this->exists($key);
    }

}
