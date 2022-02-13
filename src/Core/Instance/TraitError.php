<?php
/**
 * @class Dotclear\Utils\TraitError
 * @brief Dotclear trait error
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Utils;

use Dotclear\Utils\Error;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitError
{
    /** @var    Error   Error instance */
    private $error;

    /**
     * Get instance
     *
     * @return  Error   Error instance
     */
    public function error(): Error
    {
        if (!($this->error instanceof Error)) {
            $this->error = new Error();
        }

        return $this->error;
    }
}
