<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\MagicException
use Exception;
use Throwable;

/**
 * Add exception on use of some PHP magic methods.
 *
 * @ingroup  Exception
 */
class MagicException extends Exception
{
    public function __construct($message = 'Exception for the use of deprecated function', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
