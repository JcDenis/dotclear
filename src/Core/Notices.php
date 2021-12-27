<?php
/**
 * @class Dotclear\Core\Notices
 * @brief Dotclear backend notices handling facilities
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

use Dotclear\Database\Record;
use Dotclear\Database\Cursor;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Notices
{
    /** @var Core       Core instance */
    protected $core;

    /** @var string     notices table prefix */
    protected $prefix;

    /** @var string     notices table */
    protected $table = 'notice';

    /**
     * Class constructor
     *
     * @param   Core    $core   Core instance
     */
    public function __construct(Core $core)
    {
        $this->core   = $core;
        $this->prefix = $core->prefix;
    }

    /** @see    table() */
    public function getTable(): string
    {
        return $this->table();
    }

    /**
     * Get notice table name
     *
     * @return  string  The table name
     */
    public function table(): string
    {
        return $this->table;
    }

    /** @see    get() */
    public function getNotices(array $params = [], bool $count_only = false): Record
    {
        return $this->get($params, $count_only);
    }

    /**
     * Get notices
     *
     * Parameters can be :
     * - ses_id => (string) session id
     * - notice_id => one or more notice id
     * - notice_type => one or more notice type (alias notice_format)
     * - order
     * - limit
     * - sql
     *
     * @param   array           $params         The params
     * @param   bool|boolean    $count_only     Count only
     * @return  Record                          Notices record
     */
    public function get(array $params = [], bool $count_only = false): Record
    {
        if ($count_only) {
            $f = 'COUNT(notice_id)';
        } else {
            $f = 'notice_id, ses_id, notice_type, notice_ts, notice_msg, notice_format, notice_options';
        }

        $strReq = 'SELECT ' . $f . ' FROM ' . $this->prefix . $this->table . ' ';

        $strReq .= "WHERE ses_id = '";
        if (isset($params['ses_id']) && $params['ses_id'] !== '') {
            $strReq .= (string) $params['ses_id'];
        } else {
            $strReq .= (string) session_id();
        }
        $strReq .= "' ";

        if (isset($params['notice_id']) && $params['notice_id'] !== '') {
            if (is_array($params['notice_id'])) {
                array_walk($params['notice_id'], function (&$v, $k) { if ($v !== null) {$v = (int) $v;}});
            } else {
                $params['notice_id'] = [(int) $params['notice_id']];
            }
            $strReq .= 'AND notice_id' . $this->core->con->in($params['notice_id']);
        }

        if (!empty($params['notice_type'])) {
            $strReq .= 'AND notice_type' . $this->core->con->in($params['notice_type']);
        }

        if (!empty($params['notice_format'])) {
            $strReq .= 'AND notice_type' . $this->core->con->in($params['notice_format']);
        }

        if (!empty($params['sql'])) {
            $strReq .= ' ' . $params['sql'] . ' ';
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $strReq .= 'ORDER BY ' . $this->core->con->escape($params['order']) . ' ';
            } else {
                $strReq .= 'ORDER BY notice_ts DESC ';
            }
        }

        if (!empty($params['limit'])) {
            $strReq .= $this->core->con->limit($params['limit']);
        }

        $rs = $this->core->con->select($strReq);

        return $rs;
    }

    /** @see    add() */
    public function addNotice(Cursor $cur): int
    {
        return $this->add($cur);
    }


    /**
     * Add a notice
     *
     * @param   Cursor  $cur    The cursor
     */
    public function add(Cursor $cur): int
    {
        $this->core->con->writeLock($this->prefix . $this->table);

        try {
            # Get ID
            $rs = $this->core->con->select(
                'SELECT MAX(notice_id) ' .
                'FROM ' . $this->prefix . $this->table
            );

            $cur->notice_id = (int) $rs->f(0) + 1;
            $cur->ses_id    = (string) session_id();

            $this->cursor($cur, $cur->notice_id);

            # --BEHAVIOR-- before:Core:Notices:addNotice, Dotclear\Core\Notices, Dotclear\Database\Cursor
            $this->core->behaviors->call('before:Core:Notices:addNotice', $this, $cur);

            $cur->insert();
            $this->core->con->unlock();
        } catch (Exception $e) {
            $this->core->con->unlock();

            throw $e;
        }

        # --BEHAVIOR-- after:Core:Notices:addNotice, Dotclear\Core\Notices, Dotclear\Database\Cursor
        $this->core->behaviors->call('after:Core:Notices:addNotice', $this, $cur);

        return $cur->notice_id;
    }


    /** @see    del() */
    public function delNotices(?int $notice_id, bool $delete_all = false): void
    {
        $this->del($notice_id, $delete_all);
    }

    /**
     * Delete a notice
     *
     * @param   int|null    $notice_id      The notice id
     * @param   bool        $delete_all     Delete all notices
     */
    public function del(?int $notice_id, bool $delete_all = false): void
    {
        $strReq = $delete_all ?
        'DELETE FROM ' . $this->prefix . $this->table . " WHERE ses_id = '" . (string) session_id() . "'" :
        'DELETE FROM ' . $this->prefix . $this->table . ' WHERE notice_id' . $this->core->con->in($notice_id);

        $this->core->con->execute($strReq);
    }

    /**
     * Get notices cursor
     *
     * @param   Cursor      $cur        The cursor
     * @param   int|null    $notice_id  The notice id
     */
    private function cursor(Cursor $cur, int $notice_id = null): void
    {
        if ($cur->notice_msg === '') {
            throw new CoreException(__('No notice message'));
        }

        if ($cur->notice_ts === '' || $cur->notice_ts === null) {
            $cur->notice_ts = date('Y-m-d H:i:s');
        }

        if ($cur->notice_format === '' || $cur->notice_format === null) {
            $cur->notice_format = 'text';
        }

        $notice_id = is_int($notice_id) ? $notice_id : $cur->notice_id;
    }
}
