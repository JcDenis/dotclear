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
    protected $rs;

    public function setRecord(Record $rs)
    {
        $this->rs = $rs;
    }
}
