<?php
/**
 * @class Dotclear\Admin\Page\Action\Action
 * @brief Dotclear admin handler for action page on selected entries
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page\Action;

use ArrayObject;

use Dotclear\Admin\Page\Page;
use Dotclear\Database\Record;
use Dotclear\Html\Form;
use Dotclear\Html\FormSelectOption;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

abstract class Action extends Page
{
    /** @var string form submit uri */
    protected $uri = '';

    /** @var array action combo box */
    protected $combo = [];

    /** @var ArrayObject list of defined actions (callbacks) */
    protected $actions;

    /** @var array selected entries (each key is the entry id, value contains the entry description) */
    protected $entries = [];

    /** @var Record record that challenges ids against permissions */
    protected $rs;

    /** @var array redirection $_GET arguments, if any (does not contain ids by default, ids may be merged to it) */
    protected $redir_args = [];

    /** @var array list of $_POST fields used to build the redirection  */
    protected $redirect_fields = [];

    /** @var string redirection anchor if any  */
    protected $redir_anchor = '';

    /** @var string current action, if any */
    protected $action = '';

    /** @var ArrayObject list of url parameters (usually $_POST) */
    protected $from;

    /** @var string form field name for "entries" (usually "entries") */
    protected $field_entries = '';

    /** @var string title for checkboxes list, if displayed */
    protected $cb_title = '';

    /** @var string title for caller page title */
    protected $caller_title = '';

    /** @var bool   true if we are acting inside a plugin (different handling of begin/endpage) */
    protected $in_plugin = false;

    /** @var bool   true if we enable to keep selection when redirecting */
    protected $enable_redir_selection = false;

    /**
     * Constructor
     *
     * @param   string  $uri            Form URI
     * @param   array   $redirect_args  Redirect arguments
     */
    public function __construct(string $uri, array $redirect_args = [])
    {
        parent::__construct();

        $this->actions         = new ArrayObject();
        $this->combo           = [];
        $this->uri             = $uri;
        $this->redir_args      = $redirect_args;
        $this->redirect_fields = [];
        $this->action          = '';
        $this->cb_title        = __('Title');
        $this->entries         = [];
        $this->from            = new ArrayObject($_POST);
        $this->field_entries   = 'entries';
        $this->caller_title    = __('Entries');
        if (isset($this->redir_args['_ANCHOR'])) {
            $this->redir_anchor = '#' . $this->redir_args['_ANCHOR'];
            unset($this->redir_args['_ANCHOR']);
        } else {
            $this->redir_anchor = '';
        }
        $u                            = explode('?', $_SERVER['REQUEST_URI']);
        $this->in_plugin              = (strpos($u[0], 'handler=admin.plugin.') !== false);
        $this->enable_redir_selection = true;
    }

    /**
     * Enable redir selection
     *
     * define whether to keep selection when redirecting
     * Can be usefull to be disabled to preserve some compatibility.
     *
     * @param   bool    $enable     True to enable, false otherwise
     */
    public function setEnableRedirSelection(bool $enable): void
    {
        $this->enable_redir_selection = $enable;
    }

    /**
     * Zdds an action
     *
     * @param   array       $actions    the actions names as if it was a standalone combo array.
     *                                  It will be merged with other actions.
     *                                  Can be bound to multiple values, if the same callback is to be called
     * @param   callable    $callback   The callback for the action.
     *
     * @return  Action                  The actions page itself, enabling to chain addAction().
     */
    public function addAction(array $actions, callable $callback): Action
    {
        foreach ($actions as $k => $a) {
            // Check each case of combo definition
            // Store form values in $values
            if (is_array($a)) {
                $values = array_values($a);
                if (!isset($this->combo[$k])) {
                    $this->combo[$k] = [];
                }
                $this->combo[$k] = array_merge($this->combo[$k], $a);
            } elseif ($a instanceof formSelectOption) {
                $values          = [$a->value];
                $this->combo[$k] = $a->value;
            } else {
                $values          = [$a];
                $this->combo[$k] = $a;
            }
            // Associate each potential value to the callback
            foreach ($values as $v) {
                $this->actions[$v] = $callback;
            }
        }

        return $this;
    }

    /**
     * Return the actions combo, useable through Form::combo
     *
     * @return  array   The actions combo
     */
    public function getCombo(): array
    {
        return $this->combo;
    }

    /**
     * Return the list of selected entries
     *
     * @return  array   The list
     */
    public function getIDs(): array
    {
        return array_keys($this->entries);
    }

    /**
     * Return the list of selected entries as HTML hidden fields string
     *
     * @return  string  The HTML code for hidden fields
     */
    public function getIDsHidden(): string
    {
        $ret = '';
        foreach ($this->entries as $id => $v) {
            $ret .= Form::hidden($this->field_entries . '[]', $id);
        }

        return $ret;
    }

    /**
     * Return all redirection parameters as HTML hidden fields
     *
     * @param   bool    $with_ids   If true, also include ids in HTML code
     *
     * @return  string              The HTML code for hidden fields
     */
    public function getHiddenFields(bool $with_ids = false): string
    {
        $ret = '';
        foreach ($this->redir_args as $k => $v) {
            $ret .= Form::hidden([$k], $v);
        }
        if ($with_ids) {
            $ret .= $this->getIDsHidden();
        }

        return $ret;
    }

    /**
     * Get record from DB Query containing requested IDs
     *
     * @return  Record the HTML code for hidden fields
     */
    public function getRS(): Record
    {
        return $this->rs;
    }

    /**
     * Setup redirection arguments
     *
     * by default, $_POST fields as defined in redirect_fields attributes
     * are set into redirect_args.
     *
     * @param   array|ArrayObject   $from   Input to parse fields from (usually $_POST)
     */
    protected function setupRedir(array|ArrayObject $from): void
    {
        foreach ($this->redirect_fields as $p) {
            if (isset($from[$p])) {
                $this->redir_args[$p] = $from[$p];
            }
        }
    }

    /**
     * Return redirection URL
     *
     * @param   bool    $with_selected_entries  If true, add selected entries in url
     * @param   array   $params                 Extra parameters to append to redirection
     *                                          must be an array : each key is the name,
     *                                          each value is the wanted value
     *
     * @return  string                          The redirection url
     */
    public function getRedirection(bool $with_selected_entries = false, array $params = []): string
    {
        $redir_args = array_merge($params, $this->redir_args);
        if (isset($redir_args['redir'])) {
            unset($redir_args['redir']);
        }

        if ($with_selected_entries && $this->enable_redir_selection) {
            $redir_args[$this->field_entries] = array_keys($this->entries);
        }

        return $this->uri . (strpos($this->uri, '?') === false ? '?' : '&') . http_build_query($redir_args) . $this->redir_anchor;
    }

    /**
     * Redirects to redirection page
     *
     * @see     getRedirection  for arguments details
     */
    public function redirect(bool $with_selected_entries = false, array $params = []): void
    {
        Http::redirect($this->getRedirection($with_selected_entries, $params));
        exit;
    }

    /**
     * Returns current form URI, if any
     *
     * @return  string  The form URI
     */
    public function getURI(): string
    {
        return $this->uri;
    }

    /**
     * Return current form URI, if any
     *
     * @return  string  The form URI
     */
    public function getCallerTitle(): string
    {
        return $this->caller_title;
    }

    /**
     * Return current action, if any
     *
     * @return  string  The action
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Force no check permissions for Action page
     * each Action manages their own perms
     *
     * @return  string|null|false
     */
    protected function getPermissions(): string|null|false
    {
        return false;
    }

    /**
     * Proceeds action handling, if any
     *
     * This method may issue an exit() if
     * an action is being processed. If it
     * returns, no action has been performed
     *
     * @return  bool|null
     */
    public function getPagePrepend(): ?bool
    {
        $this->setupRedir($this->from);
        $this->fetchEntries($this->from);
        if (isset($this->from['action'])) {
            $this->action = $this->from['action'];

            try {
                $performed = false;
                foreach ($this->actions as $k => $v) {
                    if ($this->from['action'] == $k) {
                        $performed = true;
                        call_user_func($v, $this, $this->from);
                    }
                }
                if ($performed) {
                    return true;
                }
            } catch (\Exception $e) {
                $this->error()->add($e);
                return false;
            }
        }

        return null;
    }

    /**
     * Return html code for selected entries
     * as a table containing entries checkboxes
     *
     * @return  string  The html code for checkboxes
     */
    public function getCheckboxes(): string
    {
        $ret = '<table class="posts-list"><tr>' .
        '<th colspan="2">' . $this->cb_title . '</th>' .
            '</tr>';
        foreach ($this->entries as $id => $title) {
            $ret .= '<tr><td class="minimal">' .
            Form::checkbox([$this->field_entries . '[]'], $id, [
                'checked' => true
            ]) .
                '</td>' .
                '<td>' . $title . '</td></tr>';
        }
        $ret .= '</table>';

        return $ret;
    }

    /**
     * Fill-in information by requesting into db
     *
     * This method may setup the following attributes
     *   * entries : list of entries (checked against permissions)
     *      entries ids are array keys, values contain entry description (if relevant)
     *   * rs : record given by db request
     *
     * @param   ArrayObject     $from   Entries from
     */
    abstract protected function fetchEntries(ArrayObject $from): void;
}
