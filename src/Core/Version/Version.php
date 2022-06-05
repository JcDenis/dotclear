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
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;

/**
 * Version handling class.
 *
 * Save and retrieve and compare
 * core and modules version in database.
 *
 * @ingroup  Core Version
 */
final class Version
{
    /**
     * @var array<string,string> $modules
     *                           The modules versions
     */
    private $modules = [];

    /**
     * Get the version of a module.
     *
     * @param string $module The module
     *
     * @return string The version
     */
    public function getVersion(string $module = 'core'): string
    {
        // Fetch versions if needed
        if (empty($this->modules)) {
            $sql = new SelectStatement(__METHOD__);
            $sql->columns(['module', 'version']);
            $sql->from(App::core()->prefix() . 'version');

            $record = $sql->select();
            while ($record->fetch()) {
                $this->modules[$record->f('module')] = $record->f('version');
            }
        }

        return isset($this->modules[$module]) ? (string) $this->modules[$module] : '';
    }

    /**
     * Set the version of a module.
     *
     * @param string $module  The module
     * @param string $version The version
     */
    public function setVersion(string $module, string $version): void
    {
        if ('' != $this->getVersion(module: $module)) {
            $this->deleteVersion(module: $module);
        }

        $sql = new InsertStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'version');
        $sql->columns([
            'module',
            'version',
        ]);
        $sql->line([[
            $sql->quote($module),
            $sql->quote($version),
        ]]);
        $sql->insert();

        $this->modules[$module] = $version;
    }

    /**
     * Remove a module version entry.
     *
     * @param string $module The module
     */
    public function deleteVersion(string $module): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'version');
        $sql->where('module = ' . $sql->quote($module));
        $sql->delete();

        unset($this->modules[$module]);
    }

    /**
     * Check if a module has a version registered.
     *
     * @param string $module The module
     *
     * @return bool True if it exists and not empty
     */
    public function hasVersion(string $module): bool
    {
        return '' != $this->getVersion(module: $module);
    }

    /**
     * Compare two versions.
     *
     * @param string $current  Current version
     * @param string $required Required version
     * @param string $operator Comparison operand
     *
     * @return bool True if comparison success
     */
    public function compareVersions(string $current, string $required, string $operator = '>='): bool
    {
        return (bool) version_compare(
            preg_replace('!-r(\d+)$!', '-p$1', $current),
            preg_replace('!-r(\d+)$!', '-p$1', $required),
            $operator
        );
    }

    /**
     * Compare two versions using only main numbers.
     *
     * @param string $current  Current version
     * @param string $required Required version
     * @param string $operator Comparison operand
     *
     * @return bool True if comparison success
     */
    public function compareMajorVersions(string $current, string $required, string $operator = '>='): bool
    {
        return (bool) version_compare(
            preg_replace('/^([0-9\.]+)(.*?)$/', '$1', $current),
            preg_replace('/^([0-9\.]+)(.*?)$/', '$1', $required),
            $operator
        );
    }
}
