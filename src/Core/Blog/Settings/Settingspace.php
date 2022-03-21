<?php
/**
 * @class Dotclear\Core\Blog\Settings\Settingspace
 * @brief Dotclear core nspace (namespace) class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Settings;

use Dotclear\Database\Record;
use Dotclear\Exception\CoreException;

class Settingspace
{
    /** @var    string     Settings table name */
    protected $table;

    /** @var    string     Blog ID*/
    protected $blog_id;

    /** @var    array       Global settings array */
    protected $global_settings = [];

    /** @var    array       Local settings array */
    protected $local_settings = [];

    /** @var    array       Associative settings array */
    protected $settings = [];

    /** @var    string      Current namespace */
    protected $ns;

    protected const NS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';
    protected const NS_ID_SCHEMA   = '/^[a-zA-Z][a-zA-Z0-9_]+$/';

    /**
     * Constructor.
     *
     * Retrieves blog settings and puts them in $settings
     * array. Local (blog) settings have a highest priority than global settings.
     *
     * @param   string|null     $blog_id    The blog identifier
     * @param   string          $name       The namespace ID
     * @param   Record|null     $rs
     *
     * @throws     CoreException
     */
    public function __construct(?string $blog_id, string $name, ?Record $rs = null)
    {
        if (preg_match(self::NS_NAME_SCHEMA, $name)) {
            $this->ns = $name;
        } else {
            throw new CoreException(sprintf(__('Invalid setting Namespace: %s'), $name));
        }

        $this->table   = dotclear()->prefix . 'setting';
        $this->blog_id = $blog_id;

        $this->getSettings($rs);
    }

    private function getSettings(Record $rs = null): bool
    {
        if ($rs == null) {
            $strReq = 'SELECT blog_id, setting_id, setting_value, ' .
            'setting_type, setting_label, setting_ns ' .
            'FROM ' . $this->table . ' ' .
            "WHERE (blog_id = '" . dotclear()->con()->escape($this->blog_id) . "' " .
            'OR blog_id IS NULL) ' .
            "AND setting_ns = '" . dotclear()->con()->escape($this->ns) . "' " .
                'ORDER BY setting_id DESC ';

            try {
                $rs = dotclear()->con()->select($strReq);
            } catch (\Exception) {
                trigger_error(__('Unable to retrieve settings:') . ' ' . dotclear()->con()->error(), E_USER_ERROR);
            }
        }
        while ($rs->fetch()) {
            if ($rs->f('setting_ns') != $this->ns) {
                break;
            }
            $id    = trim($rs->f('setting_id'));
            $value = $rs->f('setting_value');
            $type  = $rs->f('setting_type');

            if ($type == 'array') {
                $value = @json_decode($value, true);
            } else {
                if ($type == 'float' || $type == 'double') {
                    $type = 'float';
                } elseif ($type != 'boolean' && $type != 'integer') {
                    $type = 'string';
                }
            }

            settype($value, $type);

            $array = $rs->blog_id ? 'local' : 'global';

            $this->{$array . '_settings'}[$id] = [
                'ns'     => $this->ns,
                'value'  => $value,
                'type'   => $type,
                'label'  => (string) $rs->f('setting_label'),
                'global' => $rs->blog_id == '',
            ];
        }

        $this->settings = $this->global_settings;

        foreach ($this->local_settings as $id => $v) {
            $this->settings[$id] = $v;
        }

        return true;
    }

    /**
     * Returns true if a setting exist, else false
     *
     * @param   string  $id         The identifier
     * @param   bool    $global     The global
     *
     * @return  bool
     */
    public function settingExists(string $id, bool $global = false): bool
    {
        $array = $global ? 'global' : 'local';

        return isset($this->{$array . '_settings'}[$id]);
    }

    /**
     * Returns setting value if exists.
     *
     * @param   string  $n  Setting name
     *
     * @return  mixed
     */
    public function get(string $n): mixed
    {
        return isset($this->settings[$n]) && isset($this->settings[$n]['value']) ?
                $this->settings[$n]['value'] : null;
    }

    /**
     * Returns global setting value if exists.
     *
     * @param   string  $n   Setting name
     *
     * @return  mixed
     */
    public function getGlobal(string $n): mixed
    {
        return isset($this->global_settings[$n]) && isset($this->global_settings[$n]['value']) ?
            $this->global_settings[$n]['value'] : null;
    }

    /**
     * Returns local setting value if exists.
     *
     * @param   string  $n   Setting name
     *
     * @return  mixed
     */
    public function getLocal(string $n): mixed
    {
        return isset($this->local_settings[$n]) && isset($this->local_settings[$n]['value']) ?
            $this->local_settings[$n]['value'] : null;
    }

    /**
     * Magic __get method.
     *
     * @see self::get()
     *
     * @param   string  $n  Setting name
     *
     * @return  mixed
     */
    public function __get(string $n): mixed
    {
        return $this->get($n);
    }

    /**
     * Sets a setting in $settings property.
     *
     * This sets the setting for script
     * execution time only and if setting exists.
     *
     * @param   string  $n  The setting name
     * @param   mixed   $v  The setting value
     */
    public function set(string $n, mixed $v): void
    {
        if (isset($this->settings[$n])) {
            $this->settings[$n]['value'] = $v;
        }
    }

    /**
     * Magic __set method.
     *
     * @see self::set()
     *
     * @param   string  $n  The setting name
     * @param   mixed   $v  The setting value
     */
    public function __set(string $n, mixed $v): void
    {
        $this->set($n, $v);
    }

    /**
     * Magic __isset method
     *
     * Required to test empty(ns->setting)
     *
     * @param   string  $n  The setting name
     * @return  bool        Setting is set
     */
    public function __isset(string $n)
    {
        return isset($this->settings[$n]) && isset($this->settings[$n]['value']);
    }

    /**
     * Creates or updates a setting.
     *
     * $type could be 'string', 'integer', 'float', 'boolean', 'array' or null. If $type is
     * null and setting exists, it will keep current setting type.
     *
     * $value_change allow you to not change setting. Useful if you need to change
     * a setting label or type and don't want to change its value.
     *
     * @param   string  $id             The setting identifier
     * @param   mixed   $value          The setting value
     * @param   string  $type           The setting type
     * @param   string  $label          The setting label
     * @param   bool    $value_change   Change setting value or not
     * @param   bool    $global         Setting is global
     *
     * @throws     CoreException
     */
    public function put(string $id, mixed $value, ?string $type = null, ?string $label = null, bool $value_change = true, bool $global = false): void
    {
        if (!preg_match(self::NS_ID_SCHEMA, $id)) {
            throw new CoreException(sprintf(__('%s is not a valid setting id'), $id));
        }

        # We don't want to change setting value
        if (!$value_change) {
            if (!$global && $this->settingExists($id, false)) {
                $value = $this->local_settings[$id]['value'];
            } elseif ($this->settingExists($id, true)) {
                $value = $this->global_settings[$id]['value'];
            }
        }

        # Setting type
        if ($type == 'double') {
            $type = 'float';
        } elseif ($type === null) {
            if (!$global && $this->settingExists($id, false)) {
                $type = $this->local_settings[$id]['type'];
            } elseif ($this->settingExists($id, true)) {
                $type = $this->global_settings[$id]['type'];
            } else {
                if (is_array($value)) {
                    $type = 'array';
                } else {
                    $type = 'string';
                }
            }
        } elseif ($type != 'boolean' && $type != 'integer' && $type != 'float' && $type != 'array') {
            $type = 'string';
        }

        # We don't change label
        if ($label == null) {
            if (!$global && $this->settingExists($id, false)) {
                $label = $this->local_settings[$id]['label'];
            } elseif ($this->settingExists($id, true)) {
                $label = $this->global_settings[$id]['label'];
            }
        }

        if ($type != 'array') {
            settype($value, $type);
        } else {
            $value = json_encode($value);
        }

        $cur                = dotclear()->con()->openCursor($this->table);
        $cur->setting_value = ($type == 'boolean') ? (string) (int) $value : (string) $value;
        $cur->setting_type  = $type;
        $cur->setting_label = $label;

        #If we are local, compare to global value
        if (!$global && $this->settingExists($id, true)) {
            $g            = $this->global_settings[$id];
            $same_setting = ($g['ns'] == $this->ns && $g['value'] == $value && $g['type'] == $type && $g['label'] == $label);

            # Drop setting if same value as global
            if ($same_setting && $this->settingExists($id, false)) {
                $this->drop($id);
            } elseif ($same_setting) {
                return;
            }
        }

        if ($this->settingExists($id, $global) && $this->ns == $this->settings[$id]['ns']) {
            if ($global) {
                $where = 'WHERE blog_id IS NULL ';
            } else {
                $where = "WHERE blog_id = '" . dotclear()->con()->escape($this->blog_id) . "' ";
            }

            $cur->update($where . "AND setting_id = '" . dotclear()->con()->escape($id) . "' AND setting_ns = '" . dotclear()->con()->escape($this->ns) . "' ");
        } else {
            $cur->setting_id = $id;
            $cur->blog_id    = $global ? null : $this->blog_id;
            $cur->setting_ns = $this->ns;

            $cur->insert();
        }
    }

    /**
     * Rename an existing setting in a Namespace
     *
     * @param   string  $oldId  The old setting identifier
     * @param   string  $newId  The new setting identifier
     *
     * @throws  CoreException
     *
     * @return  bool
     */
    public function rename(string $oldId, string $newId): bool
    {
        if (!$this->ns) {
            throw new CoreException(__('No namespace specified'));
        }

        if (!array_key_exists($oldId, $this->settings) || array_key_exists($newId, $this->settings)) {
            return false;
        }

        if (!preg_match(self::NS_ID_SCHEMA, $newId)) {
            throw new CoreException(sprintf(__('%s is not a valid setting id'), $newId));
        }

        // Rename the setting in the settings array
        $this->settings[$newId] = $this->settings[$oldId];
        unset($this->settings[$oldId]);

        // Rename the setting in the database
        $strReq = 'UPDATE ' . $this->table .
        " SET setting_id = '" . dotclear()->con()->escape($newId) . "' " .
        " WHERE setting_ns = '" . dotclear()->con()->escape($this->ns) . "' " .
        " AND setting_id = '" . dotclear()->con()->escape($oldId) . "' ";
        dotclear()->con()->execute($strReq);

        return true;
    }

    /**
     * Removes an existing setting in a Namespace.
     *
     * @param   string  $id     The setting identifier
     *
     * @throws  CoreException
     */
    public function drop(string $id): void
    {
        if (!$this->ns) {
            throw new CoreException(__('No namespace specified'));
        }

        $strReq = 'DELETE FROM ' . $this->table . ' ';

        if ($this->blog_id === null) {
            $strReq .= 'WHERE blog_id IS NULL ';
        } else {
            $strReq .= "WHERE blog_id = '" . dotclear()->con()->escape($this->blog_id) . "' ";
        }

        $strReq .= "AND setting_id = '" . dotclear()->con()->escape($id) . "' ";
        $strReq .= "AND setting_ns = '" . dotclear()->con()->escape($this->ns) . "' ";

        dotclear()->con()->execute($strReq);
    }

    /**
     * Removes every existing specific setting in a namespace
     *
     * @param  string   $id         Setting ID
     * @param  bool     $global     Remove global setting too
     *
     * @throws CoreException
     */
    public function dropEvery(string $id, bool $global = false): void
    {
        if (!$this->ns) {
            throw new CoreException(__('No namespace specified'));
        }

        $strReq = 'DELETE FROM ' . $this->table . ' WHERE ';
        if (!$global) {
            $strReq .= 'blog_id IS NOT NULL AND ';
        }
        $strReq .= "setting_id = '" . dotclear()->con()->escape($id) . "' AND setting_ns = '" . dotclear()->con()->escape($this->ns) . "' ";

        dotclear()->con()->execute($strReq);
    }

    /**
     * Removes all existing settings in a Namespace.
     *
     * @param   bool    $force_global   Force global pref drop
     *
     * @throws  CoreException
     */
    public function dropAll(bool $force_global = false): void
    {
        if (!$this->ns) {
            throw new CoreException(__('No namespace specified'));
        }

        $strReq = 'DELETE FROM ' . $this->table . ' ';

        if (($force_global) || ($this->blog_id === null)) {
            $strReq .= 'WHERE blog_id IS NULL ';
            $global = true;
        } else {
            $strReq .= "WHERE blog_id = '" . dotclear()->con()->escape($this->blog_id) . "' ";
            $global = false;
        }

        $strReq .= "AND setting_ns = '" . dotclear()->con()->escape($this->ns) . "' ";

        dotclear()->con()->execute($strReq);

        $array = $global ? 'global' : 'local';
        unset($this->{$array . '_settings'});
        $this->{$array . '_settings'} = [];

        $array          = $global ? 'local' : 'global';
        $this->settings = $this->{$array . '_settings'};
    }

    /**
     * Dumps a namespace.
     *
     * @return  string
     */
    public function dumpNamespace(): string
    {
        return $this->ns;
    }

    /**
     * Dumps settings.
     *
     * @return  array
     */
    public function dumpSettings(): array
    {
        return $this->settings;
    }

    /**
     * Dumps local settings.
     *
     * @return  array
     */
    public function dumpLocalSettings(): array
    {
        return $this->local_settings;
    }

    /**
     * Dumps global settings.
     *
     * @return  array
     */
    public function dumpGlobalSettings(): array
    {
        return $this->global_settings;
    }
}
