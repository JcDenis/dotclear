<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

// Dotclear\Exception\InsufficientPermissions
use Exception;
use Throwable;

/**
 * Insufficient permissions exception.
 *
 * @ingroup Exception
 */
class InsufficientPermissions extends Exception
{
    public function __construct($message = 'Insufficient permissions.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
