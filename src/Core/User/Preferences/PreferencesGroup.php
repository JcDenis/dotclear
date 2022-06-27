<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\User\Preferences;

// Dotclear\Core\User\Preferences\PreferencesGroup
use Dotclear\App;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\InvalidValueFormat;
use Dotclear\Exception\MissingOrEmptyValue;
use Exception;

/**
 * User preference workspace handling methods.
 *
 * @ingroup  Core User Preferences
 */
class PreferencesGroup
{
    /**
     * @var array<string,array> $global_preferences
     *                          Global preferences array
     */
    protected $global_preferences = [];

    /**
     * @var array<string,array> $local_preferences
     *                          Local preferences array
     */
    protected $local_preferences = [];

    /**
     * @var array<string,array> $preferences
     *                          Associative preferences array
     */
    protected $preferences = [];

    protected const WS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';
    protected const WS_ID_SCHEMA   = '/^[a-zA-Z][a-zA-Z0-9_]+$/';

    /**
     * Constructor.
     *
     * Retrieves user preferences and puts them in $preferences
     * array. Local (user) preferences have a highest priority than global preferences.
     *
     * @param null|string $user   The user ID
     * @param string      $group  The preferences group name
     * @param null|Record $record The recordset
     *
     * @throws InvalidValueFormat
     */
    public function __construct(protected ?string $user, public readonly string $group, ?Record $record = null)
    {
        if (!preg_match(self::WS_NAME_SCHEMA, $this->group)) {
            throw new InvalidValueFormat(sprintf(__('Invalid preferences group: %s'), $this->group));
        }

        try {
            if (null == $record) {
                try {
                    $sql = new SelectStatement();
                    $sql->columns([
                        'user_id',
                        'pref_id',
                        'pref_value',
                        'pref_type',
                        'pref_label',
                        'pref_ws',
                    ]);
                    $sql->from(App::core()->getPrefix() . 'pref');
                    $sql->where($sql->orGroup([
                        'user_id = ' . $sql->quote($this->user),
                        'user_id IS NULL',
                    ]));
                    $sql->and('pref_ws = ' . $sql->quote($this->group));
                    $sql->order('pref_id ASC');
                    $record = $sql->select();
                } catch (Exception $e) {
                    throw $e;
                }
            }
            while ($record->fetch()) {
                if ($record->field('pref_ws') != $this->group) {
                    break;
                }
                $id    = trim($record->field('pref_id'));
                $value = $record->field('pref_value');
                $type  = $record->field('pref_type');

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

                $array = $record->field('user_id') ? 'local' : 'global';

                $this->{$array . '_preferences'}[$id] = [
                    'ws'     => $this->group,
                    'value'  => $value,
                    'type'   => $type,
                    'label'  => (string) $record->field('pref_label'),
                    'global' => $record->field('user_id') == '',
                ];
            }

            $this->preferences = $this->global_preferences;

            foreach ($this->local_preferences as $id => $v) {
                $this->preferences[$id] = $v;
            }
        } catch (Exception) {
            trigger_error(__('Unable to retrieve preferences:') . ' ' . App::core()->con()->error(), E_USER_ERROR);
        }
    }

    /**
     * Check if a local preference exists.
     *
     * @param string $id The identifier
     *
     * @return bool True if local preference exists
     */
    public function hasLocalPreference(string $id): bool
    {
        return isset($this->local_preferences[$id]);
    }

    /**
     * Check if a global preference exists.
     *
     * @param string $id The preferences ID
     *
     * @return bool True if global preference exists
     */
    public function hasGlobalPreference(string $id): bool
    {
        return isset($this->global_preferences[$id]);
    }

    /**
     * Get preference value if exists.
     *
     * @param string $id The preference name
     *
     * @return mixed The user preference value
     */
    public function getPreference(string $id): mixed
    {
        return isset($this->preferences[$id]) && isset($this->preferences[$id]['value']) ?
            $this->preferences[$id]['value'] : null;
    }

    /**
     * Get global preference value if exists.
     *
     * @param string $id The preference name
     *
     * @return mixed The global preference value
     */
    public function getGlobalPreference(string $id): mixed
    {
        return isset($this->global_preferences[$id]) && isset($this->global_preferences[$id]['value']) ?
            $this->global_preferences[$id]['value'] : null;
    }

    /**
     * Get local preference value if exists.
     *
     * @param string $id The preference name
     *
     * @return mixed The local preference value
     */
    public function getLocalPreference(string $id): mixed
    {
        return isset($this->local_preferences[$id]) && isset($this->local_preferences[$id]['value']) ?
            $this->local_preferences[$id]['value'] : null;
    }

    /**
     * Set a preference in $preferences property.
     *
     * This sets the preference for script
     * execution time only and if preference exists.
     *
     * @param string $id    The preference name
     * @param mixed  $value The preference value
     */
    public function setPreference(string $id, mixed $value): void
    {
        if (isset($this->preferences[$id])) {
            $this->preferences[$id]['value'] = $value;
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
     * @throws InvalidValueFormat
     */
    public function putPreference(string $id, mixed $value, ?string $type = null, ?string $label = null, ?bool $value_change = true, bool $global = false): void
    {
        if (!preg_match(self::WS_ID_SCHEMA, $id)) {
            throw new InvalidValueFormat(sprintf(__('%s is not a valid pref id'), $id));
        }

        // We don't want to change pref value
        if (!$value_change) {
            if (!$global && $this->hasLocalPreference($id)) {
                $value = $this->local_preferences[$id]['value'];
            } elseif ($this->hasGlobalPreference($id)) {
                $value = $this->global_preferences[$id]['value'];
            }
        }

        // Pref type
        if ('double' == $type) {
            $type = 'float';
        } elseif (null === $type) {
            if (!$global && $this->hasLocalPreference($id)) {
                $type = $this->local_preferences[$id]['type'];
            } elseif ($this->hasGlobalPreference($id)) {
                $type = $this->global_preferences[$id]['type'];
            } else {
                $type = is_array($value) ? 'array' : 'string';
            }
        } elseif (!in_array($type, ['boolean', 'integer', 'float', 'array'])) {
            $type = 'string';
        }

        // We don't change label
        if (null == $label) {
            if (!$global && $this->hasLocalPreference($id)) {
                $label = $this->local_preferences[$id]['label'];
            } elseif ($this->hasGlobalPreference($id)) {
                $label = $this->global_preferences[$id]['label'];
            }
        }

        if ('array' != $type) {
            settype($value, $type);
        } else {
            $value = json_encode($value);
        }

        // If we are local, compare to global value
        if (!$global && $this->hasGlobalPreference($id)) {
            $g         = $this->global_preferences[$id];
            $same_pref = ($g['ws'] == $this->group && $g['value'] == $value && $g['type'] == $type && $g['label'] == $label);

            // Drop pref if same value as global
            if ($same_pref && $this->hasLocalPreference($id)) {
                $this->dropPreference($id);
            } elseif ($same_pref) {
                return;
            }
        }

        // Update
        if (($global && $this->hasGlobalPreference(id: $id) || !$global && $this->hasLocalPreference(id: $id))
            && $this->group == $this->preferences[$id]['ws']
        ) {
            $sql = new UpdateStatement();
            $sql->set([
                'pref_value = ' . $sql->quote('boolean' == $type ? (string) (int) $value : (string) $value),
                'pref_type = ' . $sql->quote($type),
                'pref_label = ' . $sql->quote($label),
            ]);
            $sql->where(
                $global ?
                'user_id IS NULL' :
                'user_id = ' . $sql->quote($this->user)
            );
            $sql->and('pref_id = ' . $sql->quote($id));
            $sql->and('pref_ws = ' . $sql->quote($this->group));
            $sql->from(App::core()->getPrefix() . 'pref');
            $sql->update();
        // Insert
        } else {
            $sql = new InsertStatement();
            $sql->columns([
                'pref_value',
                'pref_type',
                'pref_label',
                'pref_id',
                'user_id',
                'pref_ws',
            ]);
            $sql->line([[
                $sql->quote('boolean' == $type ? (string) (int) $value : (string) $value),
                $sql->quote($type),
                $sql->quote($label),
                $sql->quote($id),
                $global ? 'NULL' : $sql->quote($this->user),
                $sql->quote($this->group),
            ]]);
            $sql->from(App::core()->getPrefix() . 'pref');
            $sql->insert();
        }
    }

    /**
     * Rename an existing preference in a group.
     *
     * @param string $oldId The old preference ID
     * @param string $newId The new preference ID
     *
     * @throws MissingOrEmptyValue
     * @throws InvalidValueFormat
     *
     * @return bool True if preference successfully renamed
     */
    public function renamePreference(string $oldId, string $newId): bool
    {
        if (!$this->group) {
            throw new MissingOrEmptyValue(__('No workspace specified'));
        }

        if (!array_key_exists($oldId, $this->preferences) || array_key_exists($newId, $this->preferences)) {
            return false;
        }

        if (!preg_match(self::WS_ID_SCHEMA, $newId)) {
            throw new InvalidValueFormat(sprintf(__('%s is not a valid pref id'), $newId));
        }

        // Rename the pref in the preferences array
        $this->preferences[$newId] = $this->preferences[$oldId];
        unset($this->preferences[$oldId]);

        // Rename the pref in the database
        $sql = new UpdateStatement();
        $sql->set('pref_id = ' . $sql->quote($newId));
        $sql->where('pref_ws = ' . $sql->quote($this->group));
        $sql->and('pref_id = ' . $sql->quote($oldId));
        $sql->from(App::core()->getPrefix() . 'pref');
        $sql->update();

        return true;
    }

    /**
     * Remove an existing preference in a group.
     *
     * Apply to current preference user,
     * or global if user is not set.
     *
     * @param string $id The preference ID
     */
    public function dropPreference(string $id): void
    {
        unset($this->local_preferences[$id]);

        if (null === $this->user) {
            $this->dropGlobalPreference(id: $id);
        } else {
            $this->deletePreference(id: $id, where: "user_id = '" . App::core()->con()->escape($this->user) . "'");
        }
    }

    /**
     * Remove an existing global preference in a group.
     *
     * @param string $id The preference ID
     */
    public function dropGlobalPreference(string $id)
    {
        unset($this->global_preferences[$id]);

        $this->deletePreference(id: $id, where: 'user_id IS NULL');
    }

    /**
     * Remove an existing non global preference in a group.
     *
     * @param string $id The preference ID
     */
    public function dropNonGlobalPreference(string $id): void
    {
        $this->deletePreference(id: $id, where: 'user_id IS NOT NULL');
    }

    /**
     * Remove an existing preference in a group.
     *
     * @param string $id    The preference ID
     * @param string $where The user ID SQL where clause
     *
     * @throws MissingOrEmptyValue
     */
    private function deletePreference(string $id, string $where)
    {
        if (!$this->group) {
            throw new MissingOrEmptyValue(__('No namespace specified'));
        }

        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'pref');
        $sql->where($where);
        $sql->and('pref_id = ' . $sql->quote($id));
        $sql->and('pref_ws = ' . $sql->quote($this->group));
        $sql->delete();

        $this->preferences = $this->global_preferences;
        $this->preferences = array_merge($this->preferences, $this->local_preferences);
    }

    /**
     * Remove all existing local preferences in a group.
     *
     * @throws MissingOrEmptyValue
     */
    public function dropPreferences(): void
    {
        if (!$this->group) {
            throw new MissingOrEmptyValue(__('No namespace specified'));
        }

        if (null === $this->user) {
            $this->dropGlobalPreferences();

            return;
        }

        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'pref');
        $sql->where('user_id = ' . $sql->quote($this->user));
        $sql->and('pref_ws = ' . $sql->quote($this->group));
        $sql->delete();

        unset($this->local_preferences);
        $this->local_preferences = [];
        $this->preferences       = $this->global_preferences;
    }

    /**
     * Remove all existing global preferences in a group.
     *
     * @throws MissingOrEmptyValue
     */
    public function dropGlobalPreferences(): void
    {
        if (!$this->group) {
            throw new MissingOrEmptyValue(__('No namespace specified'));
        }

        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'pref');
        $sql->where('user_id IS NULL');
        $sql->and('pref_ws = ' . $sql->quote($this->group));
        $sql->delete();

        unset($this->global_preferences);
        $this->global_preferences = [];
        $this->preferences        = $this->local_preferences;
    }

    /**
     * Dump group preferences.
     *
     * @return array<string,mixed> The user preferences
     */
    public function dumpPreferences(): array
    {
        return $this->preferences;
    }

    /**
     * Dump group local preferences.
     *
     * @return array<string,mixed> The local preferences
     */
    public function dumpLocalPreferences(): array
    {
        return $this->local_preferences;
    }

    /**
     * Dump group global preferences.
     *
     * @return array<string,mixed> The global preferences
     */
    public function dumpGlobalPreferences(): array
    {
        return $this->global_preferences;
    }
}
