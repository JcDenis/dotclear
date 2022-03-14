<?php
/**
 * @class Dotclear\Exception\PrependException
 * @brief Dotclear startup exception
 *
 * @package Dotclear
 * @subpackage Exception
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

class PrependException extends \Exception
{
    /**
     * Constructor
     *
     * Except in CLI mode, it always displays an html error page.
     * Its construction differ from Exception
     * and it's throw from Prepend processes.
     *
     * @uses    dotclear_error()
     *
     * @param   string  $message The message
     * @param   string  $detail  The detail
     * @param   int     $code    The code
     */
    public function __construct(string $message = 'Unknow Exception', string $detail = '', int $code = 0, bool $trace = false, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if ($trace) {
            $traces = $this->getTrace();
            if ($previous) {
                array_unshift($traces, ['file' => $previous->getFile(), 'line' => $previous->getLine()]);
            }
        }
        dotclear_error($message, $detail, $code, $traces);
    }
}
