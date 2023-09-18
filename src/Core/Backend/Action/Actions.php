<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

/**
 * @namespace   Dotclear.Core.Backend.Action
 * @brief       Backend list actions helpers.
 */

namespace Dotclear\Core\Backend\Action;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Option;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;
use formSelectOption;

/**
 * @brief   Handler for action page on selected entries.
 */
abstract class Actions
{
    /**
     * Action combo box.
     *
     * @var     array   $combo
     */
    protected $combo = [];

    /**
     * List of defined actions (callbacks).
     *
     * @var     ArrayObject     $actions
     */
    protected $actions;

    /**
     * Selected entries (each key is the entry id, value contains the entry description).
     *
     * @var     array   $entries
     */
    protected $entries = [];

    /**
     * Record that challenges ids against permissions.
     *
     * @var     MetaRecord  $rs
     */
    protected $rs;

    /**
     * List of $_POST fields used to build the redirection.
     *
     * @var     array   $redirect_fields
     */
    protected $redirect_fields = [];

    /**
     * Redirection anchor if any.
     *
     * @var     string  $redir_anchor
     */
    protected $redir_anchor = '';

    /**
     * Current action, if any.
     *
     * @var     string  $action
     */
    protected $action = '';

    /**
     * List of url parameters (usually $_POST).
     *
     * @var     ArrayObject     $from
     */
    protected $from;

    /**
     * Form field name for "entries" (usually "entries").
     *
     * @var     string  $field_entries
     */
    protected $field_entries;

    /**
     * Title for checkboxes list, if displayed.
     *
     * @var     string  $cb_title
     */
    protected $cb_title;

    /**
     * Title for caller page title.
     *
     * @var     string  $caller_title
     */
    protected string $caller_title;

    /**
     * True if we are acting inside a plugin (different handling of begin/endpage).
     *
     * @var     bool    $in_plugin
     */
    protected $in_plugin = false;

    /**
     * True if we enable to keep selection when redirecting.
     *
     * @var     bool    $enable_redir_selection
     */
    protected $enable_redir_selection = true;

    /**
     * Use render method.
     *
     * True if class uses silent process method and uses render method instead.
     *
     * @var     bool    $use_render
     */
    protected $use_render = false;

    /**
     * Action process content.
     *
     * @var     string  Action process content
     */
    protected $render = '';

    /**
     * Constructs a new instance.
     *
     * @param   null|string     $uri            The form uri
     * @param   array           $redir_args     The redirection $_GET arguments,
     *                                          if any (does not contain ids by default, ids may be merged to it)
     */
    public function __construct(
        protected ?string $uri,
        protected array $redir_args = []
    ) {
        $this->actions       = new ArrayObject();
        $this->from          = new ArrayObject($_POST);
        $this->field_entries = 'entries';
        $this->cb_title      = __('Title');
        $this->caller_title  = __('Posts');

        if (isset($this->redir_args['action_anchor'])) {
            $this->redir_anchor = '#' . $this->redir_args['action_anchor'];
            unset($this->redir_args['action_anchor']);
        }

        $uri_parts = explode('?', $_SERVER['REQUEST_URI']);
        if ($uri_parts !== false) {
            $this->in_plugin = !empty($_REQUEST['process']) && $_REQUEST['process'] == 'Plugin' || str_contains($uri_parts[0], 'plugin.php');
        }
    }

    /**
     * Define whether to keep selection when redirecting.
     *
     * Can be usefull to be disabled to preserve some compatibility.
     *
     * @param   bool    $enable     True to enable, false otherwise
     */
    public function setEnableRedirSelection(bool $enable)
    {
        $this->enable_redir_selection = $enable;
    }

    /**
     * Adds an action.
     *
     * @param   array           $actions    The actions names as if it was a standalone combo array.
     *                                      It will be merged with other actions.
     *                                      Can be bound to multiple values, if the same callback is to be called
     * @param   callable        $callback   The callback for the action.
     *
     * @return  Actions     The actions page itself, enabling to chain addAction().
     */
    public function addAction(array $actions, $callback): Actions
    {
        foreach ($actions as $group => $options) {
            // Check each case of combo definition
            // Store form values in $values
            if (is_array($options)) {
                $values              = array_values($options);
                $this->combo[$group] = array_merge($this->combo[$group] ?? [], $options);
            } elseif (
                $options instanceof formSelectOption || // CB: common/lib.form.php (deprecated Since 2.26)
                $options instanceof Option
            ) {
                $values              = [$options->value];
                $this->combo[$group] = $options->value;
            } else {
                $values              = [$options];
                $this->combo[$group] = $options;
            }
            // Associate each potential value to the callback
            foreach ($values as $value) {
                $this->actions[$value] = $callback;
            }
        }

        return $this;
    }

    /**
     * Returns the actions combo.
     *
     * Useable through form::combo/formOption (see addAction() method)
     *
     * @return  array   The actions combo
     */
    public function getCombo(): ?array
    {
        return $this->combo;
    }

    /**
     * Returns the list of selected entries.
     *
     * @return  array   The list
     */
    public function getIDs(): array
    {
        return array_keys($this->entries);
    }

    /**
     * Returns the list of selected entries as HTML hidden fields string.
     *
     * @return  string  The HTML code for hidden fields
     */
    public function getIDsHidden(): string
    {
        $ret = '';
        foreach (array_keys($this->entries) as $id) {
            $ret .= (new Hidden($this->field_entries . '[]', $id))->render();
        }

        return $ret;
    }

    /**
     * Returns the list of selected entries as an array of formHidden object.
     *
     * @return  array   The hidden form fields.
     */
    public function IDsHidden(): array
    {
        $ret = [];
        foreach (array_keys($this->entries) as $id) {
            $ret[] = (new Hidden($this->field_entries . '[]', $id));
        }

        return $ret;
    }

    /**
     * Returns all redirection parameters as HTML hidden fields.
     *
     * @param   bool    $with_ids   If true, also include ids in HTML code
     *
     * @return  string  The HTML code for hidden fields
     */
    public function getHiddenFields(bool $with_ids = false): string
    {
        $ret = '';
        foreach ($this->redir_args as $name => $value) {
            $ret .= (new Hidden([$name], (string) $value))->render();
        }
        if ($with_ids) {
            $ret .= $this->getIDsHidden();
        }

        return $ret;
    }

    /**
     * Returns all redirection parameters as an array of formHidden object.
     *
     * @param   bool    $with_ids   If true, also include ids in array
     *
     * @return  array   The hidden form fields.
     */
    public function hiddenFields(bool $with_ids = false): array
    {
        $ret = [];
        foreach ($this->redir_args as $name => $value) {
            $ret[] = (new Hidden([$name], (string) $value));
        }
        if ($with_ids) {
            $ret = array_merge($ret, $this->IDsHidden());
        }

        return $ret;
    }

    /**
     * Get record from DB Query containing requested IDs.
     *
     * @return  MetaRecord
     */
    public function getRS(): MetaRecord
    {
        return $this->rs;
    }

    /**
     * Setup redirection arguments
     *
     *  by default, $_POST fields as defined in redirect_fields attributes
     *  are set into redirect_args.
     *
     * @param   array|ArrayObject   $from   input to parse fields from (usually $_POST)
     */
    protected function setupRedir($from)
    {
        foreach ($this->redirect_fields as $field) {
            if (isset($from[$field])) {
                $this->redir_args[$field] = $from[$field];
            }
        }
    }

    /**
     * Returns redirection URL.
     *
     * @param   bool    $with_selected_entries  If true, add selected entries in url
     * @param   array   $params                 Extra parameters to append to redirection
     *                                          must be an array : each key is the name,
     *                                          each value is the wanted value
     *
     * @return  string  The redirection url
     */
    public function getRedirection(bool $with_selected_entries = false, array $params = []): string
    {
        $redirect_args = array_merge($params, $this->redir_args);
        if (isset($redirect_args['redir'])) {
            unset($redirect_args['redir']);
        }

        if ($with_selected_entries && $this->enable_redir_selection) {
            $redirect_args[$this->field_entries] = array_keys($this->entries);
        }

        return $this->uri . (str_contains($this->uri, '?') ? '&' : '?') . http_build_query($redirect_args) . $this->redir_anchor;
    }

    /**
     * Redirects to redirection page.
     *
     * @see     getRedirection  for arguments details
     *
     * @param   bool    $with_selected_entries  If true, add selected entries in url
     * @param   array   $params                 Extra parameters to append to redirection
     *                                          must be an array : each key is the name,
     *                                          each value is the wanted value
     * @return  never
     */
    public function redirect(bool $with_selected_entries = false, array $params = [])
    {
        Http::redirect($this->getRedirection($with_selected_entries, $params));
        exit;
    }

    /**
     * Returns current form URI, if any.
     *
     * @return  string  The form URI
     */
    public function getURI(): ?string
    {
        return $this->uri;
    }

    /**
     * Returns current form URI, if any.
     *
     * @return  string  The form URI
     */
    public function getCallerTitle(): string
    {
        return $this->caller_title;
    }

    /**
     * Returns current action, if any.
     *
     * @return  string  The action
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Proceeds action handling, if any.
     *
     * This method may issue an exit() if an action is being processed.
     *  If it returns, no action has been performed
     */
    public function process()
    {
        $this->setupRedir($this->from);
        $this->fetchEntries($this->from);
        if (isset($this->from['action'])) {
            $this->action = $this->from['action'];
            $performed    = false;
            if ($this->use_render) {
                ob_start();
            }

            try {
                foreach ($this->actions as $action => $callback) {
                    if ($this->from['action'] == $action) {
                        $performed = true;
                        call_user_func($callback, $this, $this->from);
                    }
                }
            } catch (Exception $e) {
                $this->error($e);
                $performed = true;
            }

            if ($this->use_render) {
                $this->render = (string) ob_get_contents();
                ob_end_clean();
            }
            if ($performed) {
                return true;
            }
        }
    }

    /**
     * Output action process contents.
     *
     * Only when property $use_render is set to true.
     */
    public function render(): void
    {
        echo (string) $this->render;
    }

    /**
     * Returns HTML code for selected entries as a table containing entries checkboxes.
     *
     * @return  string  The HTML code for checkboxes
     */
    public function getCheckboxes(): string
    {
        return $this->checkboxes()->render();
    }

    /**
     * Returns Form code for selected entries as a table containing entries checkboxes.
     *
     * @return  Table   The Table elements code for checkboxes
     */
    public function checkboxes(): Table
    {
        $items = [];
        foreach ($this->entries as $id => $title) {
            $items[] = (new Tr())
                ->items([
                    (new Td())
                        ->class('minimal')
                        ->items([
                            (new Checkbox([$this->field_entries . '[]'], true))
                                ->value($id),
                        ]),
                    (new Td())
                        ->text(Html::escapeHTML($title)),
                ]);
        }

        return (new Table())
            ->class('posts-list')
            ->items([
                (new Tr())
                    ->items([
                        (new Th())
                            ->colspan(2)
                            ->text($this->cb_title),
                    ]),
                ... $items,
            ]);
    }

    /**
     * Manage error.
     *
     * This method is called on Exception from self::process();
     * Default method does not stop script execution.
     *
     * @param   Exception   $e  The exception
     */
    public function error(Exception $e)
    {
        App::error()->add($e->getMessage());
    }

    /**
     * Displays the beginning of a page, if action does not redirects dirtectly.
     *
     * This method is called from the actions themselves.
     *
     * @param   string  $breadcrumb     Breadcrumb to display
     * @param   string  $head           Page header to include
     */
    abstract public function beginPage(string $breadcrumb = '', string $head = '');

    /**
     * Displays the ending of a page, if action does not redirects dirtectly.
     *
     * This method is called from the actions themselves.
     */
    abstract public function endPage();

    /**
     * Fills-in information by requesting into db.
     *
     * This method may setup the following attributes
     * - entries : list of entries (checked against permissions)
     *   entries ids are array keys, values contain entry description (if relevant)
     * - rs : MetaRecord given by db request
     *
     * @param   ArrayObject     $from
     */
    abstract protected function fetchEntries(ArrayObject $from);
}
