<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\InvalidConfiguration
use Exception;
use Throwable;

/**
 * Missing or empty value exception.
 *
 * @ingroup Exception
 */
class InvalidConfiguration extends Exception
{
    public function __construct($message = 'Invalid configuration.', $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
