<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Lib\Maintenance
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib;

use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceDescriptor;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

/**
Main class to call everything related to maintenance.
 */
class Maintenance
{
    public $p_url;

    private $tasks     = [];
    private $tasks_id  = [];
    private $tabs      = [];
    private $groups    = [];
    private $logs      = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->p_url = dotclear()->adminurl()->get('admin.plugin.Maintenance');
        $this->getLogs();
        $this->init();
    }

    /**
     * Initialize list of tabs and groups and tasks.
     *
     * To register a tab or group or task,
     * use behavior dcMaintenanceInit then a method of
     * Maintenance like addTab('myTab', ...).
     */
    protected function init()
    {
        # --BEHAVIOR-- dcMaintenanceInit
        dotclear()->behavior()->call('dcMaintenanceInit', $this);
    }

    /// @name Tab methods
    //@{
    /**
     * Adds a tab.
     *
     * @param      string  $id       The identifier
     * @param      string  $name     The name
     * @param      array   $options  The options
     *
     * @return     self
     */
    public function addTab($id, $name, $options = [])
    {
        $this->tabs[$id] = new MaintenanceDescriptor($id, $name, $options);

        return $this;
    }

    /**
     * Gets the tab.
     *
     * @param      string  $id     The identifier
     *
     * @return     MaintenanceDescriptor|null  The tab.
     */
    public function getTab($id)
    {
        return array_key_exists($id, $this->tabs) ? $this->tabs[$id] : null;
    }

    /**
     * Gets the tabs.
     *
     * @return     array  The tabs.
     */
    public function getTabs()
    {
        return $this->tabs;
    }
    //@}

    /// @name Group methods
    //@{
    /**
     * Adds a group.
     *
     * @param      string  $id       The identifier
     * @param      string  $name     The name
     * @param      array   $options  The options
     *
     * @return     self
     */
    public function addGroup($id, $name, $options = [])
    {
        $this->groups[$id] = new MaintenanceDescriptor($id, $name, $options);

        return $this;
    }

    /**
     * Gets the group.
     *
     * @param      string  $id     The identifier
     *
     * @return     MaintenanceDescriptor|null  The group.
     */
    public function getGroup($id)
    {
        return array_key_exists($id, $this->groups) ? $this->groups[$id] : null;
    }

    /**
     * Gets the groups.
     *
     * @return     array  The groups.
     */
    public function getGroups()
    {
        return $this->groups;
    }
    //@}

    /// @name Task methods
    //@{
    /**
     * Adds a task.
     *
     * @param      mixed  $task   The task, Class name or object
     *
     * @return     self
     */
    public function addTask($task)
    {
        if (is_subclass_of($task, __NAMESPACE__ . '\\MaintenanceTask')) {
            $this->tasks[$task] = new $task($this);
            $this->tasks_id[$this->tasks[$task]->id()] = $task;
        }

        return $this;
    }

    /**
     * Gets the task.
     *
     * @param      string  $id     The identifier
     *
     * @return     mixed  The task.
     */
    public function getTask($id)
    {
        return array_key_exists($id, $this->tasks_id) ? $this->tasks[$this->tasks_id[$id]] : null;
    }

    /**
     * Gets the tasks.
     *
     * @return     array  The tasks.
     */
    public function getTasks()
    {
        return $this->tasks;
    }

    /**
     * Gets the headers for plugin maintenance admin page.
     *
     * @return     string  The headers.
     */
    public function getHeaders()
    {
        $res = '';
        foreach ($this->tasks as $task) {
            $res .= $task->header();
        }

        return $res;
    }
    //@}

    /// @name Log methods
    //@{
    /**
     * Sets the log for a task.
     *
     * @param      string  $id     Task ID
     */
    public function setLog($id)
    {
        // Check if taks exists
        if (!$this->getTask($id)) {
            return;
        }

        // Get logs from this task
        $rs = dotclear()->con()->select(
            'SELECT log_id ' .
            'FROM ' . dotclear()->prefix . 'log ' .
            "WHERE log_msg = '" . dotclear()->con()->escape($id) . "' " .
            "AND log_table = 'maintenance' "
        );

        $logs = [];
        while ($rs->fetch()) {
            $logs[] = $rs->log_id;
        }

        // Delete old logs
        if (!empty($logs)) {
            dotclear()->log()->delete($logs);
        }

        // Add new log
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'log');

        $cur->log_msg   = $id;
        $cur->log_table = 'maintenance';
        $cur->user_id   = dotclear()->user()->userID();

        dotclear()->log()->add($cur);
    }

    /**
     * Delete all maintenance logs.
     */
    public function delLogs()
    {
        // Retrieve logs from this task
        $rs = dotclear()->log()->get([
            'log_table' => 'maintenance',
            'blog_id'   => '*',
        ]);

        $logs = [];
        while ($rs->fetch()) {
            $logs[] = $rs->log_id;
        }

        // Delete old logs
        if (!empty($logs)) {
            dotclear()->log()->delete($logs);
        }
    }

    /**
     * Get logs
     *
     * Return [
     *        task id => [
     *            timestamp of last execution,
     *            logged on current blog or not
     *        ]
     * ]
     *
     * @return    array List of logged tasks
     */
    public function getLogs()
    {
        if ($this->logs === null) {
            $rs = dotclear()->log()->get([
                'log_table' => 'maintenance',
                'blog_id'   => '*',
            ]);

            $this->logs = [];
            while ($rs->fetch()) {
                $this->logs[$rs->log_msg] = [
                    'ts'   => strtotime($rs->log_dt),
                    'blog' => $rs->blog_id == dotclear()->blog()->id,
                ];
            }
        }

        return $this->logs;
    }
    //@}
}
