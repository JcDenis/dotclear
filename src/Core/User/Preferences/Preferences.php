<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\User\Preferences;

// Dotclear\Core\User\Preferences\Preferences
use Dotclear\App;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\InvalidValueFormat;
use Exception;

/**
 * User preference handling methods.
 *
 * @ingroup  Core User Preference
 */
class Preferences
{
    /**
     * @var array<string,PreferencesGroup> $groups
     *                                     Associative groups array
     */
    protected $groups = [];

    protected const WS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';

    /**
     * Constructor.
     *
     * Retrieves user prefs and puts them in $groups
     * array. Local (user) prefs have a highest priority than global prefs.
     *
     * @param string $user The user ID
     */
    public function __construct(protected string $user)
    {
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
            $sql->order([
                'pref_ws ASC',
                'pref_id ASC',
            ]);

            try {
                $record = $sql->select();
            } catch (Exception $e) {
                throw $e;
            }

            // Prevent empty tables (install phase, for instance)
            if ($record->isEmpty()) {
                return;
            }

            do {
                $group = trim($record->field('pref_ws'));
                if (!$record->isStart()) {
                    // we have to go up 1 step, since groups construction performs a fetch()
                    // at very first time
                    $record->movePrev();
                }
                $this->groups[$group] = new PreferencesGroup(user: $this->user, group: $group, record: $record);
            } while (!$record->isStart());
        } catch (\Exception) {
            trigger_error(__('Unable to retrieve preferences group:') . ' ' . App::core()->con()->error(), E_USER_ERROR);
        }
    }

    /**
     * Check if a group exists.
     *
     * @param string $group The preferences group name
     *
     * @return bool True if preferences group exists
     */
    public function hasGroup(string $group): bool
    {
        return array_key_exists($group, $this->groups);
    }

    /**
     * Get full group with all prefs pertaining to it.
     *
     * If group does not exist, it will be created on the fly.
     *
     * @param string $group The preferences group name
     *
     * @return PreferencesGroup The preference group instance
     */
    public function getGroup(string $group): PreferencesGroup
    {
        $this->addGroup($group);

        return $this->groups[$group];
    }

    /**
     * Create a new group.
     *
     * If the group already exists, return it without modification.
     *
     * @param string $group The preferences group name
     */
    public function addGroup(string $group): void
    {
        if (!$this->hasGroup($group)) {
            $this->groups[$group] = new PreferencesGroup(user: $this->user, group: $group);
        }
    }

    /**
     * Rename a group.
     *
     * @param string $oldWs The old group name
     * @param string $newWs The new group name
     *
     * @throws InvalidValueFormat
     *
     * @return bool True if group successfully renamed
     */
    public function renameGroup(string $oldWs, string $newWs): bool
    {
        if (!$this->hasGroup($oldWs) || $this->hasGroup($newWs)) {
            return false;
        }

        if (!preg_match(self::WS_NAME_SCHEMA, $newWs)) {
            throw new InvalidValueFormat(sprintf(__('Invalid preferences group name: %s'), $newWs));
        }

        // Rename the group in the group array
        $this->groups[$newWs] = $this->groups[$oldWs];
        unset($this->groups[$oldWs]);

        // Rename the group in the database
        $sql = new UpdateStatement();
        $sql->set('pref_ws = ' . $sql->quote($newWs));
        $sql->from(App::core()->getPrefix() . 'pref');
        $sql->where('pref_ws = ' . $sql->quote($oldWs));
        $sql->update();

        return true;
    }

    /**
     * Delete a whole group with all preferences pertaining to it.
     *
     * @param string $group PreferencesGroup name
     *
     * @return bool True if group successfully deleted
     */
    public function deleteGroup(string $group): bool
    {
        if (!$this->hasGroup($group)) {
            return false;
        }

        // Remove the group from the group array
        unset($this->groups[$group]);

        // Delete all preferences from the group in the database
        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'pref');
        $sql->where('pref_ws = ' . $sql->quote($group));
        $sql->delete();

        return true;
    }

    /**
     * Dump groups.
     *
     * @return array<string,PreferencesGroup> The preferences groups
     */
    public function dumpGroup(): array
    {
        return $this->groups;
    }
}
