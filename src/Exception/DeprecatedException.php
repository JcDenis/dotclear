<?php
/**
 * @class Dotclear\Exception\UtilsException
 *
 * @package Dotclear
 * @subpackage Exception
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Exception;

use Dotclear\Exception;

class DeprecatedException extends Exception
{
    public function __toString() {
        $trace = $this->getTrace();
        return 'Exception for the use of deprecated function "' . $trace[1]['class'] .'::' . $trace[1]['function'] . '" in ' . $trace[1]['file'] . " line " . $trace[1]['line'] . "\n";
    }

    public static function throw(): void
    {
        if (defined('DOTCLEAR_DEBUG') && DOTCLEAR_DEBUG) {
            throw new self();
        }
    }
}
