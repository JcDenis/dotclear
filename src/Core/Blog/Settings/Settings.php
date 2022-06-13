<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Settings;

// Dotclear\Core\Blog\Settings\Settings
use Dotclear\App;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\InvalidValueFormat;
use Exception;

/**
 * Blog settings handling methods.
 *
 * @ingroup  Core Setting
 */
final class Settings
{
    private const NS_GROUP_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';

    /** @var array<string,SettingsGroup> $groups
     *             Associative namespaces array
     */
    private $groups = [];

    /**
     * Constructor.
     *
     * Retrieves blog settings and puts them in $groups array.
     * Local (blog) settings have a highest priority than global settings.
     *
     * @param null|string $blog The blog ID
     */
    public function __construct(private string|null $blog)
    {
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
            $sql->from(App::core()->prefix() . 'setting');
            $sql->where('blog_id = ' . $sql->quote($this->blog));
            $sql->or('blog_id IS NULL');
            $sql->order(['setting_ns ASC', 'setting_id DESC']);

            $record = $sql->select();
        } catch (Exception) {
            trigger_error(__('Unable to retrieve namespaces:') . ' ' . App::core()->con()->error(), E_USER_ERROR);
        }

        // Prevent empty tables (install phase, for instance)
        if ($record->isEmpty()) {
            return;
        }

        do {
            $id = trim($record->f('setting_ns'));
            if (!$record->isStart()) {
                // we have to go up 1 step, since groups construction performs
                // a fetch() at very first time
                $record->movePrev();
            }
            $this->groups[$id] = new SettingsGroup(blog: $this->blog, group: $id, record: $record);
        } while (!$record->isStart());
    }

    /**
     * Check if a group exists.
     *
     * @param string $id The settings group ID
     *
     * @return bool True if settigns group exists
     */
    public function hasGroup(string $id): bool
    {
        return array_key_exists($id, $this->groups);
    }

    /**
     * Get a full group with all settings pertaining to it.
     *
     * If group does not exists it will be created on the fly.
     *
     * @param string $id The settings group ID
     *
     * @throws InvalidValueFormat
     *
     * @return SettingsGroup The settings group
     */
    public function getGroup(string $id): SettingsGroup
    {
        if (!$this->hasGroup(id: $id)) {
            if (!preg_match(self::NS_GROUP_SCHEMA, $id)) {
                throw new InvalidValueFormat(sprintf(__('Invalid setting namespace: %s'), $id));
            }
            $this->groups[$id] = new SettingsGroup(blog: $this->blog, group: $id);
        }

        return $this->groups[$id];
    }

    /**
     * Rename a group.
     *
     * @param string $from The old settings group ID
     * @param string $to   The new settings group ID
     *
     * @throws InvalidValueFormat
     *
     * @return bool True on success
     */
    public function renameGroup(string $from, string $to): bool
    {
        if (!$this->hasGroup(id: $from) || $this->hasGroup(id: $to)) {
            return false;
        }

        if (!preg_match(self::NS_GROUP_SCHEMA, $to)) {
            throw new InvalidValueFormat(sprintf(__('Invalid setting namespace: %s'), $to));
        }

        // Rename the group in the group array
        $this->groups[$to] = $this->groups[$from];
        unset($this->groups[$from]);

        // Rename the group in the database
        $sql = new UpdateStatement();
        $sql->from(App::core()->prefix() . 'setting');
        $sql->set('setting_ns = ' . $sql->quote($to));
        $sql->where('setting_ns = ' . $sql->quote($from));
        $sql->update();

        return true;
    }

    /**
     * Delete a whole group with all settings pertaining to it.
     *
     * @param string $id The settings group ID
     *
     * @return bool True on success
     */
    public function deleteGroup(string $id): bool
    {
        if (!$this->hasGroup(id: $id)) {
            return false;
        }

        // Remove the group from the group array
        unset($this->groups[$id]);

        // Delete all settings from the group in the database
        $sql = new DeleteStatement();
        $sql->from(App::core()->prefix() . 'setting');
        $sql->where('setting_ns = ' . $sql->quote($id));
        $sql->delete();

        return true;
    }

    /**
     * Dump groups.
     */
    public function dumpGroups(): array
    {
        return $this->groups;
    }
}
