<?php
/**
 * @class Dotclear\Core\User\Preference\Preference
 * @brief Dotclear core user preference class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\User\Preference;

use Dotclear\Core\User\Preference\Workspace;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\MagicTrait;

class Preference
{
    use MagicTrait;

    /** @var    string  $table  Prefs table name */
    protected $table;

    /** @var    array   $workspaces    Associative workspaces array */ 
    protected $workspaces = [];

    /** @var    string  $ws     Current workspace */
    protected $ws;

    protected const WS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';

    /**
     * Object constructor. Retrieves user prefs and puts them in $workspaces
     * array. Local (user) prefs have a highest priority than global prefs.
     *
     * @param   string          $user_id    The user identifier
     * @param   string|null     $workspace  The workspace to load
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
     * Retrieves all (or only one) workspaces (and their prefs) from database, with one query.
     * 
     * @param   string  $workspace  Workspace to load
     */
    private function loadPrefs($workspace = null): void
    {
        $strReq = 'SELECT user_id, pref_id, pref_value, ' .
        'pref_type, pref_label, pref_ws ' .
        'FROM ' . $this->table . ' ' .
        "WHERE (user_id = '" . dotclear()->con()->escape($this->user_id) . "' " . 'OR user_id IS NULL ) ';
        if ($workspace !== null) {
            $strReq .= "AND pref_ws = '" . dotclear()->con()->escape($workspace) . "' ";
        }
        $strReq .= 'ORDER BY pref_ws ASC, pref_id ASC';

        try {
            $rs = dotclear()->con()->select($strReq);
        } catch (\Exception $e) {
            throw $e;
        }

        /* Prevent empty tables (install phase, for instance) */
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
     * @param   string  $ws     Workspace name
     *
     * @return  Workspace
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
     * @param   string  $oldWs  The old workspace name
     * @param   string  $newWs  The new workspace name
     *
     * @throws  CoreException
     *
     * @return  bool
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
        $strReq = 'UPDATE ' . $this->table .
        " SET pref_ws = '" . dotclear()->con()->escape($newWs) . "' " .
        " WHERE pref_ws = '" . dotclear()->con()->escape($oldWs) . "' ";
        dotclear()->con()->execute($strReq);

        return true;
    }

    /**
     * Delete a whole workspace with all preferences pertaining to it.
     *
     * @param   string  $ws     Workspace name
     *
     * @return  bool
     */
    public function delWorkspace(string $ws): bool
    {
        if (!$this->exists($ws)) {
            return false;
        }

        // Remove the workspace from the workspace array
        unset($this->workspaces[$ws]);

        // Delete all preferences from the workspace in the database
        $strReq = 'DELETE FROM ' . $this->table .
        " WHERE pref_ws = '" . dotclear()->con()->escape($ws) . "' ";
        dotclear()->con()->execute($strReq);

        return true;
    }

    /**
     * Returns full workspace with all prefs pertaining to it.
     *
     * @param   string  $ws     Workspace name
     *
     * @return  mixed
     */
    public function get(string $ws): mixed
    {
        return $this->exists($ws) ? $this->workspaces[$ws] : $this->addWorkspace($ws);
    }

    /**
     * Check if a workspace exists
     *
     * @param   string  $ws     Workspace name
     *
     * @return  bool
     */
    public function exists(string $ws): bool
    {
        return array_key_exists($ws, $this->workspaces);
    }

    /**
     * Dumps workspaces.
     *
     * @return  array
     */
    public function dump(): array
    {
        return $this->workspaces;
    }
}
