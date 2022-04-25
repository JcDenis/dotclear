<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat;

// Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat\FlatBackup
use Dotclear\Exception\ModuleException;

/**
 * Flat backup for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class FlatBackup
{
    protected $fp;
    private $line_cols = [];
    private $line_name;
    private $line_num;

    private $replacement = [
        '/(?<!\\\\)(?>(\\\\\\\\)*+)(\\\\n)/u' => "\$1\n",
        '/(?<!\\\\)(?>(\\\\\\\\)*+)(\\\\r)/u' => "\$1\r",
        '/(?<!\\\\)(?>(\\\\\\\\)*+)(\\\\")/u' => '$1"',
        '/(\\\\\\\\)/'                        => '\\',
    ];

    public function __construct(string $file)
    {
        if (file_exists($file) && is_readable($file)) {
            $this->fp       = fopen($file, 'rb');
            $this->line_num = 1;
        } else {
            throw new ModuleException(__('No file to read.'));
        }
    }

    public function __destruct()
    {
        if (is_resource($this->fp)) {
            @fclose($this->fp);
        }
    }

    public function getLine(): FlatBackupItem|false
    {
        if (false === ($line = $this->nextLine())) {
            return false;
        }

        if (substr($line, 0, 1) == '[') {
            $this->line_name = substr($line, 1, strpos($line, ' ') - 1);

            $line            = substr($line, strpos($line, ' ') + 1, -1);
            $this->line_cols = explode(',', $line);

            return $this->getLine();
        }
        if (substr($line, 0, 1) == '"') {
            $line = preg_replace('/^"|"$/', '', $line);
            $line = preg_split('/(^"|","|(?<!\\\)\"$)/m', $line);

            if (count($this->line_cols) != count($line)) {
                throw new ModuleException(sprintf('Invalid row count at line %s', $this->line_num));
            }

            $res = [];

            for ($i = 0; count($line) > $i; ++$i) {
                $res[$this->line_cols[$i]] = preg_replace(array_keys($this->replacement), array_values($this->replacement), $line[$i]);
            }

            return new FlatBackupItem($this->line_name, $res, $this->line_num);
        }

        return $this->getLine();
    }

    private function nextLine(): string|false
    {
        if (feof($this->fp)) {
            return false;
        }
        ++$this->line_num;

        $line = fgets($this->fp);
        $line = trim((string) $line);

        return empty($line) ? $this->nextLine() : $line;
    }
}
