<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Version;

// Dotclear\Core\Version\Version
use Dotclear\App;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\DeleteStatement;

/**
 * Version handling class.
 *
 * Save and retrieve and compare
 * core and modules version in database.
 *
 * @ingroup  Core Version
 */
class Version
{
    /**
     * @var string $table
     *             The version table name
     */
    protected $table = 'version';

    /**
     * @var array<string,string> $stack
     *                           The versions stack
     */
    protected $stack;

    /**
     * Get the version of a module.
     *
     * @param string $module The module
     *
     * @return string The version
     */
    public function get(string $module = 'core'): string
    {
        // Fetch versions if needed
        if (!is_array($this->stack)) {
            $rs = SelectStatement::init(__METHOD__)
                ->columns(['module', 'version'])
                ->from(App::core()->prefix() . $this->table)
                ->select()
            ;

            while ($rs->fetch()) {
                $this->stack[$rs->f('module')] = $rs->f('version');
            }
        }

        return isset($this->stack[$module]) ? (string) $this->stack[$module] : '';
    }

    /**
     * Set the version of a module.
     *
     * @param string $module  The module
     * @param string $version The version
     */
    public function set(string $module, string $version): void
    {
        if (null !== $this->get($module)) {
            $this->delete($module);
        }

        $sql = new InsertStatement(__METHOD__);
        $sql->from(App::core()->prefix() . $this->table)
            ->columns([
                'module',
                'version',
            ])
            ->line([[
                $sql->quote($module),
                $sql->quote($version),
            ]])
            ->insert()
        ;

        $this->stack[$module] = $version;
    }

    /**
     * Remove a module version entry.
     *
     * @param string $module The module
     */
    public function delete(string $module): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql->from(App::core()->prefix() . $this->table)
            ->where('module = ' . $sql->quote($module))
            ->delete()
        ;

        if (is_array($this->stack)) {
            unset($this->stack[$module]);
        }
    }

    /**
     * Check if a module has a version registered.
     *
     * @param string $module The module
     *
     * @return bool True if it exists and not empty
     */
    public function exists(string $module): bool
    {
        return '' != $this->get($module);
    }

    /**
     * Compare two versions with option of using only main numbers.
     *
     * @param string $current_version  Current version
     * @param string $required_version Required version
     * @param string $operator         Comparison operand
     * @param bool   $strict           Use full version
     *
     * @return bool True if comparison success
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
