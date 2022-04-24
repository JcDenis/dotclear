<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\User\Preference;

// Dotclear\Core\User\Preference\Preference
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;
use Exception;

/**
 * User preference handling methods.
 *
 * @ingroup  Core User Preference
 */
class Preference
{
    /**
     * @var string $table
     *             Prefs table name
     */
    protected $table;

    /**
     * @var array<string,Workspace> $workspaces
     *                              Associative workspaces array
     */
    protected $workspaces = [];

    /**
     * @var string $ws
     *             Current workspace
     */
    protected $ws;

    protected const WS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';

    /**
     * Constructor.
     *
     * Retrieves user prefs and puts them in $workspaces
     * array. Local (user) prefs have a highest priority than global prefs.
     *
     * @param string      $user_id   The user identifier
     * @param null|string $workspace The workspace to load
     */
    public function __construct(protected string $user_id, $workspace = null)
    {
        $this->table = dotclear()->prefix . 'pref';

        try {
            $this->loadPrefs($workspace);
        } catch (\Exception) {
            trigger_error(__('Unable to retrieve workspaces:') . ' ' . dotclear()->con()->error(), E_USER_ERROR);
        }
    }

    /**
     * Get all (or only one) workspaces (and their prefs) from database, with one query.
     *
     * @param string $workspace Workspace to load
     */
    private function loadPrefs($workspace = null): void
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->columns([
                'user_id',
                'pref_id',
                'pref_value',
                'pref_type',
                'pref_label',
                'pref_ws',
            ])
            ->from($this->table)
            ->where($sql->orGroup([
                'user_id = ' . $sql->quote($this->user_id),
                'user_id IS NULL',
            ]))
            ->order([
                'pref_ws ASC',
                'pref_id ASC',
            ])
        ;

        if (null !== $workspace) {
            $sql->and('pref_ws = ' . $sql->quote($workspace));
        }

        try {
            $rs = $sql->select();
        } catch (Exception $e) {
            throw $e;
        }

        // Prevent empty tables (install phase, for instance)
        if ($rs->isEmpty()) {
            return;
        }

        do {
            $ws = trim($rs->f('pref_ws'));
            if (!$rs->isStart()) {
                // we have to go up 1 step, since workspaces construction performs a fetch()
                // at very first time
                $rs->movePrev();
            }
            $this->workspaces[$ws] = new Workspace($this->user_id, $ws, $rs);
        } while (!$rs->isStart());
    }

    /**
     * Create a new workspace. If the workspace already exists, return it without modification.
     *
     * @param string $ws Workspace name
     */
    public function addWorkspace(string $ws): Workspace
    {
        if (!$this->exists($ws)) {
            $this->workspaces[$ws] = new Workspace($this->user_id, $ws);
        }

        return $this->workspaces[$ws];
    }

    /**
     * Rename a workspace.
     *
     * @param string $oldWs The old workspace name
     * @param string $newWs The new workspace name
     *
     * @throws CoreException
     */
    public function renWorkspace(string $oldWs, string $newWs): bool
    {
        if (!$this->exists($oldWs) || $this->exists($newWs)) {
            return false;
        }

        if (!preg_match(self::WS_NAME_SCHEMA, $newWs)) {
            throw new CoreException(sprintf(__('Invalid dcWorkspace: %s'), $newWs));
        }

        // Rename the workspace in the workspace array
        $this->workspaces[$newWs] = $this->workspaces[$oldWs];
        unset($this->workspaces[$oldWs]);

        // Rename the workspace in the database
        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->set('pref_ws = ' . $sql->quote($newWs))
            ->from($this->table)
            ->where('pref_ws = ' . $sql->quote($oldWs))
            ->update()
        ;

        return true;
    }

    /**
     * Delete a whole workspace with all preferences pertaining to it.
     *
     * @param string $ws Workspace name
     */
    public function delWorkspace(string $ws): bool
    {
        if (!$this->exists($ws)) {
            return false;
        }

        // Remove the workspace from the workspace array
        unset($this->workspaces[$ws]);

        // Delete all preferences from the workspace in the database
        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->from($this->table)
            ->where('pref_ws = ' . $sql->quote($ws))
            ->delete()
        ;

        return true;
    }

    /**
     * Get full workspace with all prefs pertaining to it.
     *
     * @param string $ws Workspace name
     */
    public function get(string $ws): mixed
    {
        return $this->exists($ws) ? $this->workspaces[$ws] : $this->addWorkspace($ws);
    }

    /**
     * Check if a workspace exists.
     *
     * @param string $ws Workspace name
     */
    public function exists(string $ws): bool
    {
        return array_key_exists($ws, $this->workspaces);
    }

    /**
     * Dump workspaces.
     */
    public function dump(): array
    {
        return $this->workspaces;
    }
}
