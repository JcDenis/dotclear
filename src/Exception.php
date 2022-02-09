<?php
/**
 * @class Dotclear\Exception
 * @brief Dotclear root Exception class
 *
 * @package Dotclear
 * @subpackage Exception
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear;

class Exception extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        # In VERBOSE mode all Exceptions are shown and stop process even if they're caught
        if (defined('DOTCLEAR_RUN_LEVEL') && DOTCLEAR_RUN_LEVEL >= DOTCLEAR_RUN_VERBOSE) {
            dotclear_error($message, str_replace("\n", '<br />', $this->getTraceAsString()), $code);
        }
    }
}
