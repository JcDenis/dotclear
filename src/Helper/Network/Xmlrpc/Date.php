<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Xmlrpc;

// Dotclear\Helper\Network\Xmlrpc\Date

/**
 * XML-RPC Date object.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Network Xmlrpc
 */
class Date
{
    protected $year;   // /< string
    protected $month;  // /< string
    protected $day;    // /< string
    protected $hour;   // /< string
    protected $minute; // /< string
    protected $second; // /< string
    protected $ts;

    /**
     * Constructor.
     *
     * Creates a new instance of xmlrpcDate. <var>$time</var> could be a
     * timestamp or a litteral date.
     *
     * @param int|string $time timestamp or litteral date
     */
    public function __construct(int|string $time)
    {
        // $time can be a PHP timestamp or an ISO one
        if (is_numeric($time)) {
            $this->parseTimestamp($time);
        } else {
            $this->parseTimestamp(strtotime($time));
        }
    }

    /**
     * Timestamp parser.
     *
     * @param int $timestamp Timestamp
     */
    protected function parseTimestamp(int $timestamp): void
    {
        $this->year   = date('Y', $timestamp);
        $this->month  = date('m', $timestamp);
        $this->day    = date('d', $timestamp);
        $this->hour   = date('H', $timestamp);
        $this->minute = date('i', $timestamp);
        $this->second = date('s', $timestamp);
        $this->ts     = $timestamp;
    }

    /**
     * ISO Date.
     *
     * Returns the date in ISO-8601 format.
     */
    public function getIso(): string
    {
        return $this->year . $this->month . $this->day . 'T' . $this->hour . ':' . $this->minute . ':' . $this->second;
    }

    /**
     * XML Date.
     *
     * Returns the XML fragment for XML-RPC message inclusion.
     */
    public function getXml(): string
    {
        return '<dateTime.iso8601>' . $this->getIso() . '</dateTime.iso8601>';
    }

    /**
     * Timestamp.
     *
     * Returns the date timestamp.
     */
    public function getTimestamp(): int
    {
        return mktime($this->hour, $this->minute, $this->second, $this->month, $this->day, $this->year);
    }
}
