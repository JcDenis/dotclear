<?php
/**
 * @class Dotclear\Core\RsExt\rsExtDates
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

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class RsExtDates
{
    /**
     * Convert date to timestamp
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     integer
     */
    public static function ts($rs)
    {
        return strtotime($rs->dt);
    }

    /**
     * Get date year
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     string
     */
    public static function year($rs)
    {
        return date('Y', strtotime($rs->dt));
    }

    /**
     * Get date month
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     string
     */
    public static function month($rs)
    {
        return date('m', strtotime($rs->dt));
    }

    /**
     * Get date day
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     string
     */
    public static function day($rs)
    {
        return date('d', strtotime($rs->dt));
    }

    /**
     * Returns date month archive full URL.
     *
     * @param      record  $rs       Invisible parameter
     * @param      dcCore  $core     The core
     *
     * @return     string
     */
    public static function url($rs, Core $core)
    {
        $url = date('Y/m', strtotime($rs->dt));

        return $core->blog->url . $core->url->getURLFor('archive', $url);
    }

    /**
     * Returns whether date is the first of year.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     bool
     */
    public static function yearHeader($rs)
    {
        if ($rs->isStart()) {
            return true;
        }

        $y = $rs->year();
        $rs->movePrev();
        $py = $rs->year();
        $rs->moveNext();

        return $y != $py;
    }

    /**
     * Returns whether date is the last of year.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     bool
     */
    public static function yearFooter($rs)
    {
        if ($rs->isEnd()) {
            return true;
        }

        $y = $rs->year();
        if ($rs->moveNext()) {
            $ny = $rs->year();
            $rs->movePrev();

            return $y != $ny;
        }

        return false;
    }
}
