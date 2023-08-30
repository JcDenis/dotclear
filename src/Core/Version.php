<?php
/**
 * Version handler.
 *
 * Handle id,version pairs through database.
 *
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Interface\Core\ConnectionInterface;
use Dotclear\Interface\Core\VersionInterface;

use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;

class Version implements VersionInterface
{
    /** @var     string  Versions database table name */
    public const VERSION_TABLE_NAME = 'version';

    /** @var    array<string,string>    The version stack */
    private array $stack = [];

    public function __construct(
        private ConnectionInterface $con
    ) {
        $this->loadVersions();
    }

    public function getVersion(string $module = 'core'): string
    {
        return $this->stack[$module] ?? '';
    }

    public function getVersions(): array
    {
        return $this->stack;
    }

    public function setVersion(string $module, string $version): void
    {
        $cur = $this->con->openCursor($this->con->prefix() . self::VERSION_TABLE_NAME);
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
            ->from($this->con->prefix() . self::VERSION_TABLE_NAME)
            ->where('module = ' . $sql->quote($module));

        $sql->delete();

        unset($this->stack[$module]);
    }

    public function compareVersion(string $module, string $version): int
    {
        return version_compare($version, $this->getVersion($module));
    }

    public function newerVersion(string $module, string $version): bool
    {
        return $this->compareVersion($module, $version) === 1;
    }

    /**
     * Load versions from database.
     */
    private function loadVersions(): void
    {
        if (empty($this->stack)) {
            $rs = (new SelectStatement())
                ->columns([
                    'module',
                    'version',
                ])
                ->from($this->con->prefix() . self::VERSION_TABLE_NAME)
                ->select();

            while ($rs->fetch()) {
                $this->stack[(string) $rs->f('module')] = (string) $rs->f('version');
            }
        }
    }
}
