<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib;

// Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceDescriptor

/**
 * Simple descriptor for tabs, groups and more.
 *
 * At this time this class is used in same way an arrayObject
 * but in futur it could be completed with advance methods.
 *
 * @ingroup  Plugin Maintenance
 */
class MaintenanceDescriptor
{
    /**
     * Constructs a new instance.
     *
     * @param string                $id      The identifier
     * @param string                $name    The name
     * @param array<string, string> $options The options
     */
    public function __construct(protected string $id, protected string $name, protected array $options = [])
    {
    }

    /**
     * Get ID.
     *
     * @return string ID
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get name.
     *
     * @return string Name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get option.
     *
     * Option called "summary" and "description" are used
     *
     * @param string $key Option key
     *
     * @return null|string Option value
     */
    public function option(string $key): ?string
    {
        return $this->options[$key] ?? null;
    }

    /**
     * Check if an option exists.
     *
     * @param string $key The key
     */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }
}
