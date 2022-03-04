<?php
/**
 * @class Dotclear\Core\Version\Version
 * @brief Dotclear core version class
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Version;

use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\DeleteStatement;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Version
{
    /** @var    string  The version table name */
    protected $table = 'version';

    /** @var    array  The versions stack */
    protected $versions = null;

    /**
     * Gets the version of a module.
     *
     * @param   string  $module     The module
     *
     * @return  string|null  The version.
     */
    public function get(string $module = 'core'): ?string
    {
        # Fetch versions if needed
        if (!is_array($this->versions)) {
            $sql = new SelectStatement('CoreGetVersion');
            $sql
                ->columns(['module', 'version'])
                ->from(dotclear()->prefix . $this->table);

            $rs = $sql->select();

            while ($rs->fetch()) {
                $this->versions[$rs->module] = $rs->version;
            }
        }

        return isset($this->versions[$module]) ? (string) $this->versions[$module] : null;
    }

    /**
     * Sets the version of a module.
     *
     * @param   string  $module     The module
     * @param   string  $version    The version
     */
    public function set(string $module, string $version): void
    {
        $cur          = dotclear()->con()->openCursor(dotclear()->prefix . $this->table);
        $cur->module  = $module;
        $cur->version = $version;

        if ($this->get($module) === null) {
            $cur->insert();
        } else {
            $cur->update("WHERE module='" . dotclear()->con()->escape($module) . "'");
        }

        $this->versions[$module] = $version;
    }

    /**
     * Remove a module version entry
     *
     * @param   string  $module     The module
     */
    public function delete(string $module): void
    {
        $sql = new DeleteStatement('CoreDelVersion');
        $sql->from(dotclear()->prefix . $this->table)
            ->where("module = '" . dotclear()->con()->escape($module) . "'")
            ->delete();

        if (is_array($this->versions)) {
            unset($this->versions[$module]);
        }
    }

    /**
     * Compare two versions with option of using only main numbers.
     *
     * @param  string    $current_version    Current version
     * @param  string    $required_version    Required version
     * @param  string    $operator            Comparison operand
     * @param  boolean    $strict                Use full version
     *
     * @return boolean    True if comparison success
     */
    public static function compare(string $current_version, string $required_version, string $operator = '>=', bool $strict = true): bool
    {
        if ($strict) {
            $current_version  = preg_replace('!-r(\d+)$!', '-p$1', $current_version);
            $required_version = preg_replace('!-r(\d+)$!', '-p$1', $required_version);
        } else {
            $current_version  = preg_replace('/^([0-9\.]+)(.*?)$/', '$1', $current_version);
            $required_version = preg_replace('/^([0-9\.]+)(.*?)$/', '$1', $required_version);
        }

        return (bool) version_compare($current_version, $required_version, $operator);
    }
}
