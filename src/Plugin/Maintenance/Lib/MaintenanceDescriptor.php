<?php
/**
 * @class Dotclear\Plugin\Maintenance\Lib\MaintenanceDescriptor
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Lib;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

/**
@brief Simple descriptor for tabs, groups and more

At this time this class is used in same way an arrayObject
but in futur it could be completed with advance methods.
 */
class MaintenanceDescriptor
{
    protected $id;
    protected $name;
    protected $options;

    /**
     * Constructs a new instance.
     *
     * @param      string  $id       The identifier
     * @param      string  $name     The name
     * @param      array   $options  The options
     */
    public function __construct($id, $name, $options = [])
    {
        $this->id      = (string) $id;
        $this->name    = (string) $name;
        $this->options = (array) $options;
    }

    /**
     * Get ID.
     *
     * @return string    ID
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * Get name.
     *
     * @return string    Name
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Get option.
     *
     * Option called "summary" and "description" are used.
     *
     * @param      string  $key    Option key
     *
     * @return     string  Option value
     */
    public function option($key)
    {
        return $this->options[$key] ?? null;
    }

    /* @ignore */
    public function __get($key)
    {
        return $this->option($key);
    }

    /* @ignore */
    public function __isset($key)
    {
        return isset($this->options[$key]);
    }
}
