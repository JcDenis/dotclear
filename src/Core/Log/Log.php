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
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\TruncateStatement;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Exception\CoreException;
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
     * @var string $log_table
     *             Log table name
     */
    protected $log_table = 'log';

    /**
     * @var string $user_table
     *             User table name
     */
    protected $user_table = 'user';

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
     * @param array $params     The parameters
     * @param bool  $count_only Count only resultats
     *
     * @return Record the logs
     */
    public function get(array $params = [], bool $count_only = false): Record
    {
        $sql = SelectStatement::init(__METHOD__)
            ->from(App::core()->prefix . $this->log_table . ' L')
        ;

        if ($count_only) {
            $sql->column($sql->count('log_id'));
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
            $sql->join(
                JoinStatement::init(__METHOD__)
                    ->type('LEFT')
                    ->from(App::core()->prefix . $this->user_table . ' U')
                    ->on('U.user_id = L.user_id')
                    ->statement()
            );
        }

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

        if (!$count_only) {
            $sql->order(
                empty($params['order']) ?
                'log_dt DESC' :
                $sql->escape($params['order'])
            );
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
        App::core()->con()->writeLock(App::core()->prefix . $this->log_table);

        try {
            // Get ID
            $id = SelectStatement::init(__METHOD__)
                ->column('MAX(log_id)')
                ->from(App::core()->prefix . $this->log_table)
                ->select()->fInt();

            $cur->setField('log_id', $id + 1);
            $cur->setField('blog_id', (string) App::core()->blog()->id);
            $cur->setField('log_dt', date('Y-m-d H:i:s'));

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
     * @param array|int $id  The identifier
     * @param bool      $all Remove all logs
     */
    public function delete(int|array $id, bool $all = false): void
    {
        if ($all) {
            $sql = TruncateStatement::init(__METHOD__);
        } else {
            $sql = new DeleteStatement(__METHOD__);
            $sql->where('log_id' . $sql->in($id));
        }

        $sql
            ->from(App::core()->prefix . $this->log_table)
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
            $cur->setField('log_dt', date('Y-m-d H:i:s'));
        }

        if (null === $cur->getField('log_ip')) {
            $cur->setField('log_ip', Http::realIP());
        }

        $log_id = is_int($log_id) ? $log_id : (int) $cur->getField('log_id');
    }
}
