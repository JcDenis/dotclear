<?php
/**
 * @class Dotclear\Theme\Ductile\Public\Prepend
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeDuctile
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Theme\Ductile\Public\DuctileBehavior;
use Dotclear\Theme\Ductile\Public\DuctileTemplate;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        if (dotclear()->blog()->settings()->system->theme != 'Ductile') {
            return;
        }

        DuctileBehavior::initDuctile();
        DuctileTemplate::initDuctile();
    }
}
