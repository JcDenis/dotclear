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

class Version
{
    /** @var    string  The version table name */
    protected $table = 'version';

    /** @var    array<string, string>   The versions stack */
    protected $stack = null;

    /**
     * Get the version of a module.
     *
     * @param   string  $module     The module
     *
     * @return  string|null         The version.
     */
    public function get(string $module = 'core'): ?string
    {
        # Fetch versions if needed
        if (!is_array($this->stack)) {
            $rs = SelectStatement::init('CoreGetVersion')
                ->columns(['module', 'version'])
                ->from(dotclear()->prefix . $this->table)
                ->select();

            while ($rs->fetch()) {
                $this->stack[$rs->f('module')] = $rs->f('version');
            }
        }

        return isset($this->stack[$module]) ? (string) $this->stack[$module] : null;
    }

    /**
     * Set the version of a module.
     *
     * @param   string  $module     The module
     * @param   string  $version    The version
     */
    public function set(string $module, string $version): void
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . $this->table);
        $cur->setField('module', $module);
        $cur->setField('version', $version);

        if (null === $this->get($module)) {
            $cur->insert();
        } else {
            $cur->update("WHERE module='" . dotclear()->con()->escape($module) . "'");
        }

        $this->stack[$module] = $version;
    }

    /**
     * Remove a module version entry
     *
     * @param   string  $module     The module
     */
    public function delete(string $module): void
    {
        DeleteStatement::init('CoreDelVersion')
            ->from(dotclear()->prefix . $this->table)
            ->where("module = '" . dotclear()->con()->escape($module) . "'")
            ->delete();

        if (is_array($this->stack)) {
            unset($this->stack[$module]);
        }
    }

    /**
     * Compare two versions with option of using only main numbers.
     *
     * @param   string  $current_version    Current version
     * @param   string  $required_version   Required version
     * @param   string  $operator           Comparison operand
     * @param   bool    $strict             Use full version
     *
     * @return  bool                        True if comparison success
     */
    public function compare(string $current_version, string $required_version, string $operator = '>=', bool $strict = true): bool
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
