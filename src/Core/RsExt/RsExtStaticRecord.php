<?php
/**
 * @class Dotclear\Core\RsExt\RsExtStaticRecord
 * @brief Dotclear lexical record helpers.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Database\StaticRecord;
use Dotclear\Helper\Lexical;

class RsExtStaticRecord extends StaticRecord
{
    private $sortfield;
    private $sortsign;

    /**
     * Constructor
     * 
     * @params  StaticRecord    $rs     StaticRecord instance
     */
    public function __construct(StaticRecord $rs)
    {
        parent::__construct($rs->__data, $rs->__info);
    }

    /**
     * Lexically sort.
     *
     * @param   string  $field  The field
     * @param   string  $order  The order
     */
    public function lexicalSort(string $field, string $order = 'asc'): void
    {
        $this->sortfield = $field;
        $this->sortsign  = strtolower($order) == 'asc' ? 1 : -1;

        usort($this->__data, [$this, 'lexicalSortCallback']);

        $this->sortfield = null;
        $this->sortsign  = null;
    }

    /**
     * Lexical sort field
     * 
     * @param   array   $a
     * @param   array   $b
     * 
     * @return  int
     */
    private function lexicalSortCallback(array $a, array $b): int
    {
        if (!isset($a[$this->sortfield]) || !isset($b[$this->sortfield])) {
            return 0;
        }

        $a = $a[$this->sortfield];
        $b = $b[$this->sortfield];

        # Integer values
        if ($a == (string) (int) $a && $b == (string) (int) $b) {
            $a = (int) $a;
            $b = (int) $b;

            return ($a - $b) * $this->sortsign;
        }

        return strcoll(strtolower(Lexical::removeDiacritics($a)), strtolower(Lexical::removeDiacritics($b))) * $this->sortsign;
    }
}
