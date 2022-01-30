<?php
/**
 * @class Dotclear\Html\TraitError
 * @brief Dotclear trait error
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Html;

use Dotclear\Html\Error;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitError
{
    /** @var    Error   Error instance */
    private $error;

    public function error(?string $msg = null): Error
    {
        if (!($this->error instanceof Error)) {
            $this->error = new Error();
        }

        if ($msg) {
            $this->error->add($msg);
        }

        return $this->error;
    }
}
