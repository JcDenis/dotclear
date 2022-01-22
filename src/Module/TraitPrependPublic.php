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

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitPrependPublic
{
    # Module check is not used on Public process
    public static function checkModule(Core $core): bool
    {
        return true;
    }

    # Module check is not used on Public process
    public static function installModule(Core $core): ?bool
    {
        return null;
    }
}
