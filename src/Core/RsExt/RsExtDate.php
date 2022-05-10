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
            date: $this->rs->f('dt'),
            to: App::core()->timezone()
        );
    }

    /**
     * Get date year.
     */
    public function year(): string
    {
        return Clock::format(
            format: 'Y',
            date: $this->ts(),
            from: App::core()->timezone(),
            to: App::core()->timezone()
        );
    }

    /**
     * Get date month.
     */
    public function month(): string
    {
        return Clock::format(
            format: 'm',
            date: $this->ts(),
            from: App::core()->timezone(),
            to: App::core()->timezone()
        );
    }

    /**
     * Get date day.
     */
    public function day(): string
    {
        return Clock::format(
            format: 'd',
            date: $this->ts(),
            from: App::core()->timezone(),
            to: App::core()->timezone()
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
            from: App::core()->timezone(),
            to: App::core()->timezone()
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
            format: $format ?: App::core()->blog()->settings()->get('system')->get('date_format'),
            date: $this->rs->f('dt'),
            to: App::core()->timezone()
        );
    }
}
