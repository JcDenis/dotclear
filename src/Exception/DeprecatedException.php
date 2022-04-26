<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\DeprecatedException
use Exception;
use Throwable;
use Dotclear\App;

/**
 * Deprecated exception.
 *
 * Do not use directly "throw new DeprecatedException"
 * Use "DeprecatedException::throw()" instead
 * as it not stops process in production run.
 *
 * @ingroup  Deprecated Exception
 */
class DeprecatedException extends Exception
{
    public function __construct($message = 'Exception for the use of deprecated function', $code = 0, Throwable $previous = null)
    {
        $trace = $this->getTrace();
        $message .= ' ' . $trace[1]['class'] . '::' . $trace[1]['function'] . '" in ' . $trace[1]['file'] . ' line ' . $trace[1]['line'] . "\n";

        parent::__construct($message, $code, $previous);
    }

    public static function throw(): void
    {
        if (!App::core()->production()) {
            throw new self();
        }
    }
}
