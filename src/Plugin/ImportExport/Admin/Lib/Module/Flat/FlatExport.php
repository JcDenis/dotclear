<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat;

// Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat\FlatExport
use Dotclear\App;
use Dotclear\Database\Record;
use Dotclear\Database\AbstractSchema;
use Dotclear\Exception\ModuleException;

/**
 * Flat export for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class FlatExport
{
    private $line_reg = ['/\\\\/u', '/\n/u', '/\r/u', '/"/u'];
    private $line_rep = ['\\\\\\\\', '\n', '\r', '\"'];

    public $fp;

    public function __construct(string $out = 'php://output')
    {
        if (false === ($this->fp = fopen($out, 'w'))) {
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
        $rs = App::core()->con()->select($sql);

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
        $req = 'SELECT * FROM ' . App::core()->con()->escapeSystem(App::core()->prefix() . $table);

        $this->export($table, $req);
    }

    public function getTables(): array
    {
        $schema    = AbstractSchema::init(App::core()->con());
        $db_tables = $schema->getTables();

        $tables = [];
        foreach ($db_tables as $t) {
            if (App::core()->prefix()) {
                if (str_starts_with($t, App::core()->prefix())) {
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
        foreach ($cols as $i => &$c) {
            $s     = $rs->f($c);
            $s     = preg_replace($this->line_reg, $this->line_rep, (string) $s);
            $s     = '"' . $s . '"';
            $l[$i] = $s;
        }

        return implode(',', $l) . "\n";
    }
}
