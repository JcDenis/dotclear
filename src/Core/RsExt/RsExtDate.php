<?php
/**
 * @class Dotclear\Core\RsExt\RsExtDate
 * @brief Dotclear dates record helpers.
 *
 * This class adds new methods to database dates results.
 * You can call them on every record comming from dcBlog::getDates.
 *
 * @warning You should not give the first argument (usualy $rs)
 * of every described function.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Core\RsExt\RsExtend;
use Dotclear\Helper\Dt;

class RsExtDate extends RsExtend
{
    /**
     * Convert date to timestamp
     *
     * @return  int
     */
    public function ts(): int
    {
        return (int) strtotime($this->rs->f('dt'));
    }

    /**
     * Get date year
     *
     * @return  string
     */
    public function year(): string
    {
        return date('Y', $this->ts());
    }

    /**
     * Get date month
     *
     * @return  string
     */
    public function month(): string
    {
        return date('m',  $this->ts());
    }

    /**
     * Get date day
     *
     * @return  string
     */
    public function day(): string
    {
        return date('d',  $this->ts());
    }

    /**
     * Returns date month archive full URL.
     *
     * @return  string
     */
    public function url(): string
    {
        return dotclear()->blog()->getURLFor('archive', date('Y/m',  $this->ts()));
    }

    /**
     * Returns whether date is the first of year.
     *
     * @return  bool
     */
    public function yearHeader(): bool
    {
        if ($this->rs->isStart()) {
            return true;
        }

        $y = $this->year();
        $this->rs->movePrev();
        $py = $this->year();
        $this->rs->moveNext();

        return $y != $py;
    }

    /**
     * Returns whether date is the last of year.
     *
     * @return  bool
     */
    public function yearFooter(): bool
    {
        if ($this->rs->isEnd()) {
            return true;
        }

        $y = $this->year();
        if ($this->rs->moveNext()) {
            $ny = $this->year();
            $this->rs->movePrev();

            return $y != $ny;
        }

        return false;
    }

    /**
     * Returns date with <var>$format</var> as formatting pattern. If format
     * is empty, uses <var>date_format</var> blog setting.
     *
     * @param   string  $format     The date format pattern
     *
     * @return  string              The date.
     */
    public function getDate(string $format = ''): string
    {
        return Dt::dt2str($format ?: dotclear()->blog()->settings()->get('system')->get('date_format'), $this->rs->f('dt'));
    }
}
