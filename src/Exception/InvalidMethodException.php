<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\InvalidMethodException
use Exception;
use Throwable;

/**
 * Invalid method call exception.
 *
 * @ingroup Exception
 */
class InvalidMethodException extends Exception
{
    public function __construct($message = 'Invalid method call.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
