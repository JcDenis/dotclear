<?php
/**
 * @class Dotclear\Module\TraitPrependPublic
 * @brief Dotclear Module public trait Prepend
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitPrependAdmin
{
    # Install module is optionnal on Admin process
    public static function installModule(): ?bool
    {
        return null;
    }
}
