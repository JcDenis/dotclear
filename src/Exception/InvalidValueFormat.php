<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\InvalidValueFormat
use Exception;
use Throwable;

/**
 * Invalid value format exception.
 *
 * @ingroup Exception
 */
class InvalidValueFormat extends Exception
{
    public function __construct($message = 'Invalid value format.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
