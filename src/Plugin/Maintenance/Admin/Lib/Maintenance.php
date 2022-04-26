<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib;

// Dotclear\Plugin\Maintenance\Admin\Lib\Maintenance
use Dotclear\App;

/**
 * Main class to call everything related to maintenance.
 *
 * @ingroup  Plugin Maintenance
 */
class Maintenance
{
    /**
     * @var string $p_url
     *             Plugin URL
     */
    public $p_url = '';

    /**
     * @var array<string,MaintenanceTask> $tasks
     *                                    Tasks
     */
    private $tasks = [];

    /**
     * @var array<string,string> $tasks_id
     *                           Tasks class name
     */
    private $tasks_id = [];

    /**
     * @var array<string,MaintenanceDescriptor> $tabs
     *                                          Tasks tabs
     */
    private $tabs = [];

    /**
     * @var array<string,MaintenanceDescriptor> $groups
     *                                          Tasks groups
     */
    private $groups = [];

    /**
     * @var array<string,array> $logs
     *                          Logs
     */
    private $logs;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->p_url = App::core()->adminurl()->get('admin.plugin.Maintenance');
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
    protected function init(): void
    {
        // --BEHAVIOR-- dcMaintenanceInit
        App::core()->behavior()->call('dcMaintenanceInit', $this);
    }

    // / @name Tab methods
    // @{
    /**
     * Adds a tab.
     *
     * @param string                $id      The identifier
     * @param string                $name    The name
     * @param array<string, string> $options The options
     *
     * @return self
     */
    public function addTab(string $id, string $name, array $options = []): Maintenance
    {
        $this->tabs[$id] = new MaintenanceDescriptor($id, $name, $options);

        return $this;
    }

    /**
     * Gets the tab.
     *
     * @param string $id The identifier
     *
     * @return null|MaintenanceDescriptor the tab
     */
    public function getTab(string $id): ?MaintenanceDescriptor
    {
        return array_key_exists($id, $this->tabs) ? $this->tabs[$id] : null;
    }

    /**
     * Gets the tabs.
     *
     * @return array<string, MaintenanceDescriptor> the tabs
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }
    // @}

    // / @name Group methods
    // @{
    /**
     * Adds a group.
     *
     * @param string                $id      The identifier
     * @param string                $name    The name
     * @param array<string, string> $options The options
     *
     * @return self
     */
    public function addGroup(string $id, string $name, array $options = []): Maintenance
    {
        $this->groups[$id] = new MaintenanceDescriptor($id, $name, $options);

        return $this;
    }

    /**
     * Gets the group.
     *
     * @param string $id The identifier
     *
     * @return null|MaintenanceDescriptor the group
     */
    public function getGroup($id): ?MaintenanceDescriptor
    {
        return array_key_exists($id, $this->groups) ? $this->groups[$id] : null;
    }

    /**
     * Gets the groups.
     *
     * @return array<string, MaintenanceDescriptor> the groups
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
    // @}

    // / @name Task methods
    // @{
    /**
     * Adds a task.
     *
     * @param string $task The task, Class name or object
     *
     * @return self
     */
    public function addTask(string $task): Maintenance
    {
        if (is_subclass_of($task, __NAMESPACE__ . '\\MaintenanceTask')) {
            $this->tasks[$task]                        = new $task($this);
            $this->tasks_id[$this->tasks[$task]->id()] = $task;
        }

        return $this;
    }

    /**
     * Gets the task.
     *
     * @param string $id The identifier
     *
     * @return mixed the task
     */
    public function getTask(string $id)
    {
        return array_key_exists($id, $this->tasks_id) ? $this->tasks[$this->tasks_id[$id]] : null;
    }

    /**
     * Gets the tasks.
     *
     * @return array the tasks
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Gets the headers for plugin maintenance admin page.
     *
     * @return string the headers
     */
    public function getHeaders(): string
    {
        $res = '';
        foreach ($this->tasks as $task) {
            $res .= $task->header();
        }

        return $res;
    }
    // @}

    // / @name Log methods
    // @{
    /**
     * Sets the log for a task.
     *
     * @param string $id Task ID
     */
    public function setLog(string $id): void
    {
        // Check if taks exists
        if (!$this->getTask($id)) {
            return;
        }

        // Get logs from this task
        $rs = App::core()->log()->get([
            'log_msg'   => $id,
            'log_table' => 'maintenance',
            'blog_id'   => '*',
        ]);

        $logs = [];
        while ($rs->fetch()) {
            $logs[] = $rs->fInt('log_id');
        }

        // Delete old logs
        if (!empty($logs)) {
            App::core()->log()->delete($logs);
        }

        // Add new log
        $cur = App::core()->con()->openCursor(App::core()->prefix . 'log');
        $cur->setField('log_msg', $id);
        $cur->setField('log_table', 'maintenance');
        $cur->setField('user_id', App::core()->user()->userID());

        App::core()->log()->add($cur);
    }

    /**
     * Delete all maintenance logs.
     */
    public function delLogs(): void
    {
        // Retrieve logs from this task
        $rs = App::core()->log()->get([
            'log_table' => 'maintenance',
            'blog_id'   => '*',
        ]);

        $logs = [];
        while ($rs->fetch()) {
            $logs[] = $rs->fInt('log_id');
        }

        // Delete old logs
        if (!empty($logs)) {
            App::core()->log()->delete($logs);
        }
    }

    /**
     * Get logs.
     *
     * Return [
     *        task id => [
     *            timestamp of last execution,
     *            logged on current blog or not
     *        ]
     * ]
     *
     * @return array<string, array> List of logged tasks
     */
    public function getLogs(): array
    {
        if (null === $this->logs) {
            $rs = App::core()->log()->get([
                'log_table' => 'maintenance',
                'blog_id'   => '*',
            ]);

            $this->logs = [];
            while ($rs->fetch()) {
                $this->logs[$rs->f('log_msg')] = [
                    'ts'   => strtotime($rs->f('log_dt')),
                    'blog' => $rs->f('blog_id') == App::core()->blog()->id,
                ];
            }
        }

        return $this->logs;
    }
    // @}
}
