<?php
/**
 * @class Dotclear\Admin\Page\Catalog\Catalog
 * @brief Dotclear admin list helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page\Catalog;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Catalog
{
    protected $rs;
    protected $rs_count;
    protected $html_prev;
    protected $html_next;

    /**
     * Constructs a new instance.
     *
     * @param      record  $rs        The record
     * @param      mixed   $rs_count  The rs count
     */
    public function __construct($rs, $rs_count)
    {
        $this->rs        = $rs;
        $this->rs_count  = (int) $rs_count;
        $this->html_prev = __('&#171; prev.');
        $this->html_next = __('next &#187;');
    }

    /**
     * Get user defined columns
     *
     * @param      string               $type   The type
     * @param      array|ArrayObject    $cols   The columns
     */
    public function userColumns($type, $cols)
    {
        $cols = dotclear()->listoption()->getUserColumns($type, $cols);
    }
}
