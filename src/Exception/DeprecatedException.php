<?php
/**
 * @class Dotclear\Exception\UtilsException
 *
 * @package Dotclear
 * @subpackage Exception
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

use Dotclear\Exception;

class DeprecatedException extends Exception
{
    public function __construct($message = 'Exception for the use of deprecated function', $code = 0, Throwable $previous = null)
    {
        $trace = $this->getTrace();
        $message .= ' ' . $trace[1]['class'] .'::' . $trace[1]['function'] . '" in ' . $trace[1]['file'] . " line " . $trace[1]['line'] . "\n";

        parent::__construct($message, $code, $previous);
    }

    public static function throw(): void
    {
        if (defined('DOTCLEAR_DEV') && DOTCLEAR_DEV) {
            throw new self();
        }
    }
}
