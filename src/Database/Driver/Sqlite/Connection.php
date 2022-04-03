<?php
/**
 * @class Dotclear\Database\Driver\Sqlite\Connection
 * @brief Sqlite connection driver
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage Database
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Driver\Sqlite;


use Dotclear\Database\AbstractConnection;
use Dotclear\Database\Record;
use Dotclear\Database\StaticRecord;
use Dotclear\Exception\DatabaseException;

class Connection extends AbstractConnection
{
    protected $__driver        = 'sqlite';
    protected $__syntax        = 'sqlite';
    protected $utf8_unicode_ci = null;
    protected $vacuum          = false;

    public function db_connect(string $host, string $user, string $password, string $database): mixed
    {
        if (!class_exists('\PDO') || !in_array('sqlite', \PDO::getAvailableDrivers())) {
            throw new DatabaseException('PDO SQLite class is not available');
        }

        $link = new \PDO('sqlite:' . $database);
        $this->db_post_connect($link, $database);

        return $link;
    }

    public function db_pconnect(string $host, string $user, string $password, string $database): mixed
    {
        if (!class_exists('\PDO') || !in_array('sqlite', \PDO::getAvailableDrivers())) {
            throw new DatabaseException('PDO SQLite class is not available');
        }

        $link = new \PDO('sqlite:' . $database, null, null, [\PDO::ATTR_PERSISTENT => true]);
        $this->db_post_connect($link, $database);

        return $link;
    }

    private function db_post_connect($handle, $database)
    {
        if ($handle instanceof \PDO) {
            $this->db_exec($handle, 'PRAGMA short_column_names = 1');
            $this->db_exec($handle, 'PRAGMA encoding = "UTF-8"');
            $handle->sqliteCreateFunction('now', [$this, 'now'], 0);
            if (class_exists('\Collator') && method_exists($handle, 'sqliteCreateCollation')) {
                $this->utf8_unicode_ci = new \Collator('root');
                if (!$handle->sqliteCreateCollation('utf8_unicode_ci', [$this->utf8_unicode_ci, 'compare'])) {
                    $this->utf8_unicode_ci = null;
                }
            }
        }
    }

    public function db_close(mixed $handle): void
    {
        if ($handle instanceof \PDO) {
            if ($this->vacuum) {
                $this->db_exec($handle, 'VACUUM');
            }
            $handle       = null;
            $this->__link = false;
        }
    }

    public function db_version(mixed $handle): string
    {
        if ($handle instanceof \PDO) {
            return $handle->getAttribute(\PDO::ATTR_SERVER_VERSION);
        }

        return '';
    }

    # There is no other way than get all selected data in a staticRecord
    public function select(string $sql): Record //StaticRecord
    {
        $result              = $this->db_query($this->__link, $sql);
        $this->__last_result = &$result;

        $info         = [];
        $info['con']  = &$this;
        $info['cols'] = $this->db_num_fields($result);
        $info['info'] = [];

        for ($i = 0; $i < $info['cols']; $i++) {
            $info['info']['name'][] = $this->db_field_name($result, $i);
            $info['info']['type'][] = $this->db_field_type($result, $i);
        }

        $data = [];
        while ($r = $result->fetch(\PDO::FETCH_ASSOC)) {
            $R = [];
            foreach ($r as $k => $v) {
                $k     = preg_replace('/^(.*)\./', '', $k);
                $R[$k] = $v;
                $R[]   = &$R[$k];
            }
            $data[] = $R;
        }

        $info['rows'] = count($data);
        $result->closeCursor();

        return new StaticRecord($data, $info);
    }

    public function db_query(mixed $handle, string $query): mixed
    {
        if ($handle instanceof \PDO) {
            $res = $handle->query($query);
            if ($res === false) {
                $e = new DatabaseException($this->db_last_error($handle));

                throw $e;
            }

            return $res;
        }

        return null;
    }

    public function db_exec(mixed $handle, string $query): mixed
    {
        return $this->db_query($handle, $query);
    }

    public function db_num_fields(mixed $res): int
    {
        if ($res instanceof \PDOStatement) {
            return $res->columnCount();
        }

        return 0;
    }

    public function db_num_rows(mixed $res): int
    {
        return 0;
    }

    public function db_field_name(mixed $res, int $position): string
    {
        if ($res instanceof \PDOStatement) {
            $m = $res->getColumnMeta($position);

            return preg_replace('/^.+\./', '', $m['name']); # we said short_column_names = 1
        }

        return '';
    }

    public function db_field_type(mixed $res, int $position): string
    {
        if ($res instanceof \PDOStatement) {
            $m = $res->getColumnMeta($position);
            return match($m['\PDO_type']) {
                \PDO::PARAM_BOOL => 'boolean',
                \PDO::PARAM_NULL => 'null',
                \PDO::PARAM_INT  => 'integer',
                default          => 'varchar',
            };
        }

        return '';
    }

    public function db_fetch_assoc(mixed $res): array|false
    {
        return false;
    }

    public function db_result_seek(mixed $res, int $row): bool
    {
        return false;
    }

    public function db_changes(mixed $handle, mixed $res): int
    {
        if ($res instanceof \PDOStatement) {
            return $res->rowCount();
        }

        return 0;
    }

    public function db_last_error(mixed $handle): string|false
    {
        if ($handle instanceof \PDO) {
            $err = $handle->errorInfo();

            return $err[2] . ' (' . $err[1] . ')';
        }

        return false;
    }

    public function db_escape_string(?string $str, mixed $handle = null): string
    {
        if ($handle instanceof \PDO) {
            return trim($handle->quote($str), "'");
        }

        return $str;
    }

    public function escapeSystem(string $str): string
    {
        return "'" . $this->escape($str) . "'";
    }

    public function begin(): void
    {
        if ($this->__link instanceof \PDO) {
            $this->__link->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->__link instanceof \PDO) {
            $this->__link->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->__link instanceof \PDO) {
            $this->__link->rollBack();
        }
    }

    public function db_write_lock(string $table): void
    {
        $this->execute('BEGIN EXCLUSIVE TRANSACTION');
    }

    public function db_unlock(): void
    {
        $this->execute('END');
    }

    public function db_vacuum(string $table): void
    {
        $this->vacuum = true;
    }

    public function dateFormat(string $field, string $pattern): string
    {
        return "strftime('" . $this->escape($pattern) . "'," . $field . ') ';
    }

    public function orderBy(): string
    {
        $default = [
            'order'   => '',
            'collate' => false
        ];
        foreach (func_get_args() as $v) {
            if (is_string($v)) {
                $res[] = $v;
            } elseif (is_array($v) && !empty($v['field'])) {
                $v          = array_merge($default, $v);
                $v['order'] = (strtoupper($v['order']) == 'DESC' ? 'DESC' : '');
                if ($v['collate']) {
                    if (class_exists('\Collator') && $this->utf8_unicode_ci instanceof \Collator) {
                        $res[] = $v['field'] . ' COLLATE utf8_unicode_ci ' . $v['order'];
                    } else {
                        $res[] = 'LOWER(' . $v['field'] . ') ' . $v['order'];
                    }
                } else {
                    $res[] = $v['field'] . ' ' . $v['order'];
                }
            }
        }

        return empty($res) ? '' : ' ORDER BY ' . implode(',', $res) . ' ';
    }

    public function lexFields(): string
    {
        $fmt = class_exists('\Collator') && $this->utf8_unicode_ci instanceof \Collator ? '%s COLLATE utf8_unicode_ci' : 'LOWER(%s)';
        foreach (func_get_args() as $v) {
            if (is_string($v)) {
                $res[] = sprintf($fmt, $v);
            } elseif (is_array($v)) {
                $res = array_map(function ($i) use ($fmt) {return sprintf($fmt, $i);}, $v);
            }
        }

        return empty($res) ? '' : implode(',', $res);
    }

    # Internal SQLite function that adds NOW() SQL function.
    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
