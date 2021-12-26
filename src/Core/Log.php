<?php
/**
 * @brief Dotclear core log class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Exception;
use Dotclear\Exception\CoreException;

use Dotclear\Core\Core;

use Dotclear\Database\Connection;
use Dotclear\Database\Record;
use Dotclear\Database\Cursor;
use Dotclear\Network\Http;
use Dotclear\Utils\Sql\SelectStatement;
use Dotclear\Utils\Sql\JoinStatement;
use Dotclear\Utils\Sql\TruncateStatement;
use Dotclear\Utils\Sql\DeleteStatement;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Log
{
    /** @var Core       Core instance */
    protected $core;

    /** @var Connetion  Connection instance */
    protected $con;

    /** @var string     Log table name */
    protected $log_table;

    /** @var string     User table name */
    protected $user_table;

    /**
     * Constructs a new instance.
     *
     * @param      Core  $core   The core
     */
    public function __construct(Core $core)
    {
        $this->core       = &$core;
        $this->con        = &$core->con;
        $this->log_table  = $core->prefix . 'log';
        $this->user_table = $core->prefix . 'user';
    }

    /**
     * Retrieves logs. <b>$params</b> is an array taking the following
     * optionnal parameters:
     *
     * - blog_id: Get logs belonging to given blog ID
     * - user_id: Get logs belonging to given user ID
     * - log_ip: Get logs belonging to given IP address
     * - log_table: Get logs belonging to given log table
     * - order: Order of results (default "ORDER BY log_dt DESC")
     * - limit: Limit parameter
     *
     * @param      array   $params      The parameters
     * @param      bool    $count_only  Count only resultats
     *
     * @return     Record  The logs.
     */
    public function getLogs(array $params = [], bool $count_only = false): Record
    {
        $sql = new SelectStatement($this->core, 'dcLogGetLogs');

        if ($count_only) {
            $sql->column('COUNT(log_id)');
        } else {
            $sql->columns([
                'L.log_id',
                'L.user_id',
                'L.log_table',
                'L.log_dt',
                'L.log_ip',
                'L.log_msg',
                'L.blog_id',
                'U.user_name',
                'U.user_firstname',
                'U.user_displayname',
                'U.user_url',
            ]);
        }

        $sql->from($this->log_table . ' L');

        if (!$count_only) {
            $sql->join(
                (new JoinStatement($this->core, 'dcLogGetLogs'))
                ->type('LEFT')
                ->from($this->user_table . ' U')
                ->on('U.user_id = L.user_id')
                ->statement()
            );
        }

        if (!empty($params['blog_id'])) {
            if ($params['blog_id'] === '*') {
            } else {
                $sql->where('L.blog_id = ' . $sql->quote($params['blog_id']));
            }
        } else {
            $sql->where('L.blog_id = ' . $sql->quote($this->core->blog->id));
        }

        if (!empty($params['user_id'])) {
            $sql->and('L.user_id' . $sql->in($params['user_id']));
        }
        if (!empty($params['log_ip'])) {
            $sql->and('log_ip' . $sql->in($params['log_ip']));
        }
        if (!empty($params['log_table'])) {
            $sql->and('log_table' . $sql->in($params['log_table']));
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $sql->order($sql->escape($params['order']));
            } else {
                $sql->order('log_dt DESC');
            }
        }

        if (!empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        $rs = $sql->select();
        $rs->core = $this->core;
        $rs->extend('Dotclear\\Core\\RsExt\\rsExtLog');

        return $rs;
    }

    /**
     * Creates a new log. Takes a cursor as input and returns the new log ID.
     *
     * @param      Cursor  $cur    The current
     *
     * @return     int
     */
    public function addLog(Cursor $cur): int
    {
        $this->con->writeLock($this->log_table);

        try {
            # Get ID
            $sql = new SelectStatement($this->core, 'dcLogAddLog');
            $sql
                ->column('MAX(log_id)')
                ->from($this->log_table);

            $rs = $sql->select();

            $cur->log_id  = (int) $rs->f(0) + 1;
            $cur->blog_id = (string) $this->core->blog->id;
            $cur->log_dt  = date('Y-m-d H:i:s');

            $this->getLogCursor($cur, $cur->log_id);

            # --BEHAVIOR-- before:Core:Log:addLog, Dotclear\Core\Log, Dotclear\Database\Cursor
            $this->core->behaviors->call('before:Core:Log:addLog', $this, $cur);

            $cur->insert();
            $this->con->unlock();
        } catch (Exception $e) {
            $this->con->unlock();

            throw $e;
        }

        # --BEHAVIOR-- after:Core:Log:addLog, Dotclear\Core\Log, Dotclear\Database\Cursor
        $this->core->behaviors->call('after:Core:Log:addLog', $this, $cur);

        return (int) $cur->log_id;
    }

    /**
     * Deletes a log.
     *
     * @param      int|array    $id     The identifier
     * @param      bool         $all    Remove all logs
     */
    public function delLogs(int|array $id, bool $all = false): void
    {
        if ($all) {
            $sql = new TruncateStatement($this->core, 'dcLogDelLogs');
            $sql
                ->from($this->log_table);
        } else {
            $sql = new DeleteStatement($this->core, 'dcLogDelLogs');
            $sql
                ->from($this->log_table)
                ->where('log_id ' . $sql->in($id));
        }

        $sql->run();
    }

    /**
     * Gets the log cursor.
     *
     * @param      Cursor     $cur     The current
     * @param      mixed      $log_id  The log identifier
     *
     * @throws     CoreException
     */
    private function getLogCursor(Cursor $cur, ?int $log_id = null)
    {
        if ($cur->log_msg === '') {
            throw new CoreException(__('No log message'));
        }

        if ($cur->log_table === null) {
            $cur->log_table = 'none';
        }

        if ($cur->user_id === null) {
            $cur->user_id = 'unknown';
        }

        if ($cur->log_dt === '' || $cur->log_dt === null) {
            $cur->log_dt = date('Y-m-d H:i:s');
        }

        if ($cur->log_ip === null) {
            $cur->log_ip = Http::realIP();
        }

        $log_id = is_int($log_id) ? $log_id : (int) $cur->log_id;
    }
}
