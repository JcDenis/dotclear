<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\AbstractContainer
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Exception;

/**
 * Abstract container helper.
 *
 * @ingroup  Helper Container Stack
 */
abstract class AbstractContainer
{
    /**
     * @var string $id
     *             The container id
     */
    protected $id = '';

    /**
     * @var array<string,mixed> $info
     *                          Default properties
     */
    protected $info = [];

    /**
     * @var array<string,mixed> $row
     *                          Properties
     */
    private $row = [];

    /**
     * @var array<string,mixed> $change
     *                          Modified properties
     */
    private $change = [];

    /**
     * Constructor.
     *
     * @param Record $rs A record
     */
    public function __construct(Record $rs = null)
    {
        foreach ($this->info() as $key => $value) {
            $this->add($key, $value);
        }

        $this->fromRecord($rs);
    }

    /**
     * Update properties from record.
     *
     * @param Record $rs A record
     */
    public function fromRecord(Record $rs = null): void
    {
        if ($rs && !$rs->isEmpty()) {
            foreach ($this->info() as $key => $value) {
                if ($rs->exists($key)) {
                    $this->row[$key] = $rs->f($key);
                }
            }
        }
    }

    /**
     * Apply container properties to a cursor.
     *
     * Only modified values compared to default properties
     * will be added to cursor.
     *
     * @param Cursor $cur A cursor
     */
    public function toCursor(Cursor $cur): Cursor
    {
        foreach ($this->change as $key => $value) {
            $cur->setField($key, $value);
        }

        return $cur;
    }

    /**
     * Get container id.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get container properties.
     *
     * @return array<string, mixed>
     */
    public function row(): array
    {
        return $this->row;
    }

    /**
     * Get container default properties.
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return $this->info;
    }

    /**
     * Add a property.
     *
     * @param string $key   The key
     * @param mixed  $value The value
     *
     * @return static Self instance
     */
    public function add(string $key, mixed $value): static
    {
        if (!$this->exists($key)) {
            $this->row[$key] = $value;
        }

        return $this;
    }

    /**
     * Set a propety.
     *
     * @param string $key   The key
     * @param mixed  $value The value
     *
     * @return mixed The value
     */
    public function set(string $key, mixed $value): mixed
    {
        $this->exists($key, true);
        if (!settype($value, gettype($this->row[$key]))) {
            throw new Exception(sprintf('Wrong container value type for key %s', $key));
        }
        $this->row[$key] = $this->change[$key] = $value;

        return $this->row[$key];
    }

    /**
     * Get a property.
     *
     * @param string $key The key
     *
     * @return mixed The value
     */
    public function get(string $key): mixed
    {
        $this->exists($key, true);

        return $this->row[$key];
    }

    /**
     * Check if a property exists.
     *
     * @param string $key   The key
     * @param bool   $throw Throw error if key does not exist
     */
    public function exists(string $key, bool $throw = false): bool
    {
        if (!($exists = array_key_exists($key, $this->row)) && $throw) {
            throw new Exception(sprintf('Unknown container key %s', $key));
        }

        return $exists;
    }
}
