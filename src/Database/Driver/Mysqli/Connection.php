<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database\Driver\Mysqli;

// Dotclear\Database\Driver\Mysqli\Connection
use Dotclear\Database\AbstractConnection;
use Dotclear\Exception\DatabaseException;
use mysqli_result;
use mysqli;

/**
 * Mysql connection driver.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Database Connection Mysql
 */
class Connection extends AbstractConnection
{
    protected $__driver = 'mysqli';
    protected $__syntax = 'mysql';

    public function db_connect(string $host, string $user, string $password, string $database): mixed
    {
        if (!function_exists('mysqli_connect')) {
            throw new DatabaseException('PHP MySQLi functions are not available');
        }

        $port   = abs((int) ini_get('mysqli.default_port'));
        $socket = '';
        if (str_contains($host, ':')) {
            // Port or socket given
            $bits   = explode(':', $host);
            $host   = array_shift($bits);
            $socket = array_shift($bits);
            if (abs((int) $socket) > 0) {
                // TCP/IP connection on given port
                $port   = abs((int) $socket);
                $socket = '';
            } else {
                // Socket connection
                $port = 0;
            }
        }
        if (false === ($link = @mysqli_connect($host, $user, $password, $database, $port, $socket))) {
            throw new DatabaseException('Unable to connect to database');
        }

        $this->db_post_connect($link, $database);

        return $link;
    }

    public function db_pconnect(string $host, string $user, string $password, string $database): mixed
    {
        // No pconnect with mysqli, below code is for comatibility
        return $this->db_connect($host, $user, $password, $database);
    }

    private function db_post_connect($link, $database)
    {
        if (version_compare($this->db_version($link), '4.1', '>=')) {
            $this->db_query($link, 'SET NAMES utf8');
            $this->db_query($link, 'SET CHARACTER SET utf8');
            $this->db_query($link, "SET COLLATION_CONNECTION = 'utf8_general_ci'");
            $this->db_query($link, "SET COLLATION_SERVER = 'utf8_general_ci'");
            $this->db_query($link, "SET CHARACTER_SET_SERVER = 'utf8'");
            if (version_compare($this->db_version($link), '8.0', '<')) {
                // Setting CHARACTER_SET_DATABASE is obosolete for MySQL 8.0+
                $this->db_query($link, "SET CHARACTER_SET_DATABASE = 'utf8'");
            }
            $link->set_charset('utf8');
        }
    }

    public function db_close(mixed $handle): void
    {
        if ($handle instanceof mysqli) {
            mysqli_close($handle);
        }
    }

    public function db_version(mixed $handle): string
    {
        if ($handle instanceof mysqli) {
            $v = mysqli_get_server_version($handle);

            return sprintf('%s.%s.%s', ($v - ($v % 10000)) / 10000, ($v - ($v % 100)) % 10000 / 100, $v % 100);
        }

        return '';
    }

    public function db_query(mixed $handle, string $query): mixed
    {
        if ($handle instanceof mysqli) {
            $res = @mysqli_query($handle, $query);
            if (false === $res) {
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
        if ($res instanceof mysqli_result) {
            // return mysql_num_fields($res);
            return $res->field_count;
        }

        return 0;
    }

    public function db_num_rows(mixed $res): int
    {
        if ($res instanceof mysqli_result) {
            return $res->num_rows;
        }

        return 0;
    }

    public function db_field_name(mixed $res, int $position): string
    {
        if ($res instanceof mysqli_result) {
            $res->field_seek($position);
            $finfo = $res->fetch_field();

            // @phpstan-ignore-next-line (Failed to see $finfo better than an object)
            return $finfo->name;
        }

        return '';
    }

    public function db_field_type(mixed $res, int $position): string
    {
        if ($res instanceof mysqli_result) {
            $res->field_seek($position);
            $finfo = $res->fetch_field();

            // @phpstan-ignore-next-line (Failed to see $finfo better than an object)
            return $this->_convert_types($finfo->type);
        }

        return '';
    }

    public function db_fetch_assoc(mixed $res): array|false
    {
        if ($res instanceof mysqli_result) {
            $v = $res->fetch_assoc();

            return (null === $v) ? false : $v;
        }

        return false;
    }

    public function db_result_seek(mixed $res, int $row): bool
    {
        if ($res instanceof mysqli_result) {
            return $res->data_seek($row);
        }

        return false;
    }

    public function db_changes(mixed $handle, mixed $res): int
    {
        if ($handle instanceof mysqli) {
            return mysqli_affected_rows($handle);
        }

        return 0;
    }

    public function db_last_error(mixed $handle): string|false
    {
        if ($handle instanceof mysqli) {
            $e = mysqli_error($handle);
            if ($e) {
                return $e . ' (' . \mysqli_errno($handle) . ')';
            }
        }

        return false;
    }

    public function db_escape_string(?string $str, mixed $handle = null): string
    {
        if ($handle instanceof mysqli) {
            return mysqli_real_escape_string($handle, (string) $str);
        }

        return addslashes($str);
    }

    public function db_write_lock(string $table): void
    {
        try {
            $this->execute('LOCK TABLES ' . $this->escapeSystem($table) . ' WRITE');
        } catch (DatabaseException $e) {
            // As lock is a privilege in MySQL, we can avoid errors with weak_locks static var
            if (!self::$weak_locks) {
                throw $e;
            }
        }
    }

    public function db_unlock(): void
    {
        try {
            $this->execute('UNLOCK TABLES');
        } catch (DatabaseException $e) {
            if (!self::$weak_locks) {
                throw $e;
            }
        }
    }

    public function db_vacuum(string $table): void
    {
        $this->execute('OPTIMIZE TABLE ' . $this->escapeSystem($table));
    }

    public function dateFormat(string $field, string $pattern): string
    {
        $pattern = str_replace('%M', '%i', $pattern);

        return 'DATE_FORMAT(' . $field . ',' . "'" . $this->escape($pattern) . "') ";
    }

    public function orderBy(): string
    {
        $default = [
            'order'   => '',
            'collate' => false,
        ];
        foreach (func_get_args() as $v) {
            if (is_string($v)) {
                $res[] = $v;
            } elseif (is_array($v) && !empty($v['field'])) {
                $v          = array_merge($default, $v);
                $v['order'] = (strtoupper($v['order']) == 'DESC' ? 'DESC' : '');
                $res[]      = $v['field'] . ($v['collate'] ? ' COLLATE utf8_unicode_ci' : '') . ' ' . $v['order'];
            }
        }

        return empty($res) ? '' : ' ORDER BY ' . implode(',', $res) . ' ';
    }

    public function lexFields(): string
    {
        $fmt = '%s COLLATE utf8_unicode_ci';
        foreach (func_get_args() as $v) {
            if (is_string($v)) {
                $res[] = sprintf($fmt, $v);
            } elseif (is_array($v)) {
                $res = array_map(fn ($i) => sprintf($fmt, $i), $v);
            }
        }

        return empty($res) ? '' : implode(',', $res);
    }

    public function concat(): string
    {
        $args = func_get_args();

        return 'CONCAT(' . implode(',', $args) . ')';
    }

    public function escapeSystem(string $str): string
    {
        return '`' . $str . '`';
    }

    protected function _convert_types(mixed $id): string
    {
        $id2type = [
            '1' => 'int',
            '2' => 'int',
            '3' => 'int',
            '8' => 'int',
            '9' => 'int',

            '16' => 'int', // BIT type recognized as unknown with mysql adapter

            '4'   => 'real',
            '5'   => 'real',
            '246' => 'real',

            '253' => 'string',
            '254' => 'string',

            '10' => 'date',
            '11' => 'time',
            '12' => 'datetime',
            '13' => 'year',

            '7' => 'timestamp',

            '252' => 'blob',
        ];
        $type = 'unknown';

        if (isset($id2type[$id])) {
            $type = $id2type[$id];
        }

        return $type;
    }

    public function db_field_cast(mixed $str, string $type): mixed
    {
        return match ($type) {
            'int', 'timestamp' => (int) $str,
            'real' => (float) $str,
            'string', 'date', 'time', 'datetime', 'year', 'blob' => (string) $str,
            default => $str,
        };
    }
}
