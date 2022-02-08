<?php
/**
 * @class Dotclear\Exception\PrependException
 * @brief Dotclear startup exception
 *
 * @package Dotclear
 * @subpackage Exception
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

use Dotclear\App;
use Dotclear\Exception;

class PrependException extends Exception
{
    /**
     * Constructor
     *
     * Except in CLI mode, it always displays an html error page.
     * Its construction differ from Exception
     * and it's throw from Prepend processes.
     *
     * @param   string  $message The message
     * @param   string  $detail  The detail
     * @param   int     $code    The code
     */
    public function __construct(string $message = 'Unknow Exception', string $detail = '', int $code = 0)
    {
        App::error($message, $detail, $code);
    }
}
