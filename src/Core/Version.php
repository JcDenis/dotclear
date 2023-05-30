<?php
/**
 * @brief Version core class.
 *
 * Provides an object to manage versions stack
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use dcCore;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;

class Version
{
    /** @var    string  Versions table name */
    public const VERSION_TABLE_NAME = 'version';

    /** @var    array<string,string>   Stack of registered versions (core, modules) */
    private $stack = [];

    /**
     * Gets the version of a module.
     *
     * @param   null|string     $module     The module
     *
     * @return  null|string     The version.
     */
    public function get(?string $module = 'core'): ?string
    {
        # Fetch versions if needed
        $this->dump();

        return is_null($module) || !isset($this->stack[$module]) ? null : $this->stack[$module];
    }

    /**
     * Gets all known versions
     *
     * @return  array<string,string>    The versions.
     */
    public function dump(): array
    {
        // Fetch versions if needed
        if (empty($this->stack)) {
            $rs = (new SelectStatement())
                ->columns([
                    'module',
                    'version',
                ])
                ->from(dcCore::app()->prefix . self::VERSION_TABLE_NAME)
                ->select();

            while ($rs->fetch()) {
                if (is_string($rs->f('module')) && is_string($rs->f('version'))) {
                    $this->stack[$rs->f('module')] = $rs->f('version');
                }
            }
        }

        return $this->stack;
    }

    /**
     * Sets the version of a module.
     *
     * @param   string  $module     The module
     * @param   string  $version    The version
     */
    public function set(string $module, string $version): void
    {
        $cur_version = $this->get($module);

        $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . self::VERSION_TABLE_NAME);
        $cur->setField('module', $module);
        $cur->setField('version', $version);

        if ($cur_version === null) {
            $cur->insert();
        } else {
            $sql = new UpdateStatement();
            $sql->where('module = ' . $sql->quote($module));

            $sql->update($cur);
        }

        $this->stack[$module] = $version;
    }

    /**
     * Compare the given version of a module with the registered one
     *
     * Returned values:
     *
     * -1 : newer version already installed
     * 0 : same version installed
     * 1 : older version is installed
     *
     * @param   string  $module     The module
     * @param   string  $version    The version
     *
     * @return  int     return the result of the test
     */
    public function compare(string $module, string $version): int
    {
        return version_compare($version, (string) $this->get($module));
    }

    /**
     * Test if a version is a new one.
     *
     * @param   string  $module     The module
     * @param   string  $version    The version
     *
     * @return  bool
     */
    public function newer(string $module, string $version): bool
    {
        return $this->compare($module, $version) === 1;
    }

    /**
     * Remove a module version entry.
     *
     * @param   string  $module     The module
     */
    public function delete(string $module): void
    {
        $sql = new DeleteStatement();
        $sql
            ->from(dcCore::app()->prefix . self::VERSION_TABLE_NAME)
            ->where('module = ' . $sql->quote($module));

        $sql->delete();

        unset($this->stack[$module]);
    }
}
