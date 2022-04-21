<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\ErrorTrait

/**
 * Error stack trait.
 *
 * @ingroup  Helper Error
 */
trait ErrorTrait
{
    /**
     * @var Error $error
     *            Error instance
     */
    private $error;

    /**
     * Get instance.
     *
     * @return Error Error instance
     */
    public function error(): Error
    {
        if (!($this->error instanceof Error)) {
            $this->error = new Error();
        }

        return $this->error;
    }
}
