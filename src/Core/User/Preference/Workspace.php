<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\User\Preference;

// Dotclear\Core\User\Preference\Workspace
use Dotclear\App;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;
use Exception;

/**
 * User preference workspace handling methods.
 *
 * @ingroup  Core User Preference
 */
class Workspace
{
    /**
     * @var array<string,array> $global_prefs
     *                          Global prefs array
     */
    protected $global_prefs = [];

    /**
     * @var array<string,array> $local_prefs
     *                          Local prefs array
     */
    protected $local_prefs = [];

    /**
     * @var array<string,array> $prefs
     *                          Associative prefs array
     */
    protected $prefs = [];

    /**
     * @var string $ws
     *             Current workspace
     */
    protected $ws;

    protected const WS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';
    protected const WS_ID_SCHEMA   = '/^[a-zA-Z][a-zA-Z0-9_]+$/';

    /**
     * Constructor.
     *
     * Retrieves user prefs and puts them in $prefs
     * array. Local (user) prefs have a highest priority than global prefs.
     *
     * @param null|string $user_id The user identifier
     * @param string      $name    The name
     * @param null|Record $rs      The recordset
     *
     * @throws CoreException
     */
    public function __construct(protected ?string $user_id, string $name, ?Record $rs = null)
    {
        if (preg_match(self::WS_NAME_SCHEMA, $name)) {
            $this->ws = $name;
        } else {
            throw new CoreException(sprintf(__('Invalid dcWorkspace: %s'), $name));
        }

        try {
            $this->getPrefs($rs);
        } catch (\Exception) {
            trigger_error(__('Unable to retrieve prefs:') . ' ' . App::core()->con()->error(), E_USER_ERROR);
        }
    }

    /**
     * Get preferences.
     *
     * @param null|Record $rs Record instance
     */
    private function getPrefs(?Record $rs = null): bool
    {
        if (null == $rs) {
            try {
                $sql = new SelectStatement(__METHOD__);
                $rs  = $sql
                    ->columns([
                        'user_id',
                        'pref_id',
                        'pref_value',
                        'pref_type',
                        'pref_label',
                        'pref_ws',
                    ])
                    ->from(App::core()->prefix() . 'pref')
                    ->where($sql->orGroup([
                        'user_id = ' . $sql->quote($this->user_id),
                        'user_id IS NULL',
                    ]))
                    ->and('pref_ws = ' . $sql->quote($this->ws))
                    ->order('pref_id ASC')
                    ->select()
                ;
            } catch (Exception $e) {
                throw $e;
            }
        }
        while ($rs->fetch()) {
            if ($rs->f('pref_ws') != $this->ws) {
                break;
            }
            $id    = trim($rs->f('pref_id'));
            $value = $rs->f('pref_value');
            $type  = $rs->f('pref_type');

            if ('array' == $type) {
                $value = @json_decode($value, true);
            } else {
                if ('float' == $type || 'double' == $type) {
                    $type = 'float';
                } elseif ('boolean' != $type && 'integer' != $type) {
                    $type = 'string';
                }
            }

            settype($value, $type);

            $array = $rs->f('user_id') ? 'local' : 'global';

            $this->{$array . '_prefs'}[$id] = [
                'ws'     => $this->ws,
                'value'  => $value,
                'type'   => $type,
                'label'  => (string) $rs->f('pref_label'),
                'global' => $rs->f('user_id') == '',
            ];
        }

        $this->prefs = $this->global_prefs;

        foreach ($this->local_prefs as $id => $v) {
            $this->prefs[$id] = $v;
        }

        return true;
    }

    /**
     * Check if a pref exists.
     *
     * @param string $id     The identifier
     * @param bool   $global The global
     */
    public function prefExists(string $id, bool $global = false): bool
    {
        $array = $global ? 'global' : 'local';

        return isset($this->{$array . '_prefs'}[$id]);
    }

    /**
     * Get pref value if exists.
     *
     * @param string $n Pref name
     */
    public function get(string $n): mixed
    {
        return isset($this->prefs[$n]) && isset($this->prefs[$n]['value']) ?
            $this->prefs[$n]['value'] : null;
    }

    /**
     * Get global pref value if exists.
     *
     * @param string $n Pref name
     */
    public function getGlobal(string $n): mixed
    {
        return isset($this->global_prefs[$n]) && isset($this->global_prefs[$n]['value']) ?
            $this->global_prefs[$n]['value'] : null;
    }

    /**
     * Get local pref value if exists.
     *
     * @param string $n Pref name
     */
    public function getLocal(string $n): mixed
    {
        return isset($this->local_prefs[$n]) && isset($this->local_prefs[$n]['value']) ?
            $this->local_prefs[$n]['value'] : null;
    }

    /**
     * Set a pref in $prefs property.
     *
     * This sets the pref for script
     * execution time only and if pref exists.
     *
     * @param string $n The pref name
     * @param mixed  $v The pref value
     */
    public function set(string $n, mixed $v): void
    {
        if (isset($this->prefs[$n])) {
            $this->prefs[$n]['value'] = $v;
        }
    }

    /**
     * Create or update a pref.
     *
     * $type could be 'string', 'integer', 'float', 'boolean' or null. If $type is
     * null and pref exists, it will keep current pref type.
     *
     * $value_change allow you to not change pref. Useful if you need to change
     * a pref label or type and don't want to change its value.
     *
     * @param string    $id           The pref identifier
     * @param mixed     $value        The pref value
     * @param string    $type         The pref type
     * @param string    $label        The pref label
     * @param null|bool $value_change Change pref value or not
     * @param bool      $global       Pref is global
     *
     * @throws CoreException
     */
    public function put(string $id, mixed $value, ?string $type = null, ?string $label = null, ?bool $value_change = true, bool $global = false): void
    {
        if (!preg_match(self::WS_ID_SCHEMA, $id)) {
            throw new CoreException(sprintf(__('%s is not a valid pref id'), $id));
        }

        // We don't want to change pref value
        if (!$value_change) {
            if (!$global && $this->prefExists($id, false)) {
                $value = $this->local_prefs[$id]['value'];
            } elseif ($this->prefExists($id, true)) {
                $value = $this->global_prefs[$id]['value'];
            }
        }

        // Pref type
        if ('double' == $type) {
            $type = 'float';
        } elseif (null === $type) {
            if (!$global && $this->prefExists($id, false)) {
                $type = $this->local_prefs[$id]['type'];
            } elseif ($this->prefExists($id, true)) {
                $type = $this->global_prefs[$id]['type'];
            } else {
                $type = is_array($value) ? 'array' : 'string';
            }
        } elseif (!in_array($type, ['boolean', 'integer', 'float', 'array'])) {
            $type = 'string';
        }

        // We don't change label
        if (null == $label) {
            if (!$global && $this->prefExists($id, false)) {
                $label = $this->local_prefs[$id]['label'];
            } elseif ($this->prefExists($id, true)) {
                $label = $this->global_prefs[$id]['label'];
            }
        }

        if ('array' != $type) {
            settype($value, $type);
        } else {
            $value = json_encode($value);
        }

        // If we are local, compare to global value
        if (!$global && $this->prefExists($id, true)) {
            $g         = $this->global_prefs[$id];
            $same_pref = ($g['ws'] == $this->ws && $g['value'] == $value && $g['type'] == $type && $g['label'] == $label);

            // Drop pref if same value as global
            if ($same_pref && $this->prefExists($id, false)) {
                $this->drop($id);
            } elseif ($same_pref) {
                return;
            }
        }

        // Update
        if ($this->prefExists($id, $global) && $this->ws == $this->prefs[$id]['ws']) {
            $sql = new UpdateStatement(__METHOD__);
            $sql
                ->set([
                    'pref_value = ' . $sql->quote('boolean' == $type ? (string) (int) $value : (string) $value),
                    'pref_type = ' . $sql->quote($type),
                    'pref_label = ' . $sql->quote($label),
                ])
                ->where(
                    $global ?
                    'user_id IS NULL' :
                    'user_id = ' . $sql->quote($this->user_id)
                )
                ->and('pref_id = ' . $sql->quote($id))
                ->and('pref_ws = ' . $sql->quote($this->ws))
                ->from(App::core()->prefix() . 'pref')
                ->update()
            ;
        // Insert
        } else {
            $sql = new InsertStatement(__METHOD__);
            $sql
                ->columns([
                    'pref_value',
                    'pref_type',
                    'pref_label',
                    'pref_id',
                    'user_id',
                    'pref_ws',
                ])
                ->line([[
                    $sql->quote('boolean' == $type ? (string) (int) $value : (string) $value),
                    $sql->quote($type),
                    $sql->quote($label),
                    $sql->quote($id),
                    $global ? 'NULL' : $sql->quote($this->user_id),
                    $sql->quote($this->ws),
                ]])
                ->from(App::core()->prefix() . 'pref')
                ->insert()
            ;
        }
    }

    /**
     * Rename an existing pref in a Workspace.
     *
     * @param string $oldId The old identifier
     * @param string $newId The new identifier
     *
     * @throws CoreException
     *
     * @return bool false is error, true if renamed
     */
    public function rename(string $oldId, string $newId): bool
    {
        if (!$this->ws) {
            throw new CoreException(__('No workspace specified'));
        }

        if (!array_key_exists($oldId, $this->prefs) || array_key_exists($newId, $this->prefs)) {
            return false;
        }

        if (!preg_match(self::WS_ID_SCHEMA, $newId)) {
            throw new CoreException(sprintf(__('%s is not a valid pref id'), $newId));
        }

        // Rename the pref in the prefs array
        $this->prefs[$newId] = $this->prefs[$oldId];
        unset($this->prefs[$oldId]);

        // Rename the pref in the database
        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->set('pref_id = ' . $sql->quote($newId))
            ->where('pref_ws = ' . $sql->quote($this->ws))
            ->and('pref_id = ' . $sql->quote($oldId))
            ->from(App::core()->prefix() . 'pref')
            ->update()
        ;

        return true;
    }

    /**
     * Remove an existing pref. Workspace.
     *
     * @param string $id           The pref identifier
     * @param bool   $force_global Force global pref drop
     *
     * @throws CoreException
     */
    public function drop(string $id, bool $force_global = false): void
    {
        if (!$this->ws) {
            throw new CoreException(__('No workspace specified'));
        }

        $global = $force_global || null === $this->user_id;

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where(
                $global ?
                'user_id IS NULL' :
                'user_id = ' . $sql->quote($this->user_id)
            )
            ->and('pref_id = ' . $sql->quote($id))
            ->and('pref_ws = ' . $sql->quote($this->ws))
            ->from(App::core()->prefix() . 'pref')
            ->delete()
        ;

        if ($this->prefExists($id, $global)) {
            $array = $global ? 'global' : 'local';
            unset($this->{$array . '_prefs'}[$id]);
        }

        $this->prefs = $this->global_prefs;
        foreach ($this->local_prefs as $id => $v) {
            $this->prefs[$id] = $v;
        }
    }

    /**
     * Remove every existing specific pref. in a workspace.
     *
     * @param string $id     Pref ID
     * @param bool   $global Remove global pref too
     */
    public function dropEvery(string $id, bool $global = false): void
    {
        if (!$this->ws) {
            throw new CoreException(__('No workspace specified'));
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where('pref_id = ' . $sql->quote($id))
            ->and('pref_ws = ' . $sql->quote($this->ws))
        ;

        if (!$global) {
            $sql->and('user_id IS NOT NULL');
        }

        $sql
            ->from(App::core()->prefix() . 'pref')
            ->delete()
        ;
    }

    /**
     * Remove all existing pref. in a Workspace.
     *
     * @param bool $force_global Remove global prefs too
     *
     * @throws CoreException
     */
    public function dropAll(bool $force_global = false): void
    {
        if (!$this->ws) {
            throw new CoreException(__('No workspace specified'));
        }

        $global = $force_global || null === $this->user_id;

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where(
                $global ?
                'user_id IS NULL' :
                'user_id = ' . $sql->quote($this->user_id)
            )
            ->and('pref_ws = ' . $sql->quote($this->ws))
            ->from(App::core()->prefix() . 'pref')
            ->delete()
        ;

        $array = $global ? 'global' : 'local';
        unset($this->{$array . '_prefs'});
        $this->{$array . '_prefs'} = [];

        $array       = $global ? 'local' : 'global';
        $this->prefs = $this->{$array . '_prefs'};
    }

    /**
     * Dump a workspace.
     */
    public function dumpWorkspace(): string
    {
        return $this->ws;
    }

    /**
     * Dump preferences.
     */
    public function dumpPrefs(): array
    {
        return $this->prefs;
    }

    /**
     * Dump local preferences.
     */
    public function dumpLocalPrefs(): array
    {
        return $this->local_prefs;
    }

    /**
     * Dump global preferences.
     */
    public function dumpGlobalPrefs(): array
    {
        return $this->global_prefs;
    }
}
