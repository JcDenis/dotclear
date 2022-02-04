<?php
/**
 * @class Dotclear\Database\Driver\Pgsql\Connection
 * @brief Pgsql connection driver
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

namespace Dotclear\Database\Driver\Pgsql;

use Dotclear\Exception\DatabaseException;

use Dotclear\Database\Connection as BaseConnection;
use Dotclear\Database\InterfaceConnection;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Connection extends BaseConnection implements InterfaceConnection
{
    protected $__driver        = 'pgsql';
    protected $__syntax        = 'postgresql';
    protected $utf8_unicode_ci = null;

    private function get_connection_string($host, $user, $password, $database)
    {
        $str  = '';
        $port = false;

        if ($host) {
            if (strpos($host, ':') !== false) {
                $bits = explode(':', $host);
                $host = array_shift($bits);
                $port = abs((int) array_shift($bits));
            }
            $str .= "host = '" . addslashes($host) . "' ";

            if ($port) {
                $str .= 'port = ' . $port . ' ';
            }
        }
        if ($user) {
            $str .= "user = '" . addslashes($user) . "' ";
        }
        if ($password) {
            $str .= "password = '" . addslashes($password) . "' ";
        }
        if ($database) {
            $str .= "dbname = '" . addslashes($database) . "' ";
        }

        return $str;
    }

    public function db_connect($host, $user, $password, $database)
    {
        if (!function_exists('pg_connect')) {
            throw new DatabaseException('PHP PostgreSQL functions are not available');
        }

        $str = $this->get_connection_string($host, $user, $password, $database);

        if (($link = @pg_connect($str)) === false) {
            throw new DatabaseException('Unable to connect to database');
        }

        $this->db_post_connect($link, $database);

        return $link;
    }

    public function db_pconnect($host, $user, $password, $database)
    {
        if (!function_exists('pg_pconnect')) {
            throw new DatabaseException('PHP PostgreSQL functions are not available');
        }

        $str = $this->get_connection_string($host, $user, $password, $database);

        if (($link = @pg_pconnect($str)) === false) {
            throw new DatabaseException('Unable to connect to database');
        }

        $this->db_post_connect($link, $database);

        return $link;
    }

    private function db_post_connect($handle, $database)
    {
        if (version_compare($this->db_version($handle), '9.1') >= 0) {
            // Only for PostgreSQL 9.1+
            $result = $this->db_query($handle, "SELECT * FROM pg_collation WHERE (collcollate LIKE '%.utf8')");
            if ($this->db_num_rows($result) > 0) {
                $this->db_result_seek($result, 0);
                $row                   = $this->db_fetch_assoc($result);
                $this->utf8_unicode_ci = '"' . $row['collname'] . '"';
            }
        }
    }

    public function db_close($handle)
    {
        if (is_resource($handle) || $handle instanceof \PgSql\Connection) {
            pg_close($handle);
        }
    }

    public function db_version($handle)
    {
        if (is_resource($handle) || $handle instanceof \PgSql\Connection) {
            return pg_parameter_status($handle, 'server_version');
        }

        return '';
    }

    public function db_query($handle, $query)
    {
        if (is_resource($handle) || $handle instanceof \PgSql\Connection) {
            $res = @pg_query($handle, $query);
            if ($res === false) {
                $e = new DatabaseException($this->db_last_error($handle));

                throw $e;
            }

            return $res;
        }
    }

    public function db_exec($handle, $query)
    {
        return $this->db_query($handle, $query);
    }

    public function db_num_fields($res)
    {
        if (is_resource($res) || $res instanceof \PgSql\Result) {
            return pg_num_fields($res);
        }

        return 0;
    }

    public function db_num_rows($res)
    {
        if (is_resource($res) || $res instanceof \PgSql\Result) {
            return pg_num_rows($res);
        }

        return 0;
    }

    public function db_field_name($res, $position)
    {
        if (is_resource($res) || $res instanceof \PgSql\Result) {
            return pg_field_name($res, $position);
        }

        return '';
    }

    public function db_field_type($res, $position)
    {
        if (is_resource($res) || $res instanceof \PgSql\Result) {
            return pg_field_type($res, $position);
        }

        return '';
    }

    public function db_fetch_assoc($res)
    {
        if (is_resource($res) || $res instanceof \PgSql\Result) {
            return pg_fetch_assoc($res);
        }

        return false;
    }

    public function db_result_seek($res, $row)
    {
        if (is_resource($res) || $res instanceof \PgSql\Result) {
            return pg_result_seek($res, (int) $row);
        }

        return false;
    }

    public function db_changes($handle, $res)
    {
        if ((is_resource($handle) || $handle instanceof \PgSql\Connection) && (is_resource($res) || $res instanceof \PgSql\Result)) {
            return pg_affected_rows($res);
        }

        return 0;
    }

    public function db_last_error($handle)
    {
        if (is_resource($handle) || $handle instanceof \PgSql\Connection) {
            return pg_last_error($handle);
        }

        return false;
    }

    public function db_escape_string($str, $handle = null)
    {
        if ($handle instanceof \PgSql\Connection) {
            return pg_escape_string($handle, (string) $str);
        }

        return addslashes((string) $str);
    }

    public function db_write_lock($table)
    {
        $this->execute('BEGIN');
        $this->execute('LOCK TABLE ' . $this->escapeSystem($table) . ' IN EXCLUSIVE MODE');
    }

    public function db_unlock()
    {
        $this->execute('END');
    }

    public function vacuum($table)
    {
        $this->execute('VACUUM FULL ' . $this->escapeSystem($table));
    }

    public function dateFormat($field, $pattern)
    {
        $rep = [
            '%d' => 'DD',
            '%H' => 'HH24',
            '%M' => 'MI',
            '%m' => 'MM',
            '%S' => 'SS',
            '%Y' => 'YYYY'
        ];

        $pattern = str_replace(array_keys($rep), array_values($rep), $pattern);

        return 'TO_CHAR(' . $field . ',' . "'" . $this->escape($pattern) . "') ";
    }

    public function orderBy()
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
                    if ($this->utf8_unicode_ci) {
                        $res[] = $v['field'] . ' COLLATE ' . $this->utf8_unicode_ci . ' ' . $v['order'];
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

    public function lexFields()
    {
        $fmt = $this->utf8_unicode_ci ? '%s COLLATE ' . $this->utf8_unicode_ci : 'LOWER(%s)';
        foreach (func_get_args() as $v) {
            if (is_string($v)) {
                $res[] = sprintf($fmt, $v);
            } elseif (is_array($v)) {
                $res = array_map(fn ($i) => sprintf($fmt, $i), $v);
            }
        }

        return empty($res) ? '' : implode(',', $res);
    }

    /**
     * Function call
     *
     * Calls a PostgreSQL function an returns the result as a {@link record}.
     * After <var>$name</var>, you can add any parameters you want to append
     * them to the PostgreSQL function. You don't need to escape string in
     * arguments.
     *
     * @param string    $name    Function name
     * @return    record
     */
    public function callFunction($name)
    {
        $data = func_get_args();
        array_shift($data);

        foreach ($data as $k => $v) {
            if (is_null($v)) {
                $data[$k] = 'NULL';
            } elseif (is_string($v)) {
                $data[$k] = "'" . $this->escape($v) . "'";
            } elseif (is_array($v)) {
                $data[$k] = $v[0];
            } else {
                $data[$k] = $v;
            }
        }

        $req = 'SELECT ' . $name . "(\n" .
        implode(",\n", array_values($data)) .
            "\n) ";

        return $this->select($req);
    }
}
