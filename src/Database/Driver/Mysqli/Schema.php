<?php
/**
 * @note Dotclear\Database\Driver\Mysqli\Schema
 * @brief Mysql schema driver
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Database
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Driver\Mysqli;

use Dotclear\Database\AbstractSchema;

class Schema extends AbstractSchema
{
    public function dbt2udt(string $type, ?int &$len, mixed &$default): string
    {
        $type = parent::dbt2udt($type, $len, $default);

        switch ($type) {
            case 'float':
                return 'real';

            case 'double':
                return 'float';

            case 'datetime':
                // DATETIME real type is TIMESTAMP
                if ("'1970-01-01 00:00:00'" == $default) {
                    // Bad hack
                    $default = 'now()';
                }

                return 'timestamp';

            case 'integer':
            case 'mediumint':
                if (11 == $len) {
                    $len = 0;
                }

                return 'integer';

            case 'bigint':
                if (20 == $len) {
                    $len = 0;
                }

                break;

            case 'tinyint':
            case 'smallint':
                if (6 == $len) {
                    $len = 0;
                }

                return 'smallint';

            case 'numeric':
                $len = 0;

                break;

            case 'tinytext':
            case 'longtext':
                return 'text';
        }

        return $type;
    }

    public function udt2dbt(string $type, ?int &$len, mixed &$default): string
    {
        $type = parent::udt2dbt($type, $len, $default);

        switch ($type) {
            case 'real':
                return 'float';

            case 'float':
                return 'double';

            case 'timestamp':
                if ('now()' == $default) {
                    // MySQL does not support now() default value...
                    $default = "'1970-01-01 00:00:00'";
                }

                return 'datetime';

            case 'text':
                $len = 0;

                return 'longtext';
        }

        return $type;
    }

    public function db_get_tables(): array
    {
        $sql = 'SHOW TABLES';
        $rs  = $this->con->select($sql);

        $res = [];
        while ($rs->fetch()) {
            $res[] = $rs->f(0);
        }

        return $res;
    }

    public function db_get_columns(string $table): array
    {
        $sql = 'SHOW COLUMNS FROM ' . $this->con->escapeSystem($table);
        $rs  = $this->con->select($sql);

        $res = [];
        while ($rs->fetch()) {
            $field   = trim($rs->f('Field'));
            $type    = trim($rs->f('Type'));
            $null    = strtolower($rs->f('Null')) == 'yes';
            $default = $rs->f('Default');

            $len = null;
            if (preg_match('/^(.+?)\(([\d,]+)\)$/si', $type, $m)) {
                $type = $m[1];
                $len  = (int) $m[2];
            }

            // $default from db is a string and is NULL in schema so upgrade failed.
            if (strtoupper((string) $default) == 'NULL') {
                $default = null;
            } elseif ('' != $default && !is_numeric($default)) {
                $default = "'" . $default . "'";
            }

            $res[$field] = [
                'type'    => $type,
                'len'     => $len,
                'null'    => $null,
                'default' => $default,
            ];
        }

        return $res;
    }

    public function db_get_keys(string $table): array
    {
        $sql = 'SHOW INDEX FROM ' . $this->con->escapeSystem($table);
        $rs  = $this->con->select($sql);

        $t   = [];
        $res = [];
        while ($rs->fetch()) {
            $key_name = $rs->f('Key_name');
            $unique   = $rs->f('Non_unique') == 0;
            $seq      = $rs->f('Seq_in_index');
            $col_name = $rs->f('Column_name');

            if ('PRIMARY' == $key_name || $unique) {
                $t[$key_name]['cols'][$seq] = $col_name;
                $t[$key_name]['unique']     = $unique;
            }
        }

        foreach ($t as $name => $idx) {
            ksort($idx['cols']);

            $res[] = [
                'name'    => $name,
                'primary' => 'PRIMARY' == $name,
                'unique'  => $idx['unique'],
                'cols'    => array_values($idx['cols']),
            ];
        }

        return $res;
    }

    public function db_get_indexes(string $table): array
    {
        $sql = 'SHOW INDEX FROM ' . $this->con->escapeSystem($table);
        $rs  = $this->con->select($sql);

        $t   = [];
        $res = [];
        while ($rs->fetch()) {
            $key_name = $rs->f('Key_name');
            $unique   = $rs->f('Non_unique') == 0;
            $seq      = $rs->f('Seq_in_index');
            $col_name = $rs->f('Column_name');
            $type     = $rs->f('Index_type');

            if ('PRIMARY' != $key_name && !$unique) {
                $t[$key_name]['cols'][$seq] = $col_name;
                $t[$key_name]['type']       = $type;
            }
        }

        foreach ($t as $name => $idx) {
            ksort($idx['cols']);

            $res[] = [
                'name' => $name,
                'type' => $idx['type'],
                'cols' => $idx['cols'],
            ];
        }

        return $res;
    }

    public function db_get_references(string $table): array
    {
        $sql = 'SHOW CREATE TABLE ' . $this->con->escapeSystem($table);
        $rs  = $this->con->select($sql);

        $s = $rs->f(1);

        $res = [];

        $n = preg_match_all('/^\s*CONSTRAINT\s+`(.+?)`\s+FOREIGN\s+KEY\s+\((.+?)\)\s+REFERENCES\s+`(.+?)`\s+\((.+?)\)(.*?)$/msi', $s, $match);
        if (0 < $n) {
            foreach ($match[1] as $i => $name) {
                // Columns transformation
                $t_cols = str_replace('`', '', $match[2][$i]);
                $t_cols = explode(',', $t_cols);
                $r_cols = str_replace('`', '', $match[4][$i]);
                $r_cols = explode(',', $r_cols);

                // ON UPDATE|DELETE
                $on        = trim($match[5][$i], ', ');
                $on_delete = null;
                $on_update = null;
                if ('' != $on) {
                    if (preg_match('/ON DELETE (.+?)(?:\s+ON|$)/msi', $on, $m)) {
                        $on_delete = strtolower(trim($m[1]));
                    }
                    if (preg_match('/ON UPDATE (.+?)(?:\s+ON|$)/msi', $on, $m)) {
                        $on_update = strtolower(trim($m[1]));
                    }
                }

                $res[] = [
                    'name'    => $name,
                    'c_cols'  => $t_cols,
                    'p_table' => $match[3][$i],
                    'p_cols'  => $r_cols,
                    'update'  => $on_update,
                    'delete'  => $on_delete,
                ];
            }
        }

        return $res;
    }

    public function db_create_table(string $name, array $fields): void
    {
        $a = [];

        foreach ($fields as $n => $f) {
            $type    = $f['type'];
            $len     = (int) $f['len'];
            $default = $f['default'];
            $null    = $f['null'];

            $type = $this->udt2dbt($type, $len, $default);
            $len  = 0 < $len ? '(' . $len . ')' : '';
            $null = $null ? 'NULL' : 'NOT NULL';

            if (null === $default) {
                $default = 'DEFAULT NULL';
            } elseif (false !== $default) {
                $default = 'DEFAULT ' . $default . ' ';
            } else {
                $default = '';
            }

            $a[] = $this->con->escapeSystem($n) . ' ' .
                $type . $len . ' ' . $null . ' ' . $default;
        }

        $sql = 'CREATE TABLE ' . $this->con->escapeSystem($name) . " (\n" .
        implode(",\n", $a) .
            "\n) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin ";

        $this->con->execute($sql);
    }

    public function db_create_field(string $table, string $name, string $type, ?int $len, bool $null, mixed $default): void
    {
        $type = $this->udt2dbt($type, $len, $default);

        if (null === $default) {
            $default = 'DEFAULT NULL';
        } elseif (false !== $default) {
            $default = 'DEFAULT ' . $default;
        } else {
            $default = '';
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ADD COLUMN ' . $this->con->escapeSystem($name) . ' ' . $type . ((int) $len > 0 ? '(' . (int) $len . ')' : '') . ' ' . ($null ? 'NULL' : 'NOT NULL') . ' ' . $default;

        $this->con->execute($sql);
    }

    public function db_create_primary(string $table, string $name, array $cols): void
    {
        $c = [];
        foreach ($cols as $v) {
            $c[] = $this->con->escapeSystem($v);
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ' .
        'ADD CONSTRAINT PRIMARY KEY (' . implode(',', $c) . ') ';

        $this->con->execute($sql);
    }

    public function db_create_unique(string $table, string $name, array $cols): void
    {
        $c = [];
        foreach ($cols as $v) {
            $c[] = $this->con->escapeSystem($v);
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ' .
        'ADD CONSTRAINT UNIQUE KEY ' . $this->con->escapeSystem($name) . ' ' .
        '(' . implode(',', $c) . ') ';

        $this->con->execute($sql);
    }

    public function db_create_index(string $table, string $name, string $type, array $cols): void
    {
        $c = [];
        foreach ($cols as $v) {
            $c[] = $this->con->escapeSystem($v);
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ' .
        'ADD INDEX ' . $this->con->escapeSystem($name) . ' USING ' . $type . ' ' .
        '(' . implode(',', $c) . ') ';

        $this->con->execute($sql);
    }

    public function db_create_reference(string $name, string $c_table, array $c_cols, string $p_table, array $p_cols, string $update, string $delete): void
    {
        $c = [];
        $p = [];
        foreach ($c_cols as $v) {
            $c[] = $this->con->escapeSystem($v);
        }
        foreach ($p_cols as $v) {
            $p[] = $this->con->escapeSystem($v);
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($c_table) . ' ' .
        'ADD CONSTRAINT ' . $name . ' FOREIGN KEY ' .
        '(' . implode(',', $c) . ') ' .
        'REFERENCES ' . $this->con->escapeSystem($p_table) . ' ' .
        '(' . implode(',', $p) . ') ';

        if ($update) {
            $sql .= 'ON UPDATE ' . $update . ' ';
        }
        if ($delete) {
            $sql .= 'ON DELETE ' . $delete . ' ';
        }

        $this->con->execute($sql);
    }

    public function db_alter_field(string $table, string $name, string $type, ?int $len, bool $null, $default): void
    {
        $type = $this->udt2dbt($type, $len, $default);

        if (null === $default) {
            $default = 'DEFAULT NULL';
        } elseif (false !== $default) {
            $default = 'DEFAULT ' . $default;
        } else {
            $default = '';
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ' .
        'CHANGE COLUMN ' . $this->con->escapeSystem($name) . ' ' . $this->con->escapeSystem($name) . ' ' . $type . (0 < $len ? '(' . $len . ')' : '') . ' ' . ($null ? 'NULL' : 'NOT NULL') . ' ' . $default;

        $this->con->execute($sql);
    }

    public function db_alter_primary(string $table, string $name, string $newname, array $cols): void
    {
        $c = [];
        foreach ($cols as $v) {
            $c[] = $this->con->escapeSystem($v);
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ' .
        'DROP PRIMARY KEY, ADD PRIMARY KEY ' .
        '(' . implode(',', $c) . ') ';

        $this->con->execute($sql);
    }

    public function db_alter_unique(string $table, string $name, string $newname, array $cols): void
    {
        $c = [];
        foreach ($cols as $v) {
            $c[] = $this->con->escapeSystem($v);
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ' .
        'DROP INDEX ' . $this->con->escapeSystem($name) . ', ' .
        'ADD UNIQUE ' . $this->con->escapeSystem($newname) . ' ' .
        '(' . implode(',', $c) . ') ';

        $this->con->execute($sql);
    }

    public function db_alter_index(string $table, string $name, string $newname, string $type, array $cols): void
    {
        $c = [];
        foreach ($cols as $v) {
            $c[] = $this->con->escapeSystem($v);
        }

        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ' .
        'DROP INDEX ' . $this->con->escapeSystem($name) . ', ' .
        'ADD INDEX ' . $this->con->escapeSystem($newname) . ' ' .
        'USING ' . $type . ' ' .
        '(' . implode(',', $c) . ') ';

        $this->con->execute($sql);
    }

    public function db_alter_reference(string $name, string $newname, string $c_table, array $c_cols, string $p_table, array $p_cols, string $update, string $delete): void
    {
        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($c_table) . ' ' .
        'DROP FOREIGN KEY ' . $this->con->escapeSystem($name);

        $this->con->execute($sql);
        $this->createReference($newname, $c_table, $c_cols, $p_table, $p_cols, $update, $delete);
    }

    public function db_drop_unique(string $table, string $name): void
    {
        $sql = 'ALTER TABLE ' . $this->con->escapeSystem($table) . ' ' .
        'DROP INDEX ' . $this->con->escapeSystem($name);
        $this->con->execute($sql);
    }
}
