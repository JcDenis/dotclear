<?php

/**
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\TruncateStatement;
use Dotclear\Helper\Network\Http;
use Dotclear\Exception\BadRequestException;
use Dotclear\Interface\Core\LogInterface;
use Dotclear\Schema\Extension\Log as ExtLog;
use Throwable;

/**
 * @brief   Core log handler.
 *
 * @since   2.28, container services have been added to constructor
 * @since   2.36, constructor arguments has been replaced by Core instance
 */
class Log implements LogInterface
{
    /**
     * Full log table name (including db prefix).
     */
    protected string $log_table;

    /**
     * Full user table name (including db prefix).
     */
    protected string $user_table;

    /**
     * Constructs a new instance.
     *
     * @param   Core    $core   The core container
     */
    public function __construct(
        protected Core $core
    ) {
        $this->log_table  = $this->core->db()->con()->prefix() . self::LOG_TABLE_NAME;
        $this->user_table = $this->core->db()->con()->prefix() . $this->core->auth()::USER_TABLE_NAME;
    }

    /**
     * Get log table name.
     *
     * @deprecated since 2.28, use App::log()::LOG_TABLE_NAME instead
     *
     * @return  string  The log database table name
     */
    public function getTable(): string
    {
        $this->core->deprecated()->set('App::log()::LOG_TABLE_NAME', '2.28');

        return self::LOG_TABLE_NAME;
    }

    public function openLogCursor(): Cursor
    {
        return $this->core->db()->con()->openCursor($this->log_table);
    }

    public function getLogs(array $params = [], bool $count_only = false): MetaRecord
    {
        $sql = new SelectStatement();

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
        }

        $sql->from($sql->alias($this->log_table, 'L'));

        if (!$count_only) {
            $sql->join(
                (new JoinStatement())
                ->left()
                ->from($sql->alias($this->user_table, 'U'))
                ->on('U.user_id = L.user_id')
                ->statement()
            );
        }

        if (!empty($params['blog_id'])) {
            if ($params['blog_id'] === '*') {
                // Nothing to add here
            } else {
                $sql->where('L.blog_id = ' . $sql->quote($params['blog_id']));
            }
        } else {
            $sql->where('L.blog_id = ' . $sql->quote($this->core->blog()->id()));
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

        if (!$count_only && !empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        $rs = $sql->select();
        if ($rs instanceof MetaRecord) {
            $rs->extend(ExtLog::class);
        }

        return $rs ?? MetaRecord::newFromArray([]);
    }

    public function addLog(Cursor $cur): int
    {
        $this->core->db()->con()->writeLock($this->log_table);

        try {
            # Get ID
            $sql = new SelectStatement();
            $sql
                ->column($sql->max('log_id'))
                ->from($this->log_table);

            $rs = $sql->select();

            $cur->log_id = $rs instanceof MetaRecord ? (int) $rs->f(0) + 1 : 1;

            if ($cur->log_msg === '') {
                throw new BadRequestException(__('No log message'));
            }

            if ($cur->log_table === null) {
                $cur->log_table = 'none';
            }

            if ($cur->user_id === null) {
                $cur->user_id = 'unknown';
            }

            if ($cur->blog_id === null) {
                $cur->blog_id = $this->core->blog()->id();
            }

            if ($cur->log_dt === '' || $cur->log_dt === null) {
                $cur->log_dt = date('Y-m-d H:i:s');
            }

            if ($cur->log_ip === null) {
                $cur->log_ip = Http::realIP();
            }

            # --BEHAVIOR-- coreBeforeLogCreate -- Log, Cursor
            $this->core->behavior()->callBehavior('coreBeforeLogCreate', $this, $cur);

            $cur->insert();
            $this->core->db()->con()->unlock();
        } catch (Throwable $e) {
            $this->core->db()->con()->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterLogCreate -- Log, Cursor
        $this->core->behavior()->callBehavior('coreAfterLogCreate', $this, $cur);

        return $cur->log_id;
    }

    public function delLog(int $id): void
    {
        $sql = new DeleteStatement();
        $sql
            ->from($this->log_table)
            ->where('log_id = ' . $id)
            ->delete();
    }

    public function delLogs($id, bool $all = false): void
    {
        if ($all) {
            $this->delAllLogs();
        } elseif (is_int($id)) {
            $this->delLog($id);
        } else {
            $sql = new DeleteStatement();
            $sql
                ->from($this->log_table)
                ->where('log_id ' . $sql->in($id))
                ->delete();
        }
    }

    public function delAllLogs(): void
    {
        $sql = new TruncateStatement();
        $sql
            ->from($this->log_table)
            ->run();
    }
}
