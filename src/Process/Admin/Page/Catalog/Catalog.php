<?php
/**
 * @class Dotclear\Process\Admin\Page\Catalog\Catalog
 * @brief Dotclear admin list helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Page\Catalog;

use ArrayObject;

use Dotclear\Database\Record;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Catalog
{
    /** @var    Record  The catalog record */
    protected $rs;

    /** @var    int     The catalog count */
    protected $rs_count;

    /** @var    string  The HTML representation of previous */
    protected $html_prev;

    /** @var    string  The HTML representation of next */
    protected $html_next;

    /**
     * Constructs a new instance.
     *
     * @param   Record  $rs         The record
     * @param   int     $rs_count   The rs count
     */
    public function __construct(Record $rs, int $rs_count)
    {
        $this->rs        = $rs;
        $this->rs_count  = (int) $rs_count;
        $this->html_prev = __('&#171; prev.');
        $this->html_next = __('next &#187;');
    }

    /**
     * Get user defined columns
     *
     * @param   string          $type   The type
     * @param   ArrayObject     $cols   The columns
     */
    public function userColumns(string $type, ArrayObject $cols): void
    {
        $cols = dotclear()->listoption()->getUserColumns($type, $cols);
    }
}