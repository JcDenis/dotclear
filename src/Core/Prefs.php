<?php
/**
 * @class Dotclear\Core\Prefs
 * @brief Dotclear core prefs class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Exception\CoreException;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prefs
{
    protected $table;   ///< <b>string</b> Prefs table name
    protected $user_id; ///< <b>string</b> User ID

    protected $workspaces = []; ///< <b>array</b> Associative workspaces array

    protected $ws; ///< <b>string</b> Current workspace

    protected const WS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';

    /**
     * Object constructor. Retrieves user prefs and puts them in $workspaces
     * array. Local (user) prefs have a highest priority than global prefs.
     *
     * @param      string      $user_id   The user identifier
     * @param      string|null $workspace The workspace to load
     */
    public function __construct($user_id, $workspace = null)
    {
        $this->table   = dotclear()->prefix . 'pref';
        $this->user_id = $user_id;

        try {
            $this->loadPrefs($workspace);
        } catch (\Exception $e) {
            if (version_compare(dotclear()->getVersion('core'), '2.3', '>')) {
                trigger_error(__('Unable to retrieve workspaces:') . ' ' . dotclear()->con()->error(), E_USER_ERROR);
            }
        }
    }

    /**
    Retrieves all (or only one) workspaces (and their prefs) from database, with one query.
     */
    private function loadPrefs($workspace = null)
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
     * @param      string  $ws     Workspace name
     *
     * @return     dcWorkspace
     */
    public function addWorkspace($ws)
    {
        if (!$this->exists($ws)) {
            $this->workspaces[$ws] = new Workspace($this->user_id, $ws);
        }

        return $this->workspaces[$ws];
    }

    /**
     * Rename a workspace.
     *
     * @param      string     $oldWs  The old workspace name
     * @param      string     $newWs  The new workspace name
     *
     * @throws     CoreException  (description)
     *
     * @return     bool
     */
    public function renWorkspace($oldWs, $newWs)
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
     * @param      string  $ws     Workspace name
     *
     * @return     bool
     */
    public function delWorkspace($ws)
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
     * @param      string  $ws     Workspace name
     *
     * @return     mixed
     */
    public function get($ws)
    {
        if ($this->exists($ws)) {
            return $this->workspaces[$ws];
        }
    }

    /**
     * Magic __get method.
     *
     * @copydoc ::get
     *
     * @param      string  $n     Workspace name
     *
     * @return     mixed
     */
    public function __get($n)
    {
        return $this->get($n);
    }

    /**
     * Check if a workspace exists
     *
     * @param      string  $ws     Workspace name
     *
     * @return     boolean
     */
    public function exists($ws)
    {
        return array_key_exists($ws, $this->workspaces);
    }

    /**
     * Dumps workspaces.
     *
     * @return     array
     */
    public function dumpWorkspaces()
    {
        return $this->workspaces;
    }
}
