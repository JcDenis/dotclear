<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\InvalidValueType
use Error;
use Throwable;

/**
 * Invalid value type error.
 *
 * @note Invalid value type throws an Error, not an Exception
 *
 * @ingroup Exception
 */
class InvalidValueType extends Error
{
    public function __construct($message = 'Invalid value type.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
