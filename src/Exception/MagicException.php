<?php
/**
 * @class Dotclear\Exception\MagicException
 *
 * @package Dotclear
 * @subpackage Exception
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

class MagicException extends \Exception
{
    public function __construct($message = 'Exception for the use of deprecated function', $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}