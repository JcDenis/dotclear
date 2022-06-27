<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

// Dotclear\Core\RsExt\RsExtDate
use Dotclear\App;
use Dotclear\Helper\Clock;

/**
 * Record dates helpers.
 *
 * This class adds new methods to database dates results.
 * You can call them on every record comming from Blog::getDates.
 *
 * @ingroup  Core Date Record
 */
class RsExtDate extends RsExtend
{
    /**
     * Convert date to timestamp.
     */
    public function ts(): int
    {
        return Clock::ts(
            date: $this->rs->field('dt'),
            to: App::core()->getTimezone()
        );
    }

    /**
     * Get date year.
     */
    public function year(): int
    {
        return (int) Clock::format(
            format: 'Y',
            date: $this->ts(),
            from: App::core()->getTimezone(),
            to: App::core()->getTimezone()
        );
    }

    /**
     * Get date month.
     */
    public function month(): int
    {
        return (int) Clock::format(
            format: 'm',
            date: $this->ts(),
            from: App::core()->getTimezone(),
            to: App::core()->getTimezone()
        );
    }

    /**
     * Get date day.
     */
    public function day(): int
    {
        return (int) Clock::format(
            format: 'd',
            date: $this->ts(),
            from: App::core()->getTimezone(),
            to: App::core()->getTimezone()
        );
    }

    /**
     * Returns date month archive full URL.
     */
    public function url(): string
    {
        return App::core()->blog()->getURLFor('archive', Clock::format(
            format: 'Y/m',
            date: $this->ts(),
            from: App::core()->getTimezone(),
            to: App::core()->getTimezone()
        ));
    }

    /**
     * Returns whether date is the first of year.
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
     * @param string $format The date format pattern
     *
     * @return string the date
     */
    public function getDate(string $format = ''): string
    {
        return Clock::str(
            format: $format ?: App::core()->blog()->settings('system')->getSetting('date_format'),
            date: $this->rs->field('dt'),
            to: App::core()->getTimezone()
        );
    }
}
