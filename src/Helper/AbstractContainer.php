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
     * @var array<string,mixed> $default
     *                          Default properties
     */
    private $default = [];

    /**
     * @var array<string,mixed> $current
     *                          Current properties
     */
    private $current = [];

    /**
     * @var array<string,string> $changed
     *                           Modified properties
     */
    private $changed = [];

    /**
     * Constructor.
     *
     * @param Record $rs A record
     */
    public function __construct(Record $rs = null)
    {
        foreach ($this->initDefaultProperties() as $key => $value) {
            $this->current[$key] = $this->default[$key] = $value;
        }

        $this->parseFromRecord($rs);
    }

    /**
     * Update properties from record.
     *
     * Only fields existing in default properties are set.
     *
     * @param Record $rs A record
     */
    public function parseFromRecord(Record $rs = null): void
    {
        if (null != $rs && !$rs->isEmpty()) {
            foreach ($this->getDefaultProperties() as $key => $value) {
                if ($rs->exists($key)) {
                    $this->current[$key] = $rs->field($key);
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
    public function parseToCursor(Cursor $cur): Cursor
    {
        foreach ($this->getChangedProperties() as $key => $value) {
            $cur->setField($key, $value);
        }

        return $cur;
    }

    /**
     * Set default properties.
     *
     * This method is called on class construction
     * and must return an array of key/value pair of default properties.
     *
     * @note
     * The type of default values is used to fixed new values type.
     *
     * @return array<string,mixed> the default properties
     */
    abstract protected function initDefaultProperties(): array;

    /**
     * Get default properties.
     *
     * @return array<string,mixed> The default properties
     */
    public function getDefaultProperties(): array
    {
        return $this->default;
    }

    /**
     * Get current properties.
     *
     * @return array<string,mixed> The current properties
     */
    public function getCurrentProperties(): array
    {
        return $this->current;
    }

    /**
     * Get modified properties.
     *
     * @return array<string,mixed> The modified properties
     */
    public function getChangedProperties(): array
    {
        return $this->changed;
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
        if (!$this->propertyExists($key)) {
            $this->current[$key] = $value;
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
    public function setProperty(string $key, mixed $value): mixed
    {
        $this->propertyExists($key, true);
        if (!settype($value, gettype($this->current[$key]))) {
            throw new Exception(sprintf('Wrong container value type for key %s', $key));
        }
        $this->current[$key] = $this->changed[$key] = $value;

        return $this->current[$key];
    }

    /**
     * Get a property.
     *
     * @param string $key The key
     *
     * @return mixed The value
     */
    public function getProperty(string $key): mixed
    {
        $this->propertyExists($key, true);

        return $this->current[$key];
    }

    /**
     * Check if a property exists.
     *
     * @param string $key   The key
     * @param bool   $throw Throw error if key does not exist
     */
    public function propertyExists(string $key, bool $throw = false): bool
    {
        if (!($exists = array_key_exists($key, $this->current)) && $throw) {
            throw new Exception(sprintf('Unknown container key %s', $key));
        }

        return $exists;
    }
}
