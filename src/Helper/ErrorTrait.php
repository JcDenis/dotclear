<?php
/**
 * @class Dotclear\Helper\ErrorTrait
 * @brief Dotclear trait error
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

use Dotclear\Helper\Error;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait ErrorTrait
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
