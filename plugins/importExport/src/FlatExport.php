<?php

/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\importExport;

use Dotclear\Database\MetaRecord;
use Dotclear\Helper\TraitDynamicProperties;
use Dotclear\Interface\Database\ConnectionInterface;
use Exception;

/**
 * @brief   The module flat export handler.
 * @ingroup importExport
 */
class FlatExport
{
    use TraitDynamicProperties;

    private ConnectionInterface $con;
    private string $prefix;

    /**
     * @var string[]    $line_reg
     */
    private array $line_reg = ['/\\\\/u', '/\n/u', '/\r/u', '/"/u'];

    /**
     * @var string[]    $line_rep
     */
    private array $line_rep = ['\\\\\\\\', '\n', '\r', '\"'];

    public mixed $fp;

    public function __construct(ConnectionInterface $con, string $out = 'php://output', ?string $prefix = null)
    {
        $this->con    = &$con;
        $this->prefix = $prefix ?? '';

        if (($this->fp = fopen($out, 'w')) === false) {
            throw new Exception(__('Unable to create output file.'));
        }
        @set_time_limit(300);
    }

    public function __destruct()
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
    }

    public function export(string $name, string $sql): void
    {
        $rs = new MetaRecord($this->con->select($sql));

        if (!$rs->isEmpty()) {
            fwrite($this->fp, "\n[" . $name . ' ' . implode(',', $rs->columns()) . "]\n");
            while ($rs->fetch()) {
                fwrite($this->fp, $this->getLine($rs));
            }
            fflush($this->fp);
        }
    }

    public function exportAll(): void
    {
        $tables = $this->getTables();

        foreach ($tables as $table) {
            $this->exportTable($table);
        }
    }

    public function exportTable(string $table): void
    {
        $req = 'SELECT * FROM ' . $this->con->escapeSystem($this->prefix . $table);

        $this->export($table, $req);
    }

    /**
     * Gets the tables.
     *
     * @return     array<string>  The tables.
     */
    public function getTables(): array
    {
        $db_tables = $this->con->schema()->getTables();

        $tables = [];
        foreach ($db_tables as $t) {
            if ($this->prefix !== '') {
                if (str_starts_with($t, $this->prefix)) {
                    $tables[] = $t;
                }
            } else {
                $tables[] = $t;
            }
        }

        return $tables;
    }

    public function getLine(MetaRecord $rs): string
    {
        $l    = [];
        $cols = $rs->columns();
        foreach ($cols as $i => &$c) {
            $s     = $rs->f($c);
            $s     = preg_replace($this->line_reg, $this->line_rep, (string) $s);
            $s     = '"' . $s . '"';
            $l[$i] = $s;
        }

        return implode(',', $l) . "\n";
    }
}
