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
use Dotclear\Core\RsExt\RsExtLog;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\TruncateStatement;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Log handling methods.
 *
 * @ingroup  Core Log
 */
class Log
{
    /**
     * Retrieve logs count.
     *
     * @see self::get() whitout paramaeter order.
     *
     * @param array $params The parameters
     *
     * @return int The logs count
     */
    public function count(array $params = []): int
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->from(App::core()->prefix() . 'log L')
            ->column($sql->count('log_id'))
        ;

        return $this->query($params, $sql)->fInt(0);
    }

    /**
     * Retrieve logs.
     *
     * <b>$params</b> is an array taking the following
     * optionnal parameters:
     *
     * - blog_id: Get logs belonging to given blog ID
     * - user_id: Get logs belonging to given user ID
     * - log_ip: Get logs belonging to given IP address
     * - log_table: Get logs belonging to given log table
     * - log_msg: Get logs belonging to a given message
     * - order: Order of results (default "ORDER BY log_dt DESC")
     * - limit: Limit parameter
     *
     * @param array $params The parameters
     *
     * @return Record The logs
     */
    public function get(array $params = []): Record
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->from(App::core()->prefix() . 'log L')
            ->order(
                empty($params['order']) ?
                'log_dt DESC' :
                $sql->escape($params['order'])
            )
        ;

        return $this->query($params, $sql);
    }

    /**
     * Query log table.
     *
     * @param array           $params The params
     * @param SelectStatement $sql    The partial sql statement
     *
     * @return Record The result
     */
    private function query(array $params, SelectStatement $sql): Record
    {
        if (!empty($params['blog_id'])) {
            if ('*' != $params['blog_id']) {
                $sql->where('L.blog_id = ' . $sql->quote($params['blog_id']));
            }
        } else {
            $sql->where('L.blog_id = ' . $sql->quote(App::core()->blog()->id));
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
        if (!empty($params['log_msg'])) {
            $sql->and('log_msg' . $sql->in($params['log_msg']));
        }

        if (!empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        $rs = $sql->select();
        $rs->extend(new RsExtLog());

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
    public function add(Cursor $cur): int
    {
        App::core()->con()->writeLock(App::core()->prefix() . 'log');

        try {
            // Get ID
            $id = SelectStatement::init(__METHOD__)
                ->column('MAX(log_id)')
                ->from(App::core()->prefix() . 'log')
                ->select()->fInt();

            $cur->setField('log_id', $id + 1);
            $cur->setField('blog_id', (string) App::core()->blog()->id);
            $cur->setField('log_dt', Clock::database());

            $this->cursor($cur, $cur->getField('log_id'));

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
     * Delete a log.
     *
     * @param array|int $id The identifier
     */
    public function delete(int|array $id): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where('log_id' . $sql->in($id))
            ->from(App::core()->prefix() . 'log')
            ->run()
        ;
    }

    /**
     * Delete all logs.
     */
    public function truncate(): void
    {
        TruncateStatement::init(__METHOD__)
            ->from(App::core()->prefix() . 'log')
            ->run()
        ;
    }

    /**
     * Get the log cursor.
     *
     * @param Cursor   $cur    The current
     * @param null|int $log_id The log identifier
     *
     * @throws CoreException
     */
    private function cursor(Cursor $cur, ?int $log_id = null)
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
}
