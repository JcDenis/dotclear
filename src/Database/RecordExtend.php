<?php
/**
 * @class Dotclear\Database\RecordExtend
 * @brief Database record extension
 *
 * @package Dotclear
 * @subpackage Database
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

use Dotclear\Database\Record;

abstract class RecordExtend
{
    /** @var    Record  $rs     The record */
    protected $rs;

    /**
     * Set record
     * 
     * @param   Record  $rs     The record
     */
    public function setRecord(Record $rs): void
    {
        $this->rs = $rs;
    }
}
