<?php
/**
 * @class Dotclear\Core\Instance\TraitError
 * @brief Dotclear trait error
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Error;

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
