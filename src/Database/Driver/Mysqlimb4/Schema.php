<?php
/**
 * @note Dotclear\Database\Driver\Mysqlimb4\Schema
 * @brief Mysql mb4 schema driver
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Database
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Driver\Mysqlimb4;

use Dotclear\Database\Driver\Mysqli\Schema as BaseSchema;

class Schema extends BaseSchema
{
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
            "\n) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

        $this->con->execute($sql);
    }
}
