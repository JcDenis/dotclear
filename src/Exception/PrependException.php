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
    public function __construct(string $message = 'Unknow Exception', string $detail = '', int $code = 0, bool $trace = false, \Throwable $e = null)
    {
        parent::__construct($message, $code, $e);

        $traces = null;
        if ($trace) {
            $traces = $this->getTrace();
            if ($e) {
                array_unshift($traces, ['file' => $e->getFile(), 'line' => $e->getLine()]);
                if (null != ($previous = $e->getPrevious())) {
                    array_unshift($traces, ['file' => $previous->getFile(), 'line' => $previous->getLine()]);
                }
            }
        }
        dotclear_error($message, $detail, $code, $traces);
    }
}
