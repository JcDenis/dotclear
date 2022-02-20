<?php
/**
 * @class Dotclear\Plugin\ImportExport\Lib\Module\Flat\FlatExport
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Lib\Module\Flat;

use Dotclear\Database\Record;
use Dotclear\Database\Schema;
use Dotclear\Exception\ModuleException;
use Dotclear\Plugin\ImportExport\Lib\Module\Flat\FlatBackupItem;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class FlatExport
{
    private $line_reg = ['/\\\\/u', '/\n/u', '/\r/u', '/"/u'];
    private $line_rep = ['\\\\\\\\', '\n', '\r', '\"'];

    public $fp;

    public function __construct($out = 'php://output')
    {
        if (($this->fp = fopen($out, 'w')) === false) {
            throw new ModuleException(__('Unable to create output file.'));
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
        $rs = dotclear()->con()->select($sql);

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
        $req = 'SELECT * FROM ' . dotclear()->con()->escapeSystem(dotclear()->prefix . $table);

        $this->export($table, $req);
    }

    public function getTables(): array
    {
        $schema    = Schema::init(dotclear()->con());
        $db_tables = $schema->getTables();

        $tables = [];
        foreach ($db_tables as $t) {
            if (dotclear()->prefix) {
                if (strpos($t, dotclear()->prefix) === 0) {
                    $tables[] = $t;
                }
            } else {
                $tables[] = $t;
            }
        }

        return $tables;
    }

    public function getLine(Record $rs): string
    {
        $l    = [];
        $cols = $rs->columns();
        foreach ($cols as $i => &$c) {var_dump($i);var_dump($rs->f($c));echo "\n<br />";
            $s     = $rs->f($c);
            $s     = preg_replace($this->line_reg, $this->line_rep, (string) $s);
            $s     = '"' . $s . '"';
            $l[$i] = $s;
        }

        return implode(',', $l) . "\n";
    }
}
