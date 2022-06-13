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
use Dotclear\Helper\Mapper\Integers;
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
        $params = new LogParam($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeCountLogs, Param, SelectStatement
        App::core()->behavior()->call('coreBeforeCountLogs', param: $params, sql: $query);

        $params->unset('order');
        $params->unset('limit');

        $query->column($query->count('log_id'));

        $record = $this->queryLogTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterCountLogs, Record
        App::core()->behavior()->call('coreAfterCountLogs', record: $record);

        return $record->fInt();
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
        $params = new LogParam($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        // --BEHAVIOR-- coreBeforeGetLogs, Param, SelectStatement
        App::core()->behavior()->call('coreBeforeGetLogs', param: $params, sql: $query);

        $query->order($query->escape($params->order('log_dt DESC')));

        if (!empty($params->limit())) {
            $query->limit($params->limit());
        }

        $record = $this->queryLogTable(param: $params, sql: $query);

        // --BEHAVIOR-- coreAfterGetLogs, Record
        App::core()->behavior()->call('coreAfterGetLogs', record: $record);

        return $record;
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

        $record = $sql->select();
        $record->extend(new LogRecordExtend());

        return $record;
    }

    /**
     * Create a new log.
     *
     * Takes a cursor as input and returns the new log ID.
     *
     * @param Cursor $cursor The current
     *
     * @return int The log id
     */
    public function createLog(Cursor $cursor): int
    {
        App::core()->con()->writeLock(App::core()->prefix() . 'log');

        try {
            // Get ID
            $sql = new SelectStatement();
            $sql->column($sql->max('log_id'));
            $sql->from(App::core()->prefix() . 'log');
            $id = $sql->select()->fInt();

            $cursor->setField('log_id', $id + 1);
            $cursor->setField('blog_id', (string) App::core()->blog()->id);
            $cursor->setField('log_dt', Clock::database());

            $this->cleanLogCursor($cursor);

            // --BEHAVIOR-- coreBeforeCreateLog, Cursor
            App::core()->behavior()->call('coreBeforeCreateLog', cursor: $cursor);

            $cursor->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterCreateLog, Cursor
        App::core()->behavior()->call('coreAfterCreateLog', cursor: $cursor);

        return $cursor->getField('log_id');
    }

    /**
     * Get the log cursor.
     *
     * @param Cursor $cursor The current
     *
     * @throws CoreException
     */
    private function cleanLogCursor(Cursor $cursor)
    {
        if ('' === $cursor->getField('log_msg')) {
            throw new CoreException(__('No log message'));
        }

        if (null === $cursor->getField('log_table')) {
            $cursor->setField('log_table', 'none');
        }

        if (null === $cursor->getField('user_id')) {
            $cursor->setField('user_id', 'unknown');
        }

        if ('' === $cursor->getField('log_dt') || null === $cursor->getField('log_dt')) {
            $cursor->setField('log_dt', Clock::database());
        }

        if (null === $cursor->getField('log_ip')) {
            $cursor->setField('log_ip', Http::realIP());
        }
    }

    /**
     * Delete given logs.
     *
     * @param Integers $ids The logs IDs
     */
    public function deleteLogs(Integers $ids): void
    {
        // --BEHAVIOR-- coreBeforeDeleteLogs, Integers
        App::core()->behavior()->call('coreBeforeDeleteLogs', ids: $ids);

        $sql = new DeleteStatement();
        $sql->where('log_id' . $sql->in($ids->dump()));
        $sql->from(App::core()->prefix() . 'log');
        $sql->run();
    }

    /**
     * Delete all logs.
     */
    public function emptyLogTable(): void
    {
        // --BEHAVIOR-- coreBeforeEmptyLogTable
        App::core()->behavior()->call('coreBeforeEmptyLogTable');

        $sql = new TruncateStatement();
        $sql->from(App::core()->prefix() . 'log');
        $sql->run();
    }
}
