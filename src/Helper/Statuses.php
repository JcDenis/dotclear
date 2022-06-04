<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\Statuses
use Dotclear\App;

/**
 * Status helper.
 *
 * @ingroup  Helper Mapper
 */
class Statuses
{
    /**
     * @var array<int,Status> $statuses
     *                        Registered statuses
     */
    private $statuses = [];

    /**
     * Constructor.
     *
     * @param string $id        The statuses stack ID
     * @param Status ...$status The status instance
     */
    public function __construct(private string $id, Status ...$status)
    {
        foreach ($status as $value) {
            if ($value instanceof Status) {
                $this->addStatus(status: $value);
            }
        }

        // --BEHAVIOR-- coreAfterConstructStatuses, string, Statuses
        App::core()->behavior()->call('coreAfterConstructStatuses', id: $this->id, stack: $this);
    }

    /**
     * Add a status.
     *
     * @param Status $status The status
     */
    public function addStatus(Status $status): void
    {
        $this->statuses[$status->code] = $status;
        arsort($this->statuses);
    }

    /**
     * Remove a status.
     *
     * @param string $id The status ID
     */
    public function removeStatus(string $id): void
    {
        foreach ($this->statuses as $status) {
            if ($id == $status->id) {
                unset($this->statuses[$status->code]);

                break;
            }
        }
    }

    /**
     * Get a status ID.
     *
     * Returns a status ID given to an code.
     *
     * @param int    $code    The status code
     * @param string $default The value returned if code not exists
     *
     * @return null|string The status ID
     */
    public function getId(int $code, string $default = null): ?string
    {
        foreach ($this->statuses as $status) {
            if ($code == $status->code) {
                return $status->id;
            }
        }

        return $default;
    }

    /**
     * Get status codes.
     *
     * Return an array of ID /code pair.
     *
     * @return array<string,int> All status code
     */
    public function getCodes(): array
    {
        $result = [];
        foreach ($this->statuses as $status) {
            $result[$status->id] = $status->code;
        }

        return $result;
    }

    /**
     * Get a status code.
     *
     * Returns a status code given to an ID.
     *
     * @param string $id      The status ID
     * @param int    $default The value returned if ID not exists
     *
     * @return null|int The status code
     */
    public function getCode(string $id, int $default = null): ?int
    {
        foreach ($this->statuses as $status) {
            if ($id == $status->id) {
                return $status->code;
            }
        }

        return $default;
    }

    /**
     * Get a status icon URI.
     *
     * Returns a status icon given to an code.
     *
     * @param int    $code    The status code
     * @param string $default The value returned if code not exists
     *
     * @return null|string The status icon URI
     */
    public function getIcon(int $code, string $default = null): ?string
    {
        foreach ($this->statuses as $status) {
            if ($code == $status->code) {
                return $status->icon;
            }
        }

        return $default;
    }

    /**
     * Get all status states.
     *
     * @return array<string,int> An array of available status states and codes
     */
    public function getStates(): array
    {
        $result = [];
        foreach ($this->statuses as $status) {
            $result[$status->state] = $status->code;
        }

        return $result;
    }

    /**
     * Get a status state.
     *
     * Returns a status state given to a code. This is intended to be
     * human-readable and will be translated, so never use it for tests.
     *
     * @param int    $code    The blog status code
     * @param string $default The value returned if code not exists
     *
     * @return null|string The status state
     */
    public function getState(int $code, string $default = null): ?string
    {
        foreach ($this->statuses as $status) {
            if ($code == $status->code) {
                return $status->state;
            }
        }

        return $default;
    }

    /**
     * Get all status actions.
     *
     * @return array<string,string> An array of available status actions and IDs
     */
    public function getActions(): array
    {
        $result = [];
        foreach ($this->statuses as $status) {
            if (!empty($status->action)) {
                $result[$status->action] = $status->id;
            }
        }

        return $result;
    }

    /**
     * Dump statuses stack.
     *
     * @return array<int,Status> Registred statuses
     */
    public function dump(): array
    {
        return $this->statuses;
    }
}
