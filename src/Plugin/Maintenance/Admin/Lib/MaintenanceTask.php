<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask
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

use Dotclear\Plugin\Maintenance\Admin\Lib\Maintenance;

/**
@brief Maintenance plugin task class.

Every task of maintenance must extend this class.
 */
class MaintenanceTask
{
    /** @var    string  $p_url  Plugin URL */
    protected $p_url = '';

    /** @var    int     $code   Code for stepped task */
    protected $code = 0;

    /** @var    int     $ts     Timestamp between task execution */
    protected $ts = 0;

    /** @var    int|false|null  $expired    Task expired */
    protected $expired = 0;

    /** @var    bool    $ajax   Use ajax */
    protected $ajax = false;

    /** @var    bool    $blog   Is limited to current blog */
    protected $blog = false;

    /** @var    string|null     $perm   Permission to use task */
    protected $perm = null;

    /** @var    string  $id     Task ID */
    protected $id = '';

    /** @var    string  $sid    Task sanitized ID */
    protected $sid = '';

    /** @var    string  $anme   Task name */
    protected $name = '';

    /** @var    string  $decription     Task description */
    protected $description = '';

    /** @var    string|null   Task tab */  
    protected $tab = 'maintenance';

    /** @var    string|null     $group  Task group */
    protected $group = 'other';

    /** @var    string|null     Task execution message */
    protected $step = null;

    /** @var    string  $task   Task form button message */
    protected $task = '';

    /** @var    string  $error  Task error message */
    protected $error = '';

    /** @var    string  $success    Task success message */
    protected $success = '';

    /**
     * Constructor.
     *
     * If your task required something on construct,
     * use method init() to do it.
     *
     * @param      Maintenance  $maintenance  The maintenance
     */
    final public function __construct(protected Maintenance $maintenance)
    {
        $this->init();

        if (null === $this->perm() && !dotclear()->user()->isSuperAdmin()
            || !dotclear()->user()->check((string) $this->perm(), dotclear()->blog()->id)) {
            return;
        }

        $this->p_url = $maintenance->p_url;
        $this->id    = join('', array_slice(explode('\\', get_class($this)), -1));

        if ('' == $this->name) {
            $this->name = get_class($this);
        }

        if (empty($this->error)) {
            $this->error = __('Failed to execute task.');
        }
        if (empty($this->success)) {
            $this->success = __('Task successfully executed.');
        }

        $this->ts = abs((int) dotclear()->blog()->settings()->get('maintenance')->get('ts_' . $this->id));
    }

    /**
     * Initialize task object.
     *
     * Better to set translated messages here than
     * to rewrite constructor.
     */
    protected function init(): void
    {
    }

    /**
     * Get task permission.
     *
     * Return user permission required to run this task
     * or null for super admin.
     *
     * @return  string|null   Permission.
     */
    public function perm(): ?string
    {
        return $this->perm;
    }

    /**
     * Get task scope.
     *.
     * Is task limited to current blog.
     *
     * @return  bool    Limit to blog
     */
    public function blog(): bool
    {
        return $this->blog;
    }

    /**
     * Set $code for task having multiple steps.
     *
     * @param   int     $code   Code used for task execution
     */
    public function code(int $code = 0): void
    {
        $this->code = $code;
    }

    /**
     * Get timestamp between maintenances.
     *
     * @return  int   Timestamp
     */
    public function ts(): int
    {
        return abs((int) $this->ts);
    }

    /**
     * Get task expired.
     *
     * This return:
     * - Timestamp of last update if it expired
     * - False if it not expired or has no recall time
     * - Null if it has never been executed
     *
     * @return    int|false|null    Last update
     */
    public function expired(): int|false|null
    {
        if (0 === $this->expired) {
            if (!$this->ts()) {
                $this->expired = false;
            } else {
                $this->expired = null;
                $logs          = [];
                foreach ($this->maintenance->getLogs() as $id => $log) {
                    if ($id != $this->id() || $this->blog && !$log['blog']) {
                        continue;
                    }

                    $this->expired = $log['ts'] + $this->ts() < time() ? $log['ts'] : false;
                }
            }
        }

        return $this->expired;
    }

    /**
     * Get task ID.
     *
     * @return  string  Task ID (class name)
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get task name.
     *
     * @return  string  Task name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get task description.
     *
     * @return  string  Description
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Get task tab.
     *
     * @return  string|null     Task tab ID or null
     */
    public function tab(): ?string
    {
        return $this->tab;
    }

    /**
     * Get task group.
     *
     * If task required a full tab,
     * this must be returned null.
     *
     * @return  string|null     Task group ID or null
     */
    public function group(): ?string
    {
        return $this->group;
    }

    /**
     * Use ajax
     *
     * Is task use maintenance ajax script
     * for steps process.
     *
     * @return  bool    Use ajax
     */
    public function ajax(): bool
    {
        return (bool) $this->ajax;
    }

    /**
     * Get task message.
     *
     * This message is used on form button.
     *
     * @return  string  Message
     */
    public function task(): string
    {
        return $this->task;
    }

    /**
     * Get step message.
     *
     * This message is displayed during task step execution.
     *
     * @return  string|null     Message or null
     */
    public function step(): ?string
    {
        return $this->step;
    }

    /**
     * Get success message.
     *
     * This message is displayed when task is accomplished.
     *
     * @return  string  Message
     */
    public function success(): string
    {
        return $this->success;
    }

    /**
     * Get error message.
     *
     * This message is displayed on error.
     *
     * @return  string  Message
     */
    public function error(): string
    {
        return $this->error;
    }

    /**
     * Get header.
     *
     * Headers required on maintenance page.
     *
     * @return  string|null     Message or null
     */
    public function header(): ?string
    {
        return null;
    }

    /**
     * Get content.
     *
     * Content for full tab task.
     *
     * @return  string|null     Tab's content
     */
    public function content(): ?string
    {
        return null;
    }

    /**
     * Execute task.
     *
     * @return    int|bool    :
     *    - FALSE on error,
     *    - TRUE if task is finished
     *    - INTEGER if task required a next step
     */
    public function execute(): int|bool
    {
        return false;
    }

    /**
     * Log task execution.
     *
     * Sometimes we need to log task execution
     * direct from task itself.
     */
    protected function log(): void
    {
        $this->maintenance->setLog($this->id);
    }

    /**
     * Help function.
     */
    public function help(): void
    {
    }
}
