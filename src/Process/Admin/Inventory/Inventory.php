<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Inventory;

// Dotclear\Process\Admin\Inventory\Inventory
use Dotclear\Database\Record;

/**
 * Admin list helper.
 *
 * @ingroup  Admin Inventory
 */
class Inventory
{
    /**
     * @var string $html_prev
     *             The HTML representation of previous
     */
    protected $html_prev;

    /**
     * @var string $html_next
     *             The HTML representation of next
     */
    protected $html_next;

    /**
     * Constructs a new instance.
     *
     * @param Record $rs       The record
     * @param int    $rs_count The rs count
     */
    public function __construct(protected Record $rs, protected int $rs_count)
    {
        $this->html_prev = __('&#171; prev.');
        $this->html_next = __('next &#187;');
    }
}
