<?php
/**
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

use Dotclear\Database\Record;
use Dotclear\Database\Cursor;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Notices
{
    /** @var dcCore dotclear core instance */
    protected $core;
    protected $prefix;
    protected $table = 'notice';

    /**
     * Class constructor
     *
     * @param mixed  $core   dotclear core
     *
     * @access public
     *
     * @return mixed Value.
     */
    public function __construct(Core $core)
    {
        $this->core   = &$core;
        $this->prefix = $core->prefix;
    }

    public function getTable()
    {
        return $this->table;
    }

    /* Get notices */

    public function getNotices(array $params = [], bool $count_only = false): Record
    {
        // Return a recordset of notices
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
                array_walk($params['notice_id'], function (&$v, $k) { if ($v !== null) {$v = (integer) $v;}});
            } else {
                $params['notice_id'] = [(integer) $params['notice_id']];
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

    public function addNotice(Cursor $cur): int
    {
        $this->core->con->writeLock($this->prefix . $this->table);

        try {
            # Get ID
            $rs = $this->core->con->select(
                'SELECT MAX(notice_id) ' .
                'FROM ' . $this->prefix . $this->table
            );

            $cur->notice_id = (integer) $rs->f(0) + 1;
            $cur->ses_id    = (string) session_id();

            $this->getNoticeCursor($cur, $cur->notice_id);

            # --BEHAVIOR-- coreBeforeNoticeCreate
            $this->core->behaviors->call('coreBeforeNoticeCreate', $this, $cur);

            $cur->insert();
            $this->core->con->unlock();
        } catch (Exception $e) {
            $this->core->con->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterNoticeCreate
        $this->core->behaviors->call('coreAfterNoticeCreate', $this, $cur);

        return $cur->notice_id;
    }

    public function delNotices($id, bool $all = false): void
    {
        $strReq = $all ?
        'DELETE FROM ' . $this->prefix . $this->table . " WHERE ses_id = '" . (string) session_id() . "'" :
        'DELETE FROM ' . $this->prefix . $this->table . ' WHERE notice_id' . $this->core->con->in($id);

        $this->core->con->execute($strReq);
    }

    private function getNoticeCursor(Cursor $cur, $notice_id = null): void
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
