<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Settings;

// Dotclear\Core\Blog\Settings\SettingsGroup
use Dotclear\App;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\InvalidValueFormat;
use Dotclear\Exception\MissingOrEmptyValue;

/**
 * Blog settings namespace handling methods.
 *
 * @ingroup  Core Setting
 */
final class SettingsGroup
{
    private const NS_GROUP_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';
    private const NS_ID_SCHEMA    = '/^[a-zA-Z][a-zA-Z0-9_]+$/';

    /**
     * @var array<string,Setting> $global_settings
     *                            Global settings array
     */
    private $global_settings = [];

    /**
     * @var array<string,Setting> $local_settings
     *                            Local settings array
     */
    private $local_settings = [];

    /**
     * @var array<string,Setting> $settings
     *                            Associative settings array
     */
    private $settings = [];

    /**
     * Constructor.
     *
     * Retrieves blog settings and puts them in an array.
     *
     * Local (blog) settings have a highest priority than global settings.
     *
     * @param null|string $blog   The blog ID
     * @param string      $group  The settings group ID
     * @param null|Record $record The record, if any
     *
     * @throws InvalidValueFormat
     */
    public function __construct(private string|null $blog, public readonly string $group, ?Record $record = null)
    {
        if (!preg_match(self::NS_GROUP_SCHEMA, $this->group)) {
            throw new InvalidValueFormat(sprintf(__('Invalid setting Namespace: %s'), $this->group));
        }

        if (null == $record) {
            try {
                $sql = new SelectStatement();
                $sql->columns([
                    'blog_id',
                    'setting_id',
                    'setting_value',
                    'setting_type',
                    'setting_label',
                    'setting_ns',
                ]);
                $sql->from(App::core()->getPrefix() . 'setting');
                $sql->where($sql->orGroup([
                    'blog_id = ' . $sql->quote($this->blog),
                    'blog_id IS NULL',
                ]));
                $sql->and('setting_ns = ' . $sql->quote($this->group));
                $sql->order('setting_id DESC');

                $record = $sql->select();
            } catch (\Exception) {
                trigger_error(__('Unable to retrieve settings:') . ' ' . App::core()->con()->error(), E_USER_ERROR);
            }
        }
        while ($record->fetch()) {
            if ($record->field('setting_ns') != $this->group) {
                break;
            }
            $id    = trim($record->field('setting_id'));
            $value = $record->field('setting_value');
            $type  = $record->field('setting_type');

            if ('array' == $type) {
                $value = @json_decode($value, true);
            } elseif ('float' == $type || 'double' == $type) {
                $type = 'float';
            } elseif ('boolean' != $type && 'integer' != $type) {
                $type = 'string';
            }

            settype($value, $type);

            $array = $record->field('blog_id') ? 'local' : 'global';

            $this->{$array . '_settings'}[$id] = new Setting(
                group: $this->group,
                id: $id,
                value: $value,
                type: $type,
                label: (string) $record->field('setting_label'),
                global: $record->field('blog_id') == '',
            );
        }

        $this->settings = array_merge($this->global_settings, $this->local_settings);
    }

    /**
     * Check if a setting exists in local settings.
     *
     * @param string $id The setting ID
     *
     * @return bool True if setting exists
     */
    public function hasLocalSetting(string $id): bool
    {
        return isset($this->local_settings[$id]);
    }

    /**
     * Check if a setting exists in global settings.
     *
     * @param string $id The setting ID
     *
     * @return bool True if setting exists
     */
    public function hasGlobalSetting(string $id): bool
    {
        return isset($this->global_settings[$id]);
    }

    /**
     * Get setting value if exists.
     *
     * @param string $id The setting ID
     *
     * @return mixed The setting value (or null if not)
     */
    public function getSetting(string $id): mixed
    {
        return isset($this->settings[$id]) ? $this->settings[$id]->value : null;
    }

    /**
     * Get global setting value if exists.
     *
     * @param string $id The setting ID
     *
     * @return mixed The global setting value (or null if not)
     */
    public function getGlobalSetting(string $id): mixed
    {
        return isset($this->global_settings[$id]) ? $this->global_settings[$id]->value : null;
    }

    /**
     * Get local setting value if exists.
     *
     * @param string $id The setting ID
     *
     * @return mixed The local setting value (or null if not)
     */
    public function getLocalSetting(string $id): mixed
    {
        return isset($this->local_settings[$id]) ? $this->local_settings[$id]->value : null;
    }

    /**
     * Set a setting in $settings property.
     *
     * This sets the setting for script
     * execution time only and if setting exists.
     *
     * @param string $id    The setting ID
     * @param mixed  $value The setting value
     */
    public function setSetting(string $id, mixed $value): void
    {
        if (isset($this->settings[$id])) {
            $this->settings[$id]->value = $value;
        }
    }

    /**
     * Create or update a setting.
     *
     * $type could be 'string', 'integer', 'float', 'boolean', 'array' or null.
     * If $type is null and setting exists, it will keep current setting type.
     *
     * $change allow you to not change setting.
     * Useful if you need to change a setting label or type
     * and don't want to change its value.
     *
     * @param string $id     The setting ID
     * @param mixed  $value  The setting value
     * @param string $type   The setting type
     * @param string $label  The setting label
     * @param bool   $change Change setting value or not
     * @param bool   $global Setting is global
     *
     * @throws InvalidValueFormat
     */
    public function putSetting(string $id, mixed $value, ?string $type = null, ?string $label = null, bool $change = true, bool $global = false): void
    {
        if (!preg_match(self::NS_ID_SCHEMA, $id)) {
            throw new InvalidValueFormat(sprintf(__('%s is not a valid setting id'), $id));
        }

        // We don't want to change setting value
        if (!$change) {
            if (!$global && $this->hasLocalSetting(id: $id)) {
                $value = $this->local_settings[$id]->value;
            } elseif ($this->hasGlobalSetting(id: $id)) {
                $value = $this->global_settings[$id]->value;
            }
        }

        // Setting type
        if ('double' == $type) {
            $type = 'float';
        } elseif (null === $type) {
            if (!$global && $this->hasLocalSetting(id: $id)) {
                $type = $this->local_settings[$id]->type;
            } elseif ($this->hasGlobalSetting(id: $id)) {
                $type = $this->global_settings[$id]->type;
            } else {
                if (is_array($value)) {
                    $type = 'array';
                } else {
                    $type = 'string';
                }
            }
        } elseif ('boolean' != $type && 'integer' != $type && 'float' != $type && 'array' != $type) {
            $type = 'string';
        }

        // We don't change label
        if (null == $label) {
            if (!$global && $this->hasLocalSetting(id: $id)) {
                $label = $this->local_settings[$id]->label;
            } elseif ($this->hasGlobalSetting(id: $id)) {
                $label = $this->global_settings[$id]->label;
            }
        }

        if ('array' != $type) {
            settype($value, $type);
        } else {
            $value = json_encode($value);
        }

        // If we are local, compare to global value
        if (!$global && $this->hasGlobalSetting(id: $id)) {
            $g            = $this->global_settings[$id];
            $same_setting = ($g->group == $this->group && $g->value == $value && $g->type == $type && $g->label == $label);

            // Drop setting if same value as global
            if ($same_setting && $this->hasLocalSetting(id: $id)) {
                $this->dropSetting($id);
            } elseif ($same_setting) {
                return;
            }
        }

        // Update
        if (($global && $this->hasGlobalSetting(id: $id) || !$global && $this->hasLocalSetting(id: $id))
            && $this->group == $this->settings[$id]->group
        ) {
            $sql = new UpdateStatement();
            $sql->set([
                'setting_value = ' . $sql->quote('boolean' == $type ? (string) (int) $value : (string) $value),
                'setting_type = ' . $sql->quote($type),
                'setting_label = ' . $sql->quote($label),
            ]);
            $sql->where(
                $global ?
                'blog_id IS NULL' :
                'blog_id = ' . $sql->quote($this->blog)
            );
            $sql->and('setting_id = ' . $sql->quote($id));
            $sql->and('setting_ns = ' . $sql->quote($this->group));
            $sql->from(App::core()->getPrefix() . 'setting');
            $sql->update();
        // Insert
        } else {
            $sql = new InsertStatement();
            $sql->columns([
                'setting_value',
                'setting_type',
                'setting_label',
                'setting_id',
                'blog_id',
                'setting_ns',
            ]);
            $sql->line([[
                $sql->quote('boolean' == $type ? (string) (int) $value : (string) $value),
                $sql->quote($type),
                $sql->quote($label),
                $sql->quote($id),
                $global ? 'NULL' : $sql->quote($this->blog),
                $sql->quote($this->group),
            ]]);
            $sql->from(App::core()->getPrefix() . 'setting');
            $sql->insert();
        }
    }

    /**
     * Rename an existing setting in a group.
     *
     * @param string $from The old setting ID
     * @param string $to   The new setting ID
     *
     * @throws MissingOrEmptyValue
     * @throws InvalidValueFormat
     *
     * @return bool True on success
     */
    public function renameSetting(string $from, string $to): bool
    {
        if (!$this->group) {
            throw new MissingOrEmptyValue(__('No namespace specified'));
        }

        if (!array_key_exists($from, $this->settings) || array_key_exists($to, $this->settings)) {
            return false;
        }

        if (!preg_match(self::NS_ID_SCHEMA, $to)) {
            throw new InvalidValueFormat(sprintf(__('%s is not a valid setting id'), $to));
        }

        // Rename the setting in the settings array
        $this->settings[$to] = $this->settings[$from];
        unset($this->settings[$from]);

        // Rename the setting in the database
        $sql = new UpdateStatement();
        $sql->from(App::core()->getPrefix() . 'setting');
        $sql->set('setting_id = ' . $sql->quote($to));
        $sql->where('setting_ns = ' . $sql->quote($this->group));
        $sql->and('setting_id = ' . $sql->quote($from));
        $sql->update();

        return true;
    }

    /**
     * Remove an existing setting in a group.
     *
     * Apply to current settings blog,
     * or global if blog is not set.
     *
     * @param string $id The setting ID
     */
    public function dropSetting(string $id): void
    {
        if (null === $this->blog) {
            $this->dropGlobalSetting(id: $id);
        } else {
            $this->deleteSetting(id: $id, where: "blog_id = '" . App::core()->con()->escape($this->blog) . "'");
        }
    }

    /**
     * Remove an existing global setting in a group.
     *
     * @param string $id The setting ID
     */
    public function dropGlobalSetting(string $id)
    {
        $this->deleteSetting(id: $id, where: 'blog_id IS NULL');
    }

    /**
     * Remove an existing non global setting in a group.
     *
     * @param string $id The setting ID
     */
    public function dropNonGlobalSetting(string $id): void
    {
        $this->deleteSetting(id: $id, where: 'blog_id IS NOT NULL');
    }

    /**
     * Remove an existing setting in a group.
     *
     * @param string $id    The setting ID
     * @param string $where The blog ID SQL where clause
     *
     * @throws MissingOrEmptyValue
     */
    private function deleteSetting(string $id, string $where)
    {
        if (!$this->group) {
            throw new MissingOrEmptyValue(__('No namespace specified'));
        }

        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'setting');
        $sql->where($where);
        $sql->and('setting_id = ' . $sql->quote($id));
        $sql->and('setting_ns = ' . $sql->quote($this->group));
        $sql->delete();
    }

    /**
     * Remove all existing settings in a group.
     *
     * Apply to current settings blog,
     * or global if blog is not set.
     *
     * @throws MissingOrEmptyValue
     */
    public function dropSettings(): void
    {
        if (!$this->group) {
            throw new MissingOrEmptyValue(__('No namespace specified'));
        }

        if (null === $this->blog) {
            $this->dropGlobalSettings();

            return;
        }

        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'setting');
        $sql->where('blog_id = ' . $sql->quote($this->blog));
        $sql->and('setting_ns = ' . $sql->quote($this->group));
        $sql->delete();

        unset($this->local_settings);
        $this->local_settings = [];
        $this->settings       = $this->global_settings;
    }

    /**
     * Remove all existing global settings in a group.
     *
     * @throws MissingOrEmptyValue
     */
    public function dropGlobalSettings()
    {
        if (!$this->group) {
            throw new MissingOrEmptyValue(__('No namespace specified'));
        }

        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'setting');
        $sql->where('blog_id IS NULL');
        $sql->and('setting_ns = ' . $sql->quote($this->group));
        $sql->delete();

        unset($this->global_settings);
        $this->global_settings = [];
        $this->settings        = $this->local_settings;
    }

    /**
     * Dump settings.
     */
    public function dumpSettings(): array
    {
        return $this->settings;
    }

    /**
     * Dump local settings.
     */
    public function dumpLocalSettings(): array
    {
        return $this->local_settings;
    }

    /**
     * Dump global settings.
     */
    public function dumpGlobalSettings(): array
    {
        return $this->global_settings;
    }
}
