<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\MissingOrEmptyValue
use Exception;
use Throwable;

/**
 * Missing or empty value exception.
 *
 * @ingroup Exception
 */
class MissingOrEmptyValue extends Exception
{
    public function __construct($message = 'Missing or empty value.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
