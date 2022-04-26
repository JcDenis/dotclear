<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\PrependException
use Exception;
use Throwable;
use Dotclear\App;

/**
 * Prepend exception.
 *
 * @ingroup  Core Admin Public Install Distrib Exception
 */
class PrependException extends Exception
{
    /**
     * Constructor.
     *
     * Except in CLI mode, it always displays an html error page.
     * Its construction differ from Exception
     * and it's throw from Prepend processes.
     *
     * @param string    $message The message
     * @param string    $detail  The detail
     * @param int       $code    The code
     * @param bool      $trace   Add explicitly trace
     * @param Throwable $e       The original exception
     */
    public function __construct(string $message = 'Unknow Exception', string $detail = '', int $code = 0, bool $trace = false, Throwable $e = null)
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
        App::stop($message, $detail, $code, $traces);
    }
}
