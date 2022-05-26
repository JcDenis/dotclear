<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\InvalidValueReference
use Exception;
use Throwable;

/**
 * Invalid value reference exception.
 *
 * @ingroup Exception
 */
class InvalidValueReference extends Exception
{
    public function __construct($message = 'Invalid value reference.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
