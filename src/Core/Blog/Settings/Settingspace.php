<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Settings;

// Dotclear\Core\Blog\Settings\Settingspace
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;

/**
 * Blog settings namespace handling methods.
 *
 * @ingroup  Core Setting
 */
class Settingspace
{
    /**
     * @var string $table
     *             Settings table name
     */
    protected $table;

    /**
     * @var array<string,array> $global_settings
     *                          Global settings array
     */
    protected $global_settings = [];

    /**
     * @var array<string,array> $local_settings
     *                          Local settings array
     */
    protected $local_settings = [];

    /**
     * @var array<string,array> $settings
     *                          Associative settings array
     */
    protected $settings = [];

    /**
     * @var string $ns
     *             Current namespace
     */
    protected $ns;

    protected const NS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';
    protected const NS_ID_SCHEMA   = '/^[a-zA-Z][a-zA-Z0-9_]+$/';

    /**
     * Constructor.
     *
     * Retrieves blog settings and puts them in an array.
     *
     * Local (blog) settings have a highest priority than global settings.
     *
     * @param null|string $blog_id The blog identifier
     * @param string      $name    The namespace ID
     * @param null|Record $rs      The record, if any
     *
     * @throws CoreException
     */
    public function __construct(protected string|null $blog_id, string $name, ?Record $rs = null)
    {
        if (preg_match(self::NS_NAME_SCHEMA, $name)) {
            $this->ns = $name;
        } else {
            throw new CoreException(sprintf(__('Invalid setting Namespace: %s'), $name));
        }

        $this->table = dotclear()->prefix . 'setting';

        $this->getSettings($rs);
    }

    /**
     * Load settings from database.
     *
     * @param Record $rs
     */
    private function getSettings(Record $rs = null): bool
    {
        if (null == $rs) {
            try {
                $sql = new SelectStatement(__METHOD__);
                $rs  = $sql
                    ->columns([
                        'blog_id',
                        'setting_id',
                        'setting_value',
                        'setting_type',
                        'setting_label',
                        'setting_ns',
                    ])
                    ->from($this->table)
                    ->where($sql->orGroup([
                        'blog_id = ' . $sql->quote($this->blog_id),
                        'blog_id IS NULL',
                    ]))
                    ->and('setting_ns = ' . $sql->quote($this->ns))
                    ->order('setting_id DESC')
                    ->select()
                ;
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

            $array = $rs->f('blog_id') ? 'local' : 'global';

            $this->{$array . '_settings'}[$id] = [
                'ns'     => $this->ns,
                'value'  => $value,
                'type'   => $type,
                'label'  => (string) $rs->f('setting_label'),
                'global' => $rs->f('blog_id') == '',
            ];
        }

        $this->settings = $this->global_settings;

        foreach ($this->local_settings as $id => $v) {
            $this->settings[$id] = $v;
        }

        return true;
    }

    /**
     * Check if a setting exists.
     *
     * @param string $id     The identifier
     * @param bool   $global The global
     *
     * @return bool True if settings exists
     */
    public function settingExists(string $id, bool $global = false): bool
    {
        $array = $global ? 'global' : 'local';

        return isset($this->{$array . '_settings'}[$id]);
    }

    /**
     * Get setting value if exists.
     *
     * @param string $n Setting name
     *
     * @return mixed The setting value (or null if not)
     */
    public function get(string $n): mixed
    {
        return isset($this->settings[$n]) && isset($this->settings[$n]['value']) ?
                $this->settings[$n]['value'] : null;
    }

    /**
     * Get global setting value if exists.
     *
     * @param string $n Setting name
     *
     * @return mixed The global setting value (or null if not)
     */
    public function getGlobal(string $n): mixed
    {
        return isset($this->global_settings[$n]) && isset($this->global_settings[$n]['value']) ?
            $this->global_settings[$n]['value'] : null;
    }

    /**
     * Get local setting value if exists.
     *
     * @param string $n Setting name
     *
     * @return mixed The local setting value (or null if not)
     */
    public function getLocal(string $n): mixed
    {
        return isset($this->local_settings[$n]) && isset($this->local_settings[$n]['value']) ?
            $this->local_settings[$n]['value'] : null;
    }

    /**
     * Set a setting in $settings property.
     *
     * This sets the setting for script
     * execution time only and if setting exists.
     *
     * @param string $n The setting name
     * @param mixed  $v The setting value
     */
    public function set(string $n, mixed $v): void
    {
        if (isset($this->settings[$n])) {
            $this->settings[$n]['value'] = $v;
        }
    }

    /**
     * Create or update a setting.
     *
     * $type could be 'string', 'integer', 'float', 'boolean', 'array' or null.
     * If $type is null and setting exists, it will keep current setting type.
     *
     * $value_change allow you to not change setting.
     * Useful if you need to change a setting label or type
     * and don't want to change its value.
     *
     * @param string $id           The setting identifier
     * @param mixed  $value        The setting value
     * @param string $type         The setting type
     * @param string $label        The setting label
     * @param bool   $value_change Change setting value or not
     * @param bool   $global       Setting is global
     *
     * @throws CoreException
     */
    public function put(string $id, mixed $value, ?string $type = null, ?string $label = null, bool $value_change = true, bool $global = false): void
    {
        if (!preg_match(self::NS_ID_SCHEMA, $id)) {
            throw new CoreException(sprintf(__('%s is not a valid setting id'), $id));
        }

        // We don't want to change setting value
        if (!$value_change) {
            if (!$global && $this->settingExists($id, false)) {
                $value = $this->local_settings[$id]['value'];
            } elseif ($this->settingExists($id, true)) {
                $value = $this->global_settings[$id]['value'];
            }
        }

        // Setting type
        if ('double' == $type) {
            $type = 'float';
        } elseif (null === $type) {
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
        } elseif ('boolean' != $type && 'integer' != $type && 'float' != $type && 'array' != $type) {
            $type = 'string';
        }

        // We don't change label
        if (null == $label) {
            if (!$global && $this->settingExists($id, false)) {
                $label = $this->local_settings[$id]['label'];
            } elseif ($this->settingExists($id, true)) {
                $label = $this->global_settings[$id]['label'];
            }
        }

        if ('array' != $type) {
            settype($value, $type);
        } else {
            $value = json_encode($value);
        }

        // If we are local, compare to global value
        if (!$global && $this->settingExists($id, true)) {
            $g            = $this->global_settings[$id];
            $same_setting = ($g['ns'] == $this->ns && $g['value'] == $value && $g['type'] == $type && $g['label'] == $label);

            // Drop setting if same value as global
            if ($same_setting && $this->settingExists($id, false)) {
                $this->drop($id);
            } elseif ($same_setting) {
                return;
            }
        }

        // Update
        if ($this->settingExists($id, $global) && $this->ns == $this->settings[$id]['ns']) {
            $sql = new UpdateStatement(__METHOD__);
            $sql
                ->set([
                    'setting_value = ' . $sql->quote('boolean' == $type ? (string) (int) $value : (string) $value),
                    'setting_type = ' . $sql->quote($type),
                    'setting_label = ' . $sql->quote($label),
                ])
                ->where(
                    $global ?
                    'blog_id IS NULL' :
                    'blog_id = ' . $sql->quote($this->blog_id)
                )
                ->and('setting_id = ' . $sql->quote($id))
                ->and('setting_ns = ' . $sql->quote($this->ns))
                ->from($this->table)
                ->update()
            ;
        // Insert
        } else {
            $sql = new InsertStatement(__METHOD__);
            $sql
                ->columns([
                    'setting_value',
                    'setting_type',
                    'setting_label',
                    'setting_id',
                    'blog_id',
                    'setting_ns',
                ])
                ->line([[
                    $sql->quote('boolean' == $type ? (string) (int) $value : (string) $value),
                    $sql->quote($type),
                    $sql->quote($label),
                    $sql->quote($id),
                    $global ? 'NULL' : $sql->quote($this->blog_id),
                    $sql->quote($this->ns),
                ]])
                ->from($this->table)
                ->insert()
            ;
        }
    }

    /**
     * Rename an existing setting in a Namespace.
     *
     * @param string $oldId The old setting identifier
     * @param string $newId The new setting identifier
     *
     * @throws CoreException
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
        $sql = new UpdateStatement(__METHOD__);
        $sql->from($this->table)
            ->set('setting_id = ' . $sql->quote($newId))
            ->where('setting_ns = ' . $sql->quote($this->ns))
            ->and('setting_id = ' . $sql->quote($oldId))
            ->update()
        ;

        return true;
    }

    /**
     * Remove an existing setting in a namespace.
     *
     * @param string $id The setting identifier
     *
     * @throws CoreException
     */
    public function drop(string $id): void
    {
        if (!$this->ns) {
            throw new CoreException(__('No namespace specified'));
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->from($this->table)
            ->where(
                null === $this->blog_id ?
                'blog_id IS NULL' :
                'blog_id = ' . $sql->quote($this->blog_id)
            )
            ->and('setting_id = ' . $sql->quote($id))
            ->and('setting_ns = ' . $sql->quote($this->ns))
            ->delete()
        ;
    }

    /**
     * Remove every existing specific setting in a namespace.
     *
     * @param string $id     Setting ID
     * @param bool   $global Remove global setting too
     *
     * @throws CoreException
     */
    public function dropEvery(string $id, bool $global = false): void
    {
        if (!$this->ns) {
            throw new CoreException(__('No namespace specified'));
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql->from($this->table)
            ->where('setting_id = ' . $sql->quote($id))
            ->and('setting_ns = ' . $sql->quote($this->ns))
        ;

        if (!$global) {
            $sql->and('blog_id IS NOT NULL');
        }

        $sql->delete();
    }

    /**
     * Remove all existing settings in a namespace.
     *
     * @param bool $force_global Force global pref drop
     *
     * @throws CoreException
     */
    public function dropAll(bool $force_global = false): void
    {
        if (!$this->ns) {
            throw new CoreException(__('No namespace specified'));
        }

        $global = $force_global || null === $this->blog_id;

        $sql = new DeleteStatement(__METHOD__);
        $sql->from($this->table)
            ->where(
                $global ?
                'blog_id IS NULL' :
                'blog_id = ' . $sql->quote($this->blog_id)
            )
            ->and('setting_ns = ' . $sql->quote($this->ns))
            ->delete()
        ;

        $array = $global ? 'global' : 'local';
        unset($this->{$array . '_settings'});
        $this->{$array . '_settings'} = [];

        $array          = $global ? 'local' : 'global';
        $this->settings = $this->{$array . '_settings'};
    }

    /**
     * Dump a namespace.
     */
    public function dumpNamespace(): string
    {
        return $this->ns;
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
