<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Log;

// Dotclear\Core\Log\Log
use Dotclear\App;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\TruncateStatement;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Log handling methods.
 *
 * Use generic class Param as public methods parameter
 * as we do not know where parameters come from.
 *
 * @ingroup  Core Log
 */
final class Log
{
    /**
     * Retrieve logs count.
     *
     * @see LogParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The logs count
     */
    public function countLogs(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $param = new LogParam($param);
        $param->unset('order');
        $param->unset('limit');

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->column($query->count('log_id'));

        return $this->queryLogTable(param: $param, sql: $query)->fInt();
    }

    /**
     * Retrieve logs.
     *
     * @see LogParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return Record The logs
     */
    public function getLogs(?Param $param = null, ?SelectStatement $sql = null): Record
    {
        $param = new LogParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->order($query->escape($param->order('log_dt DESC')));

        if (!empty($param->limit())) {
            $query->limit($param->limit());
        }

        return $this->queryLogTable(param: $param, sql: $query);
    }

    /**
     * Query log table.
     *
     * @param LogParam        $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return Record The result
     */
    private function queryLogTable(LogParam $param, SelectStatement $sql): Record
    {
        $sql->from(App::core()->prefix() . 'log L', false, true);

        if (null !== $param->blog_id()) {
            if ('*' != $param->blog_id()) {
                $sql->where('L.blog_id = ' . $sql->quote($param->blog_id()));
            }
        } else {
            $sql->where('L.blog_id = ' . $sql->quote(App::core()->blog()->id));
        }

        if (!empty($param->user_id())) {
            $sql->and('L.user_id' . $sql->in($param->user_id()));
        }
        if (!empty($param->log_ip())) {
            $sql->and('log_ip' . $sql->in($param->log_ip()));
        }
        if (!empty($param->log_table())) {
            $sql->and('log_table' . $sql->in($param->log_table()));
        }
        if (!empty($param->log_msg())) {
            $sql->and('log_msg' . $sql->in($param->log_msg()));
        }

        if (null !== $param->sql()) {
            $sql->sql($param->sql());
        }

        $rs = $sql->select();
        $rs->extend(new LogRecordExtend());

        return $rs;
    }

    /**
     * Create a new log.
     *
     * Takes a cursor as input and returns the new log ID.
     *
     * @param Cursor $cur The current
     *
     * @return int The log id
     */
    public function addLog(Cursor $cur): int
    {
        App::core()->con()->writeLock(App::core()->prefix() . 'log');

        try {
            // Get ID
            $sql = new SelectStatement(__METHOD__);
            $sql->column($sql->max('log_id'));
            $sql->from(App::core()->prefix() . 'log');
            $id = $sql->select()->fInt();

            $cur->setField('log_id', $id + 1);
            $cur->setField('blog_id', (string) App::core()->blog()->id);
            $cur->setField('log_dt', Clock::database());

            $this->cleanLogCursor($cur, $cur->getField('log_id'));

            // --BEHAVIOR-- coreBeforeLogCreate, Dotclear\Core\Log, Dotclear\Database\Cursor
            App::core()->behavior()->call('coreBeforeLogCreate', $this, $cur);

            $cur->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterLogCreate, Dotclear\Core\Log, Dotclear\Database\Cursor
        App::core()->behavior()->call('coreAfterLogCreate', $this, $cur);

        return $cur->getField('log_id');
    }

    /**
     * Get the log cursor.
     *
     * @param Cursor   $cur    The current
     * @param null|int $log_id The log identifier
     *
     * @throws CoreException
     */
    private function cleanLogCursor(Cursor $cur, ?int $log_id = null)
    {
        if ('' === $cur->getField('log_msg')) {
            throw new CoreException(__('No log message'));
        }

        if (null === $cur->getField('log_table')) {
            $cur->setField('log_table', 'none');
        }

        if (null === $cur->getField('user_id')) {
            $cur->setField('user_id', 'unknown');
        }

        if ('' === $cur->getField('log_dt') || null === $cur->getField('log_dt')) {
            $cur->setField('log_dt', Clock::database());
        }

        if (null === $cur->getField('log_ip')) {
            $cur->setField('log_ip', Http::realIP());
        }

        $log_id = is_int($log_id) ? $log_id : (int) $cur->getField('log_id');
    }

    /**
     * Delete a log.
     *
     * @param int $id The identifier
     */
    public function deleteLog(int $id): void
    {
        $this->deleteLogs([$id]);
    }

    /**
     * Delete given logs.
     *
     * @param array $id The identifier
     */
    public function deleteLogs(array $id): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql->where('log_id' . $sql->in($id));
        $sql->from(App::core()->prefix() . 'log');
        $sql->run();
    }

    /**
     * Delete all logs.
     */
    public function emptyLogTable(): void
    {
        $sql = new TruncateStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'log');
        $sql->run();
    }
}
