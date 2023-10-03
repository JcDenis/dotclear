<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Database\Cursor;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Interface\Core\ConnectionInterface;
use Dotclear\Interface\Core\VersionInterface;

/**
 * @brief   Modules (and core) version handler.
 *
 * Handle id,version pairs through database.
 *
 * @since   2.28, modules version features have been grouped in this class
 */
class Version implements VersionInterface
{
    /**
     * The version stack.
     *
     * @var     array<string,string>    $stack;
     */
    private array $stack;

    /**
     * Full table name (including db prefix).
     *
     * @var     string  $table
     */
    protected string $table;

    /**
     * Constructor.
     *
     * @param   ConnectionInterface     $con    The database connection instance
     */
    public function __construct(
        protected ConnectionInterface $con
    ) {
        $this->table = $this->con->prefix() . self::VERSION_TABLE_NAME;
    }

    public function openVersionCursor(): Cursor
    {
        return $this->con->openCursor($this->con->prefix() . self::VERSION_TABLE_NAME);
    }

    public function getVersion(string $module = 'core'): string
    {
        $this->loadVersions();

        return $this->stack[$module] ?? '';
    }

    public function getVersions(): array
    {
        $this->loadVersions();

        return $this->stack;
    }

    public function setVersion(string $module, string $version): void
    {
        $cur = $this->openVersionCursor();
        $cur->setField('module', $module);
        $cur->setField('version', $version);

        if ($this->getVersion($module) === '') {
            $cur->insert();
        } else {
            $sql = new UpdateStatement();
            $sql->where('module = ' . $sql->quote($module));
            $sql->update($cur);
        }

        $this->stack[$module] = $version;
    }

    public function unsetVersion(string $module): void
    {
        $sql = new DeleteStatement();
        $sql
            ->from($this->table)
            ->where('module = ' . $sql->quote($module));

        $sql->delete();

        unset($this->stack[$module]);
    }

    public function compareVersion(string $module, string $version): int
    {
        $this->loadVersions();

        return version_compare($version, $this->getVersion($module));
    }

    public function newerVersion(string $module, string $version): bool
    {
        $this->loadVersions();

        return $this->compareVersion($module, $version) === 1;
    }

    /**
     * Load versions from database.
     */
    private function loadVersions(): void
    {
        if (!isset($this->stack)) {
            $rs = (new SelectStatement())
                ->columns([
                    'module',
                    'version',
                ])
                ->from($this->table)
                ->select();

            while ($rs->fetch()) {
                $this->stack[(string) $rs->f('module')] = (string) $rs->f('version');
            }
        }
    }
}
